<?php

namespace Tests\Feature;

use Tests\TestCase;

class ContainerIntegrationConfigurationTest extends TestCase
{
    public function test_integration_stack_keeps_mysql_separate_and_private(): void
    {
        $contents = file_get_contents(base_path('compose.integration.yaml'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('image: mysql:8.4.9', $contents);
        $this->assertStringContainsString('DB_HOST: db', $contents);
        $this->assertStringContainsString('condition: service_healthy', $contents);
        $this->assertStringContainsString('127.0.0.1:8080:8080', $contents);
        $this->assertStringNotContainsString('3306:3306', $contents);
        $this->assertStringContainsString('LOG_CHANNEL: stderr_json', $contents);
        $this->assertStringContainsString('SESSION_ENCRYPT: "true"', $contents);
    }

    public function test_integration_script_uses_ephemeral_secrets_and_complete_checks(): void
    {
        $contents = file_get_contents(base_path('deploy/VALIDAR_INTEGRACION_LOCAL.ps1'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('RandomNumberGenerator', $contents);
        $this->assertStringContainsString('database/synthetic/generate_synthetic.php', $contents);
        $this->assertStringContainsString('luzparral:health', $contents);
        $this->assertStringContainsString('luzparral:validate-synthetic', $contents);
        $this->assertStringContainsString("'/dashboard'", $contents);
        $this->assertStringContainsString("'/contingencias/mapa'", $contents);
        $this->assertStringContainsString('/contingencias/mapa/datos?', $contents);
        $this->assertStringContainsString("'/buscador-operacional'", $contents);
        $this->assertStringContainsString("'/informes'", $contents);
        $this->assertStringContainsString('contingencias.csv', $contents);
        $this->assertStringContainsString('contingencias.pdf', $contents);
        $this->assertStringContainsString('Remove-Item Env:LUZPARRAL_INTEGRATION_APP_KEY', $contents);
        $this->assertStringNotContainsString('REEMPLAZAR_PASSWORD', $contents);
    }

    public function test_integration_only_files_are_excluded_from_the_runtime_image(): void
    {
        foreach (['.dockerignore', '.containerignore'] as $ignoreFile) {
            $contents = file_get_contents(base_path($ignoreFile));

            $this->assertIsString($contents);
            $this->assertMatchesRegularExpression('/^compose\.integration\.yaml$/m', $contents);
            $this->assertMatchesRegularExpression('/^deploy\/VALIDAR_INTEGRACION_LOCAL\.ps1$/m', $contents);
        }
    }
}
