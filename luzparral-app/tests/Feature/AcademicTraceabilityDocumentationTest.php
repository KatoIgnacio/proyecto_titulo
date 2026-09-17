<?php

namespace Tests\Feature;

use Tests\TestCase;

class AcademicTraceabilityDocumentationTest extends TestCase
{
    public function test_traceability_matrix_covers_every_approved_requirement_and_story(): void
    {
        $contents = $this->document('MATRIZ_TRAZABILIDAD_ACADEMICA.md');

        $this->assertStringContainsString('Objetivo general', $contents);

        foreach (range(1, 4) as $number) {
            $this->assertStringContainsString('OE'.$number, $contents);
        }

        foreach (range(1, 9) as $number) {
            $identifier = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $this->assertStringContainsString('RF'.$identifier, $contents);
            $this->assertStringContainsString('HU'.$identifier, $contents);
        }

        foreach (range(1, 7) as $number) {
            $identifier = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $this->assertStringContainsString('RNF'.$identifier, $contents);
        }

        $this->assertStringContainsString('392,45 ms', $contents);
        $this->assertStringContainsString('resultados reales pendientes', $contents);
    }

    public function test_user_validation_protocol_defines_the_metric_and_protects_participants(): void
    {
        $contents = $this->document('VALIDACION_USUARIOS.md');

        foreach (range(1, 9) as $number) {
            $identifier = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $this->assertStringContainsString('T'.$identifier, $contents);
        }

        $this->assertStringContainsString('80 %', $contents);
        $this->assertStringContainsString('sin asistencia directa', $contents);
        $this->assertStringContainsString('consentimiento', $contents);
        $this->assertStringContainsString('Reuniones grabadas', $contents);
        $this->assertStringContainsString('Correo electrónico', $contents);
        $this->assertStringContainsString('comentarios informales', $contents);
        $this->assertStringContainsString('P01', $contents);
        $this->assertStringContainsString('pendientes de validación real', $contents);
    }

    public function test_architecture_and_final_inventory_distinguish_local_evidence_from_external_work(): void
    {
        $architecture = $this->document('ARQUITECTURA_MODELO_ACTUAL.md');
        $inventory = $this->document('CATASTRO_CUMPLIMIENTO_ANTEPROYECTO.md');

        $this->assertStringContainsString('flowchart LR', $architecture);
        $this->assertStringContainsString('erDiagram', $architecture);
        $this->assertStringContainsString('CONTINGENCIES', $architecture);
        $this->assertStringContainsString('CONTINGENCY_HISTORY', $architecture);
        $this->assertStringContainsString('FIELD_REPORT_ATTACHMENTS', $architecture);
        $this->assertStringContainsString('IMPORT_BATCHES', $architecture);

        $this->assertStringContainsString('núcleo funcional y requisitos técnicos verificados en local', $inventory);
        $this->assertStringContainsString('evaluación empírica con usuarios', $inventory);
        $this->assertStringContainsString('staging institucional', $inventory);
        $this->assertStringContainsString('datos sintéticos', $inventory);
    }

    public function test_validation_templates_have_no_participant_examples_or_personal_data(): void
    {
        $results = file_get_contents(base_path('docs/templates/validacion_usuarios_resultados.csv'));
        $feedback = file_get_contents(base_path('docs/templates/retroalimentacion.csv'));

        $this->assertSame(
            "participant_code,role,task_code,completed_without_assistance,completed_with_assistance,not_completed,elapsed_seconds,observation_code,evidence_reference\n",
            str_replace("\r\n", "\n", $results),
        );
        $this->assertSame(
            "feedback_code,participant_code,channel,date,context,observation,decision,status,evidence_reference\n",
            str_replace("\r\n", "\n", $feedback),
        );
    }

    private function document(string $name): string
    {
        $contents = file_get_contents(base_path('docs/'.$name));

        $this->assertIsString($contents);

        return preg_replace('/\s+/', ' ', $contents) ?? $contents;
    }
}
