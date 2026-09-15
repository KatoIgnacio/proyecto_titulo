<?php

namespace Tests\Feature;

use Monolog\Formatter\JsonFormatter;
use Tests\TestCase;

class MaintainabilityAndDiagnosticsTest extends TestCase
{
    public function test_report_controller_delegates_data_and_chart_responsibilities(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/ContingencyReportController.php'));
        $reportData = file_get_contents(base_path('app/Services/Reports/ContingencyReportData.php'));

        $this->assertIsString($controller);
        $this->assertIsString($reportData);
        $this->assertLessThanOrEqual(220, substr_count($controller, "\n") + 1);
        $this->assertStringContainsString('ContingencyReportData', $controller);
        $this->assertStringContainsString('TrendChartRenderer', $reportData);
        $this->assertStringNotContainsString('DB::', $controller);
        $this->assertFileExists(base_path('app/Services/Reports/ContingencyReportData.php'));
        $this->assertFileExists(base_path('app/Services/Reports/TrendChartRenderer.php'));
    }

    public function test_container_logging_is_structured_and_sent_to_standard_error(): void
    {
        $logging = config('logging.channels.stderr_json');

        $this->assertSame('monolog', $logging['driver']);
        $this->assertSame('php://stderr', $logging['with']['stream']);
        $this->assertSame(JsonFormatter::class, $logging['formatter']);
    }

    public function test_external_dependencies_and_operational_diagnostics_are_documented(): void
    {
        $dependencies = file_get_contents(base_path('docs/DEPENDENCIAS_EXTERNAS.md'));
        $diagnostics = file_get_contents(base_path('docs/DIAGNOSTICO_OPERATIVO.md'));
        $layout = file_get_contents(resource_path('views/app.blade.php'));

        $this->assertIsString($dependencies);
        $this->assertIsString($diagnostics);
        $this->assertIsString($layout);
        $this->assertStringContainsString('tile.openstreetmap.org', $dependencies);
        $this->assertStringContainsString('CIOP_DATA', $dependencies);
        $this->assertStringContainsString('luzparral:health --database --json', $diagnostics);
        $this->assertStringContainsString('LOG_CHANNEL=stderr_json', $diagnostics);
        $this->assertStringNotContainsString('fonts.bunny.net', $layout);
    }
}
