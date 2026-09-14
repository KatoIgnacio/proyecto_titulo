<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ValidateSyntheticDataset extends Command
{
    protected $signature = 'luzparral:validate-synthetic
                            {--require-runtime : Exige también las tablas internas usadas por Laravel}
                            {--json : Entrega el resultado como JSON}';

    protected $description = 'Valida cantidades, marcadores e integridad del conjunto sintético Luzparral';

    /**
     * @var array<int, array{name: string, passed: bool, detail: string}>
     */
    private array $checks = [];

    public function handle(): int
    {
        try {
            $requiredTables = [
                'dataset_metadata',
                'users',
                'communes',
                'feeders',
                'supply_points',
                'import_batches',
                'import_errors',
                'contingencies',
                'contingency_impacts',
                'contingency_history',
            ];

            if ($this->option('require-runtime')) {
                $requiredTables = array_merge($requiredTables, [
                    'migrations',
                    'cache',
                    'cache_locks',
                    'jobs',
                    'job_batches',
                    'failed_jobs',
                    'password_reset_tokens',
                    'sessions',
                ]);
            }

            $missingTables = array_values(array_filter(
                $requiredTables,
                fn (string $table): bool => ! Schema::hasTable($table),
            ));
            $this->record('Estructura requerida', $missingTables === [], $missingTables === []
                ? count($requiredTables).' tablas disponibles'
                : 'Faltan: '.implode(', ', $missingTables));

            if ($missingTables !== []) {
                return $this->finish();
            }

            $metadataRows = DB::table('dataset_metadata')->get();
            $metadataIsValid = $metadataRows->count() === 1
                && $metadataRows->first()?->dataset_key === 'luzparral-synthetic-v1';
            $this->record('Marcador sintético', $metadataIsValid, $metadataIsValid
                ? 'luzparral-synthetic-v1'
                : 'Debe existir exclusivamente el marcador esperado');

            if (! $metadataIsValid) {
                return $this->finish();
            }

            $parameters = json_decode((string) $metadataRows->first()->parameters_json, true, flags: JSON_THROW_ON_ERROR);
            $countMap = [
                'users' => 'users',
                'communes' => 'communes',
                'feeders' => 'feeders',
                'supply_points' => 'supply_points',
                'import_batches' => 'import_batches',
                'contingencies' => 'contingencies',
                'impacts' => 'contingency_impacts',
                'history_events' => 'contingency_history',
            ];

            foreach ($countMap as $parameter => $table) {
                $expected = isset($parameters[$parameter]) ? (int) $parameters[$parameter] : -1;
                $actual = DB::table($table)->count();
                $this->record(
                    "Cantidad de {$table}",
                    $expected >= 0 && $actual === $expected,
                    "esperado={$expected}; actual={$actual}",
                );
            }

            $this->record(
                'Errores de importación controlados',
                DB::table('import_errors')->exists(),
                DB::table('import_errors')->count().' registros',
            );

            $nonSyntheticSupplyPoints = DB::table('supply_points')
                ->where(function ($query): void {
                    $query->where('synthetic_code', 'not like', 'SYN-SP-%')
                        ->orWhere('customer_code', 'not like', 'SYN-CL-%');
                })
                ->count();
            $this->record('Códigos de suministro sintéticos', $nonSyntheticSupplyPoints === 0, "anomalías={$nonSyntheticSupplyPoints}");

            $nonSyntheticUsers = DB::table('users')
                ->where('email', 'not like', '%@luzparral.example.invalid')
                ->count();
            $this->record('Correos de usuarios sintéticos', $nonSyntheticUsers === 0, "anomalías={$nonSyntheticUsers}");

            $invalidCoordinates = DB::table('supply_points')
                ->where(function ($query): void {
                    $query->whereNotBetween('latitude', [-37.0, -35.0])
                        ->orWhereNotBetween('longitude', [-72.5, -71.0]);
                })
                ->count();
            $this->record('Coordenadas dentro del área ficticia', $invalidCoordinates === 0, "anomalías={$invalidCoordinates}");

            $invalidBatches = DB::table('import_batches')
                ->whereRaw('total_rows <> accepted_rows + rejected_rows')
                ->count();
            $this->record('Totales de importación consistentes', $invalidBatches === 0, "anomalías={$invalidBatches}");

            $impactTotals = DB::table('contingency_impacts')
                ->select('contingency_id')
                ->selectRaw('COUNT(*) AS total')
                ->groupBy('contingency_id');
            $invalidAffectedTotals = DB::table('contingencies as c')
                ->leftJoinSub($impactTotals, 'impact_totals', 'impact_totals.contingency_id', '=', 'c.id')
                ->whereRaw('c.affected_total <> COALESCE(impact_totals.total, 0)')
                ->count();
            $this->record('Afectados concordantes con impactos', $invalidAffectedTotals === 0, "anomalías={$invalidAffectedTotals}");

            $criticalTotals = DB::table('contingency_impacts as ci')
                ->join('supply_points as sp', 'sp.id', '=', 'ci.supply_point_id')
                ->select('ci.contingency_id')
                ->selectRaw("SUM(CASE WHEN sp.criticality IN ('critical', 'critical_electrodependent') THEN 1 ELSE 0 END) AS critical_total")
                ->selectRaw("SUM(CASE WHEN sp.criticality IN ('electrodependent', 'critical_electrodependent') THEN 1 ELSE 0 END) AS electrodependent_total")
                ->groupBy('ci.contingency_id');
            $invalidCriticalTotals = DB::table('contingencies as c')
                ->leftJoinSub($criticalTotals, 'critical_totals', 'critical_totals.contingency_id', '=', 'c.id')
                ->where(function ($query): void {
                    $query->whereRaw('c.critical_affected <> COALESCE(critical_totals.critical_total, 0)')
                        ->orWhereRaw('c.electrodependent_affected <> COALESCE(critical_totals.electrodependent_total, 0)');
                })
                ->count();
            $this->record('Críticos concordantes con impactos', $invalidCriticalTotals === 0, "anomalías={$invalidCriticalTotals}");

            $invalidContingencyTimes = DB::table('contingencies')
                ->whereNotNull('restored_at')
                ->whereColumn('restored_at', '<', 'started_at')
                ->count();
            $invalidImpactTimes = DB::table('contingency_impacts')
                ->whereNotNull('restored_at')
                ->whereColumn('restored_at', '<', 'affected_at')
                ->count();
            $invalidHistoryTimes = DB::table('contingency_history as ch')
                ->join('contingencies as c', 'c.id', '=', 'ch.contingency_id')
                ->whereColumn('ch.event_at', '<', 'c.started_at')
                ->count();
            $invalidTimes = $invalidContingencyTimes + $invalidImpactTimes + $invalidHistoryTimes;
            $this->record('Cronología operacional válida', $invalidTimes === 0, "anomalías={$invalidTimes}");
        } catch (Throwable $error) {
            $this->record('Ejecución de la validación', false, $error->getMessage());
        }

        return $this->finish();
    }

    private function record(string $name, bool $passed, string $detail): void
    {
        $this->checks[] = compact('name', 'passed', 'detail');
    }

    private function finish(): int
    {
        $passed = collect($this->checks)->every(fn (array $check): bool => $check['passed']);

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'database' => $this->databaseName(),
                'passed' => $passed,
                'checks' => $this->checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Control', 'Resultado', 'Detalle'],
                array_map(fn (array $check): array => [
                    $check['name'],
                    $check['passed'] ? 'OK' : 'ERROR',
                    $check['detail'],
                ], $this->checks),
            );

            $passed
                ? $this->info('Conjunto sintético válido.')
                : $this->error('La validación detectó inconsistencias.');
        }

        return $passed ? self::SUCCESS : self::FAILURE;
    }

    private function databaseName(): string
    {
        try {
            return DB::connection()->getDatabaseName();
        } catch (Throwable) {
            return 'no-disponible';
        }
    }
}
