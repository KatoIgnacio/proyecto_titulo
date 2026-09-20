<?php

namespace Tests\Feature;

use Tests\TestCase;

class OperationalInterfaceContentTest extends TestCase
{
    public function test_redundant_privacy_copy_is_removed_from_the_operational_interface(): void
    {
        $files = [
            'resources/js/Layouts/AuthenticatedLayout.tsx',
            'resources/js/Pages/Dashboard.tsx',
            'resources/js/Pages/Contingencies/Map.tsx',
            'resources/js/Pages/Contingencies/Show.tsx',
            'resources/js/Pages/Reports/Index.tsx',
        ];

        $content = implode("\n", array_map(
            fn (string $file): string => (string) file_get_contents(base_path($file)),
            $files,
        ));

        $removedMessages = [
            'Resguardo ético',
            'Datos sintéticos y anonimizados para fines académicos.',
            'conteo agregado y anonimizado',
            'Las capas críticas y electrodependientes muestran zonas agregadas',
            'Expediente sintético',
            'Expediente operacional sintético',
            'Conteo agregado de instalaciones prioritarias',
            'Información anonimizada y agregada',
            'Resumen agregado de los puntos de suministro relacionados',
            'La selección usa los filtros aplicados y no cambia los datos del sistema.',
        ];

        foreach ($removedMessages as $message) {
            $this->assertStringNotContainsString($message, $content);
        }
    }

    public function test_traceability_is_kept_in_the_expedient_and_reports_instead_of_the_dashboard(): void
    {
        $dashboard = (string) file_get_contents(base_path('resources/js/Pages/Dashboard.tsx'));
        $expedient = (string) file_get_contents(base_path('resources/js/Pages/Contingencies/Show.tsx'));
        $reports = (string) file_get_contents(base_path('resources/js/Pages/Reports/Index.tsx'));
        $pdf = (string) file_get_contents(base_path('resources/views/reports/contingencies-pdf.blade.php'));

        $this->assertStringNotContainsString('trazabilidad', mb_strtolower($dashboard));
        $this->assertStringContainsString('Trazabilidad de origen', $expedient);
        $this->assertStringContainsString('trazabilidad histórica', $expedient);
        $this->assertStringContainsString('trazabilidad', mb_strtolower($reports));
        $this->assertStringContainsString('Los valores presentados son sintéticos y agregados.', $pdf);
    }

    public function test_import_results_explain_partial_rejections_and_synthetic_test_cases(): void
    {
        $imports = (string) file_get_contents(base_path('resources/js/Pages/Imports/Index.tsx'));

        $this->assertStringContainsString('las filas válidas se incorporaron y las rechazadas no se agregaron', $imports);
        $this->assertStringContainsString('Ver detalle', $imports);
        $this->assertStringContainsString("UNKNOWN_FEEDER: 'Alimentador no registrado'", $imports);
        $this->assertStringContainsString('los rechazos son casos de prueba deliberados', $imports);
        $this->assertStringContainsString('no provienen de registros reales', $imports);
    }
}
