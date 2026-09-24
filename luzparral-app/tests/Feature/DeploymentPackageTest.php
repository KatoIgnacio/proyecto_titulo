<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeploymentPackageTest extends TestCase
{
    public function test_parra_environment_template_contains_no_credentials(): void
    {
        $contents = file_get_contents(base_path('deploy/parra/parra.env.example'));
        $production = file_get_contents(base_path('deploy/parra/parra-production.env.example'));
        $mysql = file_get_contents(base_path('deploy/parra/mysql.env.example'));

        $this->assertIsString($contents);
        $this->assertIsString($production);
        $this->assertIsString($mysql);
        $this->assertStringContainsString('APP_ENV=production', $contents);
        $this->assertStringContainsString('APP_DEBUG=false', $contents);
        $this->assertStringContainsString('APP_KEY=REEMPLAZAR_', $contents);
        $this->assertStringContainsString('DB_PASSWORD=REEMPLAZAR_', $contents);
        $this->assertStringContainsString('LOG_CHANNEL=stderr_json', $contents);
        $this->assertStringContainsString('SESSION_EXPIRE_ON_CLOSE=true', $contents);
        $this->assertStringContainsString('PASSWORD_RESET_ENABLED=false', $contents);
        $this->assertStringContainsString('SECURITY_HEADERS_ENABLED=true', $contents);
        $this->assertStringContainsString('SECURITY_MAX_ACTIVE_USERS=10', $contents);
        $this->assertStringContainsString('DB_HOST=luzparral-mysql', $contents);
        $this->assertStringContainsString('DB_DATABASE=sigcel_staging', $contents);
        $this->assertStringContainsString('DB_DATABASE=sigcel_production', $production);
        $this->assertStringContainsString('MYSQL_ROOT_PASSWORD=REEMPLAZAR_', $mysql);
        $this->assertStringContainsString('MYSQL_STAGING_DATABASE=sigcel_staging', $mysql);
        $this->assertStringContainsString('MYSQL_PRODUCTION_DATABASE=sigcel_production', $mysql);
        $this->assertStringNotContainsString('APP_KEY=base64:', $contents);
    }

    public function test_parra_deployment_separates_staging_and_production_ports(): void
    {
        $contents = file_get_contents(base_path('deploy/parra/deploy.sh'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('host_port=2004', $contents);
        $this->assertStringContainsString('host_port=2003', $contents);
        $this->assertStringContainsString('--env-file "$application_environment_file"', $contents);
        $this->assertStringContainsString('--network "$network_name"', $contents);
        $this->assertStringContainsString(':/var/www/html/storage', $contents);
        $this->assertStringContainsString('APP_URL debe utilizar el puerto', $contents);
        $this->assertStringContainsString('SESSION_LIFETIME debe ser un numero de hasta 60 minutos', $contents);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE debe ser true', $contents);
        $this->assertStringContainsString('PASSWORD_RESET_ENABLED', $contents);
        $this->assertStringContainsString('LOG_CHANNEL=stderr_json', $contents);
        $this->assertStringContainsString('/up', $contents);
        $this->assertStringContainsString('backup-database.sh', $contents);
        $this->assertStringContainsString('php artisan migrate --force --no-interaction', $contents);
        $this->assertLessThan(
            strpos($contents, 'php artisan migrate --force --no-interaction'),
            strpos($contents, 'backup-database.sh'),
        );
    }

    public function test_parra_mysql_is_persistent_private_and_isolated_by_environment(): void
    {
        $database = file_get_contents(base_path('deploy/parra/database.sh'));
        $backup = file_get_contents(base_path('deploy/parra/backup-database.sh'));
        $restore = file_get_contents(base_path('deploy/parra/restore-database.sh'));
        $restoreTest = file_get_contents(base_path('deploy/parra/test-backup-restore.sh'));

        $this->assertIsString($database);
        $this->assertStringContainsString('podman network create --internal', $database);
        $this->assertStringContainsString('podman network create "$edge_network_name"', $database);
        $this->assertStringContainsString('MySQL no debe conectarse a la red de entrada', $database);
        $this->assertStringContainsString('luzparral-mysql-data', $database);
        $this->assertStringContainsString('MYSQL_STAGING_DATABASE', $database);
        $this->assertStringContainsString('MYSQL_PRODUCTION_DATABASE', $database);
        $this->assertStringContainsString('requieren claves diferentes', $database);
        $this->assertStringNotContainsString('--publish', $database);

        $this->assertStringContainsString('--single-transaction', $backup);
        $this->assertStringContainsString('sha256sum', $backup);
        $this->assertStringContainsString('--confirm-empty-target', $restore);
        $this->assertStringContainsString('table_count', $restore);
        $this->assertStringContainsString('sigcel_restorecheck_', $restoreTest);
        $this->assertStringContainsString('DROP DATABASE IF EXISTS', $restoreTest);
    }

    public function test_server_verification_checks_security_headers_and_disabled_recovery(): void
    {
        $contents = file_get_contents(base_path('deploy/parra/verify.sh'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('X-Content-Type-Options: nosniff', $contents);
        $this->assertStringContainsString('X-Frame-Options: DENY', $contents);
        $this->assertStringContainsString('Content-Security-Policy:', $contents);
        $this->assertStringContainsString('/forgot-password', $contents);
        $this->assertStringContainsString('se esperaba 404', $contents);
        $this->assertStringContainsString('luzparral:health --database --json', $contents);
        $this->assertStringContainsString('database.sh" status', $contents);
        $this->assertStringContainsString('3306 no publicado', $contents);
        $this->assertStringNotContainsString('migrate:status', $contents);
    }

    public function test_image_transfer_requires_an_integrity_check(): void
    {
        $exportScript = file_get_contents(base_path('deploy/EXPORTAR_IMAGEN.ps1'));
        $loadScript = file_get_contents(base_path('deploy/parra/load-image.sh'));

        $this->assertIsString($exportScript);
        $this->assertIsString($loadScript);
        $this->assertStringContainsString('Get-FileHash -Algorithm SHA256', $exportScript);
        $this->assertStringContainsString('[System.IO.File]::WriteAllText', $exportScript);
        $this->assertStringContainsString('git status --porcelain', $exportScript);
        $this->assertStringContainsString("[string] \$DatabaseImage = 'mysql:8.4.11'", $exportScript);
        $this->assertStringContainsString("role = 'private-database'", $exportScript);
        $this->assertStringContainsString('sha256sum --check', $loadScript);
        $this->assertStringContainsString('podman load --input', $loadScript);
    }

    public function test_deployment_files_are_not_copied_into_the_runtime_image(): void
    {
        foreach (['.dockerignore', '.containerignore'] as $ignoreFile) {
            $contents = file_get_contents(base_path($ignoreFile));

            $this->assertIsString($contents);
            $this->assertMatchesRegularExpression('/^artifacts$/m', $contents);
            $this->assertMatchesRegularExpression('/^deploy\/EXPORTAR_IMAGEN\.ps1$/m', $contents);
            $this->assertMatchesRegularExpression('/^deploy\/parra$/m', $contents);
        }
    }

    public function test_deployment_guide_covers_the_latest_operational_modules(): void
    {
        $deployment = file_get_contents(base_path('docs/DESPLIEGUE_PARRA.md'));
        $handoff = file_get_contents(base_path('docs/INFORME_PREMIGRACION_PARRA.md'));

        $this->assertIsString($deployment);
        $this->assertIsString($handoff);
        $deployment = preg_replace('/\s+/', ' ', $deployment) ?? $deployment;
        $handoff = preg_replace('/\s+/', ' ', $handoff) ?? $handoff;
        $this->assertStringContainsString('filtros por dia, mes, año y rango', $deployment);
        $this->assertStringContainsString('pronostico Windy', $deployment);
        $this->assertStringContainsString('importacion controlada', $deployment);
        $this->assertStringContainsString('explicar las', $deployment);
        $this->assertStringContainsString('editar y eliminar', $deployment);
        $this->assertStringContainsString('mapa debe actualizar el area visible', $deployment);
        $this->assertStringContainsString('Pendiente exclusivamente en Parra', $handoff);
        $this->assertStringContainsString('puerto `2004`', $handoff);
        $this->assertStringContainsString('puerto `2004`', $deployment);
        $this->assertStringContainsString('puerto `2003`', $deployment);
        $this->assertStringContainsString('https://embed.windy.com', $handoff);
        $this->assertStringContainsString('persistencia después de reiniciar', $handoff);
        $this->assertStringContainsString('importación controlada', $handoff);
        $this->assertStringContainsString('motivo de cada rechazo', $handoff);
        $this->assertStringContainsString('Solo Administración puede editar o eliminar antecedentes', $handoff);
        $this->assertStringContainsString('agrupa marcadores', $handoff);
        $this->assertStringContainsString('luzparral-app-f0a05a164686-linux-amd64.tar', $handoff);
        $this->assertStringContainsString('quedan **obsoletos**', $handoff);
        $this->assertStringContainsString('después del', $handoff);
        $this->assertStringContainsString('commit limpio de este segmento', $handoff);
    }

    public function test_deployment_architecture_records_quality_scenarios_and_limits(): void
    {
        $contents = file_get_contents(base_path('docs/DECISIONES_ARQUITECTURA_DESPLIEGUE.md'));

        $this->assertIsString($contents);
        foreach (range(1, 7) as $number) {
            $this->assertStringContainsString('ASR-0'.$number, $contents);
        }
        $this->assertStringContainsString('ADR-01', $contents);
        $this->assertStringContainsString('ADR-04', $contents);
        $this->assertStringContainsString('puerto `3306` existe únicamente', $contents);
        $this->assertStringContainsString('punto único de falla', $contents);
    }
}
