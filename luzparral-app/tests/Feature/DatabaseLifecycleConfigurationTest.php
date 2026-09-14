<?php

namespace Tests\Feature;

use Tests\TestCase;

class DatabaseLifecycleConfigurationTest extends TestCase
{
    public function test_backup_uses_consistent_dump_without_exposing_password(): void
    {
        $contents = file_get_contents(base_path('database/scripts/backup-mysql.ps1'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('LUZPARRAL_DB_ALLOWED_DATABASE', $contents);
        $this->assertStringContainsString('LUZPARRAL_DB_PASSWORD', $contents);
        $this->assertStringContainsString("'--single-transaction'", $contents);
        $this->assertStringContainsString('Get-FileHash -Algorithm SHA256', $contents);
        $this->assertStringNotContainsString('--password=', $contents);
        $this->assertStringNotContainsString("'--databases'", $contents);
    }

    public function test_restore_requires_checksum_and_an_empty_target(): void
    {
        $contents = file_get_contents(base_path('database/scripts/restore-mysql.ps1'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('ConfirmEmptyTarget', $contents);
        $this->assertStringContainsString('Get-FileHash -Algorithm SHA256', $contents);
        $this->assertStringContainsString('TABLE_SCHEMA', $contents);
        $this->assertStringContainsString('La restauracion solo se permite sobre una base vacia', $contents);
        $this->assertStringNotContainsString('DROP DATABASE', $contents);
        $this->assertStringNotContainsString('--password=', $contents);
    }

    public function test_documentation_distinguishes_all_database_scenarios(): void
    {
        $contents = file_get_contents(base_path('docs/CICLO_BASE_DATOS.md'));

        $this->assertIsString($contents);
        foreach (['Escenario A', 'Escenario B', 'Escenario C', 'Escenario D', 'Crear un respaldo', 'Restaurar y validar'] as $section) {
            $this->assertStringContainsString($section, $contents);
        }
        $this->assertStringContainsString('migrate --pretend', $contents);
        $this->assertStringContainsString('validate-synthetic --require-runtime', $contents);
    }
}
