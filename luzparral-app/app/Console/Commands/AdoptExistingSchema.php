<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdoptExistingSchema extends Command
{
    protected $signature = 'luzparral:adopt-existing-schema
                            {--confirm-synthetic : Confirma que la base contiene el conjunto sintético controlado}';

    protected $description = 'Registra como línea base un esquema sintético Luzparral existente sin recrear sus tablas';

    public function handle(): int
    {
        if (! $this->option('confirm-synthetic')) {
            $this->error('Operación cancelada: use --confirm-synthetic solo sobre la base sintética controlada.');

            return self::FAILURE;
        }

        $requiredStructure = [
            'dataset_metadata' => ['dataset_key', 'generator_version', 'random_seed'],
            'users' => ['name', 'email', 'password', 'role', 'active'],
            'communes' => ['code', 'name', 'center_lat', 'center_lon'],
            'feeders' => ['commune_id', 'code', 'name'],
            'supply_points' => ['commune_id', 'feeder_id', 'latitude', 'longitude', 'criticality'],
            'import_batches' => ['status', 'total_rows', 'accepted_rows', 'rejected_rows'],
            'import_errors' => ['import_batch_id', 'error_code', 'message'],
            'contingencies' => ['code', 'status', 'started_at', 'affected_total'],
            'contingency_impacts' => ['contingency_id', 'supply_point_id', 'status'],
            'contingency_history' => ['contingency_id', 'status', 'event_at'],
        ];

        foreach ($requiredStructure as $table => $columns) {
            if (! Schema::hasTable($table)) {
                $this->error("Falta la tabla requerida: {$table}.");

                return self::FAILURE;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    $this->error("Falta la columna requerida: {$table}.{$column}.");

                    return self::FAILURE;
                }
            }
        }

        $datasetKeys = DB::table('dataset_metadata')->pluck('dataset_key')->all();
        if ($datasetKeys !== ['luzparral-synthetic-v1']) {
            $this->error('La base no posee exclusivamente el marcador sintético esperado. No se realizaron cambios.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('migrations')) {
            $exitCode = $this->call('migrate:install');
            if ($exitCode !== self::SUCCESS) {
                return $exitCode;
            }
        }

        $batch = max(1, ((int) DB::table('migrations')->max('batch')) + 1);
        $baselineMigrations = [
            '0001_01_01_000000_create_users_table',
            '2026_09_09_000100_create_luzparral_domain_tables',
        ];

        foreach ($baselineMigrations as $migration) {
            DB::table('migrations')->insertOrIgnore([
                'migration' => $migration,
                'batch' => $batch,
            ]);
        }

        $this->info('Esquema sintético validado y registrado como línea base.');
        $this->line('Base: '.DB::connection()->getDatabaseName());
        $this->line('No se eliminaron ni recrearon tablas operacionales.');

        return self::SUCCESS;
    }
}
