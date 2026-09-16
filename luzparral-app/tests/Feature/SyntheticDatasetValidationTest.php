<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SyntheticDatasetValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_synthetic_dataset_passes_all_integrity_checks(): void
    {
        $this->createSyntheticDataset();

        $this->artisan('luzparral:validate-synthetic', ['--require-runtime' => true])
            ->assertSuccessful();
    }

    public function test_inconsistent_affected_total_fails_validation(): void
    {
        $this->createSyntheticDataset();
        DB::table('contingencies')->update(['affected_total' => 2]);

        $this->artisan('luzparral:validate-synthetic')
            ->assertFailed();
    }

    private function createSyntheticDataset(): void
    {
        $timestamp = '2026-09-09 12:00:00';
        $user = User::factory()->create([
            'email' => 'validacion@luzparral.example.invalid',
            'role' => 'admin',
            'active' => true,
        ]);

        $communeId = DB::table('communes')->insertGetId([
            'code' => 'COM-TEST',
            'name' => 'COMUNA SINTETICA',
            'center_lat' => -36.14,
            'center_lon' => -71.82,
            'active' => true,
        ]);
        $feederId = DB::table('feeders')->insertGetId([
            'commune_id' => $communeId,
            'code' => 'SYN-AL-TEST',
            'name' => 'Alimentador sintético de validación',
            'active' => true,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $supplyPointId = DB::table('supply_points')->insertGetId([
            'synthetic_code' => 'SYN-SP-000001',
            'customer_code' => 'SYN-CL-000001',
            'commune_id' => $communeId,
            'feeder_id' => $feederId,
            'latitude' => -36.14,
            'longitude' => -71.82,
            'criticality' => 'normal',
            'active' => true,
            'installed_at' => '2024-01-01',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $batchId = DB::table('import_batches')->insertGetId([
            'source_name' => 'Fuente sintética',
            'synthetic_file_name' => 'SYN_VALIDACION.txt',
            'status' => 'completed_with_warnings',
            'total_rows' => 2,
            'accepted_rows' => 1,
            'rejected_rows' => 1,
            'started_at' => '2026-09-09 10:00:00',
            'completed_at' => '2026-09-09 10:05:00',
            'imported_by' => $user->id,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        DB::table('import_errors')->insert([
            'import_batch_id' => $batchId,
            'source_row_number' => 2,
            'field_name' => 'MINUTOS',
            'error_code' => 'NEGATIVE_MINUTES',
            'message' => 'Error sintético controlado',
            'synthetic_reference' => 'SYN-ROW-000002',
            'created_at' => $timestamp,
        ]);
        $contingencyId = DB::table('contingencies')->insertGetId([
            'code' => 'SYN-CONT-000001',
            'osf_code' => 'SYN-OSF-000001',
            'commune_id' => $communeId,
            'feeder_id' => $feederId,
            'source_batch_id' => $batchId,
            'status' => 'closed',
            'priority' => 'low',
            'cause' => 'synthetic_test',
            'description' => 'Contingencia sintética para validar integridad.',
            'started_at' => '2026-09-09 10:10:00',
            'estimated_restore_at' => '2026-09-09 11:00:00',
            'restored_at' => '2026-09-09 10:40:00',
            'latitude' => -36.14,
            'longitude' => -71.82,
            'affected_total' => 1,
            'critical_affected' => 0,
            'electrodependent_affected' => 0,
            'created_by' => $user->id,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        DB::table('contingency_impacts')->insert([
            'contingency_id' => $contingencyId,
            'supply_point_id' => $supplyPointId,
            'status' => 'restored',
            'affected_at' => '2026-09-09 10:10:00',
            'restored_at' => '2026-09-09 10:40:00',
            'outage_minutes' => 30,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        DB::table('contingency_history')->insert([
            'contingency_id' => $contingencyId,
            'status' => 'closed',
            'note' => 'Cierre sintético de validación.',
            'event_at' => '2026-09-09 10:45:00',
            'user_id' => $user->id,
            'source' => 'synthetic',
            'created_at' => $timestamp,
        ]);
        DB::table('field_reports')->insert([
            'contingency_id' => $contingencyId,
            'reported_by' => $user->id,
            'progress_status' => 'completed',
            'description' => 'Antecedente sintético para validar el conjunto de pruebas.',
            'observed_at' => '2026-09-09 10:40:00',
            'latitude' => -36.14,
            'longitude' => -71.82,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        DB::table('dataset_metadata')->insert([
            'dataset_key' => 'luzparral-synthetic-v1',
            'generator_version' => 'test',
            'random_seed' => 1,
            'generated_at' => $timestamp,
            'parameters_json' => json_encode([
                'users' => 1,
                'communes' => 1,
                'feeders' => 1,
                'supply_points' => 1,
                'import_batches' => 1,
                'contingencies' => 1,
                'impacts' => 1,
                'history_events' => 1,
                'field_reports' => 1,
            ], JSON_THROW_ON_ERROR),
            'notes' => 'Conjunto sintético mínimo para pruebas.',
        ]);
    }
}
