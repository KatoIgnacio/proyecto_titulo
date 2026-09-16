<?php

namespace App\Console\Commands;

use App\Support\DatabaseConnectionFailure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class CheckApplicationHealth extends Command
{
    /** @var list<string> */
    private const REQUIRED_TABLES = [
        'migrations',
        'users',
        'communes',
        'feeders',
        'contingencies',
        'contingency_impacts',
        'contingency_history',
        'field_reports',
        'field_report_attachments',
    ];

    protected $signature = 'luzparral:health
                            {--database : Comprueba la conexión y las tablas esenciales}
                            {--json : Emite una respuesta estructurada para automatización}';

    protected $description = 'Comprueba la salud de la aplicación y, opcionalmente, de su base de datos';

    public function handle(): int
    {
        $checks = ['application' => 'ok'];

        if (! $this->option('database')) {
            return $this->successfulResult($checks);
        }

        try {
            $probe = DB::selectOne('SELECT 1 AS connection_test');

            if ((int) ($probe->connection_test ?? 0) !== 1) {
                throw new \RuntimeException('La consulta de prueba no devolvió el valor esperado.');
            }

            $checks['database'] = 'ok';
            $missingTables = array_values(array_filter(
                self::REQUIRED_TABLES,
                fn (string $table): bool => ! Schema::hasTable($table),
            ));

            if ($missingTables !== []) {
                return $this->failedResult(
                    'La base de datos respondió, pero el esquema requerido está incompleto.',
                    $checks + ['schema' => 'error'],
                    ['missing_tables' => $missingTables],
                );
            }

            $checks['schema'] = 'ok';
        } catch (Throwable $exception) {
            return $this->failedResult(
                'No fue posible verificar la conexión con la base de datos.',
                $checks + ['database' => 'error'],
                DatabaseConnectionFailure::diagnosticContext($exception),
            );
        }

        return $this->successfulResult($checks);
    }

    /** @param array<string, string> $checks */
    private function successfulResult(array $checks): int
    {
        if ($this->option('json')) {
            $this->line((string) json_encode([
                'status' => 'ok',
                'checks' => $checks,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        foreach ($checks as $component => $status) {
            $this->line(ucfirst($component).': '.strtoupper($status));
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $checks
     * @param  array<string, mixed>  $context
     */
    private function failedResult(string $message, array $checks, array $context): int
    {
        $reference = (string) Str::uuid();

        Log::error('health.database_failed', $context + [
            'diagnostic_id' => $reference,
        ]);

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'status' => 'error',
                'checks' => $checks,
                'message' => $message,
                'diagnostic_id' => $reference,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::FAILURE;
        }

        $this->error($message);
        $this->line('Identificador de diagnóstico: '.$reference);

        return self::FAILURE;
    }
}
