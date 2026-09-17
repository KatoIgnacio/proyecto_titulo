<?php

namespace Tests\Feature;

use Tests\TestCase;

class QualityAndPerformanceConfigurationTest extends TestCase
{
    public function test_performance_script_covers_the_expected_load_and_routes_without_embedded_secrets(): void
    {
        $contents = file_get_contents(base_path('deploy/MEDIR_RENDIMIENTO_LOCAL.ps1'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('foreach ($concurrentUsers in @(5, 10, 30))', $contents);
        $this->assertStringContainsString('maximum_concurrent_users = 30', $contents);
        $this->assertStringContainsString('rnf01_limit_ms = 3000', $contents);
        $this->assertStringContainsString('heavy_exports_maximum_users = 10', $contents);
        $this->assertStringContainsString('segment-13-performance.json', $contents);
        $this->assertStringContainsString("'/dashboard?range=12m'", $contents);
        $this->assertStringContainsString("'/contingencias/mapa?range=12m&status=active'", $contents);
        $this->assertStringContainsString("'/buscador-operacional?category=code&query=SYN-CONT'", $contents);
        $this->assertStringContainsString("'/contingencias/1/antecedentes-terreno'", $contents);
        $this->assertStringContainsString("Method = 'POST'", $contents);
        $this->assertStringContainsString("'/pronostico-meteorologico'", $contents);
        $this->assertStringContainsString("'/informes?range=12m'", $contents);
        $this->assertStringContainsString("'/informes/contingencias.csv?range=12m'", $contents);
        $this->assertStringContainsString("'/informes/contingencias.pdf?range=12m&report_type=executive'", $contents);
        $this->assertStringContainsString("GetEnvironmentVariable('LUZPARRAL_PERFORMANCE_PASSWORD')", $contents);
        $this->assertStringContainsString('La sesión autenticada no conservó la cookie CSRF.', $contents);
        $this->assertStringNotContainsString('password = \'', $contents);
    }

    public function test_integration_runner_can_execute_the_performance_measurement(): void
    {
        $contents = file_get_contents(base_path('deploy/VALIDAR_INTEGRACION_LOCAL.ps1'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('[switch]$RunPerformance', $contents);
        $this->assertStringContainsString("'MEDIR_RENDIMIENTO_LOCAL.ps1'", $contents);
        $this->assertStringContainsString('5, 10 y 30 sesiones concurrentes', $contents);
        $this->assertStringContainsString('Remove-Item Env:LUZPARRAL_PERFORMANCE_PASSWORD', $contents);
    }
}
