<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationalCopyCleanupMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_demo_records_receive_neutral_operational_copy(): void
    {
        $now = now();
        $user = User::factory()->create();
        $communeId = DB::table('communes')->insertGetId([
            'code' => 'COM-QA',
            'name' => 'PARRAL',
            'center_lat' => -36.14,
            'center_lon' => -71.83,
            'active' => true,
        ]);
        $feederId = DB::table('feeders')->insertGetId([
            'commune_id' => $communeId,
            'code' => 'SYN-AL-QA',
            'name' => 'Alimentador sintetico PARRAL 1',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $batchId = DB::table('import_batches')->insertGetId([
            'source_name' => 'PowerOn sintetico',
            'synthetic_file_name' => 'SYN_POWERON_QA.txt',
            'status' => 'completed_with_warnings',
            'total_rows' => 2,
            'accepted_rows' => 1,
            'rejected_rows' => 1,
            'started_at' => $now,
            'completed_at' => $now,
            'imported_by' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('import_errors')->insert([
            'import_batch_id' => $batchId,
            'source_row_number' => 2,
            'field_name' => 'MINUTOS',
            'error_code' => 'NEGATIVE_MINUTES',
            'message' => 'Duracion negativa detectada en registro sintetico',
            'synthetic_reference' => 'SYN-ROW-000002',
            'created_at' => $now,
        ]);
        $contingencyId = DB::table('contingencies')->insertGetId([
            'code' => 'SYN-CONT-QA',
            'osf_code' => 'SYN-OSF-QA',
            'commune_id' => $communeId,
            'feeder_id' => $feederId,
            'source_batch_id' => $batchId,
            'status' => 'reported',
            'priority' => 'medium',
            'cause' => 'equipment_failure',
            'description' => 'Contingencia sintetica SYN-CONT-QA; no representa un evento real.',
            'started_at' => $now,
            'estimated_restore_at' => $now->copy()->addHours(2),
            'restored_at' => null,
            'latitude' => -36.14,
            'longitude' => -71.83,
            'affected_total' => 1,
            'critical_affected' => 0,
            'electrodependent_affected' => 0,
            'created_by' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('contingency_history')->insert([
            'contingency_id' => $contingencyId,
            'status' => 'reported',
            'note' => 'Contingencia sintetica registrada por importacion.',
            'event_at' => $now,
            'user_id' => null,
            'source' => 'import',
            'created_at' => $now,
        ]);
        DB::table('field_reports')->insert([
            'contingency_id' => $contingencyId,
            'reported_by' => $user->id,
            'progress_status' => 'inspection',
            'description' => 'Inspeccion sintetica realizada; se verifican condiciones del sector y elementos de la red.',
            'observed_at' => $now,
            'latitude' => null,
            'longitude' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $migration = require database_path('migrations/2026_11_05_000600_remove_redundant_synthetic_copy_from_operational_data.php');
        $migration->up();

        $this->assertDatabaseHas('feeders', ['id' => $feederId, 'name' => 'Alimentador PARRAL 1']);
        $this->assertDatabaseHas('import_batches', ['id' => $batchId, 'source_name' => 'PowerOn']);
        $this->assertDatabaseHas('import_errors', ['import_batch_id' => $batchId, 'message' => 'Duración negativa detectada en el registro']);
        $this->assertDatabaseHas('contingencies', ['id' => $contingencyId, 'description' => 'Interrupción asociada a falla de equipamiento.']);
        $this->assertDatabaseHas('contingency_history', ['contingency_id' => $contingencyId, 'note' => 'Contingencia registrada por importación.']);
        $this->assertDatabaseHas('field_reports', ['contingency_id' => $contingencyId, 'description' => 'Inspección realizada; se verifican condiciones del sector y elementos de la red.']);
    }
}
