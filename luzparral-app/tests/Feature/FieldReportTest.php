<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Commune;
use App\Models\Contingency;
use App\Models\Feeder;
use App\Models\FieldReport;
use App\Models\FieldReportAttachment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FieldReportTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-09-16 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_field_report_requires_authentication(): void
    {
        $contingency = $this->createContingency();

        $this->post(route('contingencies.field-reports.store', $contingency), [
            'progress_status' => 'inspection',
            'description' => 'Inspección sintética sin sesión autenticada.',
            'observed_at' => '2026-09-16 10:00:00',
        ])->assertRedirect('/login');
    }

    public function test_authorized_roles_can_register_a_field_report_and_traceability_event(): void
    {
        foreach ([UserRole::Admin, UserRole::Supervisor, UserRole::Operator] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $contingency = $this->createContingency();

            $this->actingAs($user)
                ->post(route('contingencies.field-reports.store', $contingency), [
                    'progress_status' => 'repair',
                    'description' => 'Brigada sintética informa avance de reparación en terreno.',
                    'observed_at' => '2026-09-16 10:00:00',
                    'latitude' => -36.1401,
                    'longitude' => -71.8201,
                ])
                ->assertRedirect()
                ->assertSessionHas('success');

            $this->assertDatabaseHas('field_reports', [
                'contingency_id' => $contingency->id,
                'reported_by' => $user->id,
                'progress_status' => 'repair',
            ]);
            $this->assertDatabaseHas('contingency_history', [
                'contingency_id' => $contingency->id,
                'user_id' => $user->id,
                'source' => 'manual',
            ]);
        }
    }

    public function test_viewer_cannot_register_a_field_report(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $contingency = $this->createContingency();

        $this->actingAs($viewer)
            ->post(route('contingencies.field-reports.store', $contingency), [
                'progress_status' => 'inspection',
                'description' => 'Intento sintético que debe ser rechazado por permisos.',
                'observed_at' => '2026-09-16 10:00:00',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('field_reports', 0);
        $this->assertDatabaseCount('contingency_history', 0);
    }

    public function test_location_requires_both_coordinates_and_observation_must_follow_the_contingency(): void
    {
        $operator = User::factory()->create(['role' => UserRole::Operator]);
        $contingency = $this->createContingency();

        $this->actingAs($operator)
            ->post(route('contingencies.field-reports.store', $contingency), [
                'progress_status' => 'inspection',
                'description' => 'Antecedente sintético con ubicación incompleta y fecha inválida.',
                'observed_at' => '2026-09-08 10:00:00',
                'latitude' => -36.1401,
            ])
            ->assertSessionHasErrors(['observed_at', 'longitude']);

        $this->assertDatabaseCount('field_reports', 0);
    }

    public function test_allowed_evidence_is_private_downloadable_and_exposed_without_internal_path(): void
    {
        Storage::fake('local');
        $operator = User::factory()->create(['role' => UserRole::Operator]);
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $contingency = $this->createContingency();
        $png = UploadedFile::fake()->createWithContent(
            'avance.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z4gAAAABJRU5ErkJggg==', true),
        );

        $this->actingAs($operator)
            ->post(route('contingencies.field-reports.store', $contingency), [
                'progress_status' => 'repair',
                'description' => 'Evidencia sintética del avance de reparación en terreno.',
                'observed_at' => '2026-09-16 10:00:00',
                'attachments' => [$png],
            ])
            ->assertRedirect();

        $attachment = FieldReportAttachment::query()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertSame(64, strlen($attachment->checksum_sha256));

        $this->actingAs($viewer)
            ->get(route('field-reports.attachments.download', $attachment))
            ->assertOk()
            ->assertDownload('avance.png');

        $this->actingAs($viewer)
            ->get(route('contingencies.show', $contingency))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions.registerFieldReports', false)
                ->where('auth.permissions.manageFieldReports', false)
                ->has('fieldReports', 1)
                ->where('fieldReports.0.progress_status', 'repair')
                ->where('fieldReports.0.attachments.0.name', 'avance.png')
                ->missing('fieldReports.0.attachments.0.path')
                ->missing('fieldReports.0.attachments.0.checksum_sha256'));
    }

    public function test_disallowed_evidence_type_is_rejected(): void
    {
        Storage::fake('local');
        $operator = User::factory()->create(['role' => UserRole::Operator]);
        $contingency = $this->createContingency();

        $this->actingAs($operator)
            ->post(route('contingencies.field-reports.store', $contingency), [
                'progress_status' => 'inspection',
                'description' => 'Antecedente sintético con una evidencia no permitida.',
                'observed_at' => '2026-09-16 10:00:00',
                'attachments' => [UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream')],
            ])
            ->assertSessionHasErrors('attachments.0');

        $this->assertDatabaseCount('field_reports', 0);
        $this->assertDatabaseCount('field_report_attachments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_only_admin_can_manage_existing_field_reports(): void
    {
        foreach ([UserRole::Supervisor, UserRole::Operator, UserRole::Viewer] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $contingency = $this->createContingency();
            $report = $this->createFieldReport($contingency, $user);

            $this->actingAs($user)
                ->patch(route('contingencies.field-reports.update', [$contingency, $report]), [
                    'current_updated_at' => $report->updated_at->toIso8601String(),
                    'progress_status' => 'repair',
                    'description' => 'Intento de edición que debe ser rechazado por permisos.',
                    'observed_at' => '2026-09-16 10:30:00',
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->delete(route('contingencies.field-reports.destroy', [$contingency, $report]), [
                    'confirmation' => true,
                ])
                ->assertForbidden();

            $this->assertDatabaseHas('field_reports', [
                'id' => $report->id,
                'description' => $report->description,
            ]);
        }
    }

    public function test_admin_can_edit_a_field_report_and_preserve_its_evidence(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $operator = User::factory()->create(['role' => UserRole::Operator]);
        $contingency = $this->createContingency();
        $report = $this->createFieldReport($contingency, $operator);
        $path = "field-reports/{$contingency->id}/{$report->id}/evidencia.pdf";
        Storage::disk('local')->put($path, 'evidencia sintética');
        $attachment = $report->attachments()->create([
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'evidencia.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 19,
            'checksum_sha256' => hash('sha256', 'evidencia sintética'),
        ]);

        $this->actingAs($admin)
            ->patch(route('contingencies.field-reports.update', [$contingency, $report]), [
                'current_updated_at' => $report->updated_at->toIso8601String(),
                'progress_status' => 'repair',
                'description' => 'Brigada informa reparación avanzada y pruebas operacionales satisfactorias.',
                'observed_at' => '2026-09-16 11:00:00',
                'latitude' => -36.1501,
                'longitude' => -71.8301,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('field_reports', [
            'id' => $report->id,
            'reported_by' => $operator->id,
            'progress_status' => 'repair',
            'description' => 'Brigada informa reparación avanzada y pruebas operacionales satisfactorias.',
        ]);
        $this->assertDatabaseHas('field_report_attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertExists($path);

        $history = $contingency->history()->latest('id')->firstOrFail();
        $this->assertSame($admin->id, $history->user_id);
        $this->assertStringContainsString("Antecedente de terreno #{$report->id} editado", $history->note);
    }

    public function test_stale_field_report_edit_cannot_overwrite_a_newer_change(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $operator = User::factory()->create(['role' => UserRole::Operator]);
        $contingency = $this->createContingency();
        $report = $this->createFieldReport($contingency, $operator);
        $staleVersion = $report->updated_at->toIso8601String();

        CarbonImmutable::setTestNow('2026-09-16 12:05:00');
        $report->update(['description' => 'Cambio más reciente ya registrado por otro proceso.']);

        $this->actingAs($admin)
            ->patch(route('contingencies.field-reports.update', [$contingency, $report]), [
                'current_updated_at' => $staleVersion,
                'progress_status' => 'completed',
                'description' => 'Formulario antiguo que no debe sobrescribir el cambio reciente.',
                'observed_at' => '2026-09-16 11:00:00',
            ])
            ->assertSessionHasErrors('current_updated_at');

        $this->assertDatabaseHas('field_reports', [
            'id' => $report->id,
            'description' => 'Cambio más reciente ya registrado por otro proceso.',
            'progress_status' => 'inspection',
        ]);
        $this->assertDatabaseCount('contingency_history', 0);
    }

    public function test_admin_can_delete_a_field_report_with_its_evidence_and_keep_an_audit_event(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $operator = User::factory()->create(['role' => UserRole::Operator]);
        $contingency = $this->createContingency();
        $report = $this->createFieldReport($contingency, $operator);
        $path = "field-reports/{$contingency->id}/{$report->id}/evidencia.png";
        Storage::disk('local')->put($path, 'imagen sintética');
        $attachment = $report->attachments()->create([
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'evidencia.png',
            'mime_type' => 'image/png',
            'size_bytes' => 16,
            'checksum_sha256' => hash('sha256', 'imagen sintética'),
        ]);

        $this->actingAs($admin)
            ->delete(route('contingencies.field-reports.destroy', [$contingency, $report]), [
                'confirmation' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('field_reports', ['id' => $report->id]);
        $this->assertDatabaseMissing('field_report_attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($path);

        $history = $contingency->history()->latest('id')->firstOrFail();
        $this->assertSame($admin->id, $history->user_id);
        $this->assertStringContainsString("Antecedente de terreno #{$report->id} eliminado", $history->note);
    }

    public function test_deletion_requires_confirmation_and_nested_report_must_match_contingency(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $operator = User::factory()->create(['role' => UserRole::Operator]);
        $contingency = $this->createContingency();
        $otherContingency = $this->createContingency();
        $report = $this->createFieldReport($contingency, $operator);

        $this->actingAs($admin)
            ->delete(route('contingencies.field-reports.destroy', [$contingency, $report]))
            ->assertSessionHasErrors('confirmation');

        $this->actingAs($admin)
            ->delete(route('contingencies.field-reports.destroy', [$otherContingency, $report]), [
                'confirmation' => true,
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('field_reports', ['id' => $report->id]);
        $this->assertDatabaseCount('contingency_history', 0);
    }

    public function test_detail_exposes_field_report_management_only_to_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $contingency = $this->createContingency();

        $this->actingAs($admin)
            ->get(route('contingencies.show', $contingency))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions.manageFieldReports', true));

        $this->actingAs($supervisor)
            ->get(route('contingencies.show', $contingency))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions.registerFieldReports', true)
                ->where('auth.permissions.manageFieldReports', false));
    }

    private function createContingency(): Contingency
    {
        $this->sequence++;
        $suffix = str_pad((string) $this->sequence, 3, '0', STR_PAD_LEFT);
        $commune = Commune::query()->create([
            'code' => 'FIELD-'.$suffix,
            'name' => 'COMUNA TERRENO '.$suffix,
            'center_lat' => -36.1400000,
            'center_lon' => -71.8200000,
            'active' => true,
        ]);
        $feeder = Feeder::query()->create([
            'commune_id' => $commune->id,
            'code' => 'ALIM-FIELD-'.$suffix,
            'name' => 'Alimentador terreno '.$suffix,
            'active' => true,
        ]);

        return Contingency::query()->create([
            'code' => 'CONT-FIELD-'.$suffix,
            'osf_code' => 'OSF-FIELD-'.$suffix,
            'commune_id' => $commune->id,
            'feeder_id' => $feeder->id,
            'status' => 'in_progress',
            'priority' => 'medium',
            'cause' => 'unknown',
            'description' => 'Contingencia sintética para reportes de terreno.',
            'started_at' => '2026-09-09 10:00:00',
            'latitude' => -36.1400000,
            'longitude' => -71.8200000,
            'affected_total' => 10,
            'critical_affected' => 0,
            'electrodependent_affected' => 0,
        ]);
    }

    private function createFieldReport(Contingency $contingency, User $reporter): FieldReport
    {
        return $contingency->fieldReports()->create([
            'reported_by' => $reporter->id,
            'progress_status' => 'inspection',
            'description' => 'Inspección sintética registrada inicialmente en terreno.',
            'observed_at' => '2026-09-16 10:00:00',
            'latitude' => -36.1401,
            'longitude' => -71.8201,
        ]);
    }
}
