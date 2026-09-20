<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Commune;
use App\Models\Contingency;
use App\Models\Feeder;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContingencyImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'codigo;osf;comuna;alimentador;estado;prioridad;causa;descripcion;inicio;reposicion_estimada;reposicion_efectiva;latitud;longitud';

    public function test_import_module_requires_authentication(): void
    {
        $this->get(route('imports.index'))->assertRedirect('/login');
        $this->post(route('imports.preview'))->assertRedirect('/login');
        $this->post(route('imports.store'))->assertRedirect('/login');
    }

    public function test_only_admin_and_supervisor_can_use_the_import_module(): void
    {
        foreach ([UserRole::Admin, UserRole::Supervisor] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)
                ->get(route('imports.index'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Imports/Index')
                    ->where('auth.permissions.importContingencies', true));
        }

        foreach ([UserRole::Operator, UserRole::Viewer] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('imports.index'))->assertForbidden();
            $this->actingAs($user)->post(route('imports.preview'))->assertForbidden();
            $this->actingAs($user)->post(route('imports.store'))->assertForbidden();
        }
    }

    public function test_valid_file_can_be_previewed_without_writing_to_the_database(): void
    {
        $this->createTerritory();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $contents = $this->csv([
            $this->validRow('SYN-IMPORT-001', 'SYN-OSF-IMPORT-001'),
        ]);

        $this->actingAs($admin)
            ->postJson(route('imports.preview'), [
                'source' => 'synthetic_csv_v1',
                'file' => UploadedFile::fake()->createWithContent('lote.csv', $contents),
            ])
            ->assertOk()
            ->assertJson([
                'encoding' => 'UTF-8',
                'delimiter' => 'punto y coma',
                'total_rows' => 1,
                'accepted_rows' => 1,
                'rejected_rows' => 0,
                'can_import' => true,
            ])
            ->assertJsonPath('accepted_references.0', 'SYN-IMPORT-001');

        $this->assertDatabaseCount('import_batches', 0);
        $this->assertDatabaseCount('contingencies', 0);
    }

    public function test_mixed_file_imports_valid_rows_and_registers_rejections_atomically(): void
    {
        $this->createTerritory();
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $contents = $this->csv([
            $this->validRow('SYN-IMPORT-010', 'SYN-OSF-IMPORT-010'),
            $this->validRow('SYN-IMPORT-011', 'SYN-OSF-IMPORT-011', feeder: 'SYN-AL-NO-EXISTE'),
        ]);

        $this->actingAs($supervisor)
            ->post(route('imports.store'), [
                'source' => 'synthetic_csv_v1',
                'file' => UploadedFile::fake()->createWithContent('lote-mixto.csv', $contents),
                'checksum' => hash('sha256', $contents),
            ])
            ->assertRedirect()
            ->assertSessionHas(
                'success',
                'Importación IMP-000001 completada con observaciones. Filas incorporadas: 1; filas rechazadas: 1. Revise los motivos indicados.',
            );

        $batch = ImportBatch::query()->firstOrFail();
        $this->assertSame('completed_with_warnings', $batch->status);
        $this->assertSame(2, $batch->total_rows);
        $this->assertSame(1, $batch->accepted_rows);
        $this->assertSame(1, $batch->rejected_rows);
        $this->assertDatabaseHas('import_errors', [
            'import_batch_id' => $batch->id,
            'source_row_number' => 3,
            'field_name' => 'alimentador',
            'error_code' => 'UNKNOWN_FEEDER',
            'synthetic_reference' => 'SYN-IMPORT-011',
        ]);
        $this->assertDatabaseHas('contingencies', [
            'code' => 'SYN-IMPORT-010',
            'source_batch_id' => $batch->id,
            'created_by' => $supervisor->id,
        ]);
        $this->assertDatabaseHas('contingency_history', [
            'contingency_id' => Contingency::query()->where('code', 'SYN-IMPORT-010')->value('id'),
            'source' => 'import',
            'user_id' => $supervisor->id,
        ]);

        $this->actingAs($supervisor)
            ->get(route('imports.index', ['batch' => $batch->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedBatch.reference', 'IMP-000001')
                ->where('selectedBatch.accepted_rows', 1)
                ->where('selectedBatch.rejected_rows', 1)
                ->where('selectedBatch.errors.0.code', 'UNKNOWN_FEEDER'));
    }

    public function test_existing_codes_are_rejected_without_creating_duplicates(): void
    {
        [$commune, $feeder] = $this->createTerritory();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Contingency::query()->create([
            'code' => 'SYN-IMPORT-020',
            'osf_code' => 'SYN-OSF-IMPORT-020',
            'commune_id' => $commune->id,
            'feeder_id' => $feeder->id,
            'status' => 'reported',
            'priority' => 'medium',
            'cause' => 'unknown',
            'description' => 'Contingencia sintética preexistente para validar duplicados.',
            'started_at' => '2026-09-16 08:00:00',
            'latitude' => -36.143,
            'longitude' => -71.826,
        ]);
        $contents = $this->csv([
            $this->validRow('SYN-IMPORT-020', 'SYN-OSF-IMPORT-020'),
        ]);

        $this->actingAs($admin)
            ->post(route('imports.store'), [
                'source' => 'synthetic_csv_v1',
                'file' => UploadedFile::fake()->createWithContent('duplicado.csv', $contents),
                'checksum' => hash('sha256', $contents),
            ])
            ->assertRedirect();

        $batch = ImportBatch::query()->firstOrFail();
        $this->assertSame('rejected', $batch->status);
        $this->assertSame(0, $batch->accepted_rows);
        $this->assertSame(1, $batch->rejected_rows);
        $this->assertDatabaseCount('contingencies', 1);
        $this->assertDatabaseHas('import_errors', ['error_code' => 'DUPLICATE_DATABASE', 'field_name' => 'codigo']);
        $this->assertDatabaseHas('import_errors', ['error_code' => 'DUPLICATE_DATABASE', 'field_name' => 'osf']);
    }

    public function test_changed_file_is_rejected_before_any_batch_is_written(): void
    {
        $this->createTerritory();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $contents = $this->csv([$this->validRow('SYN-IMPORT-030', 'SYN-OSF-IMPORT-030')]);

        $this->actingAs($admin)
            ->post(route('imports.store'), [
                'source' => 'synthetic_csv_v1',
                'file' => UploadedFile::fake()->createWithContent('cambiado.csv', $contents),
                'checksum' => str_repeat('0', 64),
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('import_batches', 0);
        $this->assertDatabaseCount('contingencies', 0);
    }

    public function test_unexpected_database_failure_rolls_back_the_complete_import(): void
    {
        $this->createTerritory();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $contents = $this->csv([$this->validRow('SYN-IMPORT-035', 'SYN-OSF-IMPORT-035')]);

        DB::statement(<<<'SQL'
            CREATE TRIGGER fail_import_history
            BEFORE INSERT ON contingency_history
            WHEN NEW.source = 'import'
            BEGIN
                SELECT RAISE(ABORT, 'forced import failure');
            END
        SQL);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($admin)->post(route('imports.store'), [
                'source' => 'synthetic_csv_v1',
                'file' => UploadedFile::fake()->createWithContent('fallo.csv', $contents),
                'checksum' => hash('sha256', $contents),
            ]);

            $this->fail('La importación debía fallar al registrar la trazabilidad.');
        } catch (QueryException) {
            $this->assertDatabaseCount('import_batches', 0);
            $this->assertDatabaseCount('import_errors', 0);
            $this->assertDatabaseCount('contingencies', 0);
            $this->assertDatabaseCount('contingency_history', 0);
        }
    }

    public function test_invalid_header_is_fatal_and_windows_1252_is_supported(): void
    {
        $this->createTerritory();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->postJson(route('imports.preview'), [
                'source' => 'synthetic_csv_v1',
                'file' => UploadedFile::fake()->createWithContent('invalido.csv', "codigo;osf\nSYN-1;SYN-2\n"),
            ])
            ->assertOk()
            ->assertJsonPath('can_import', false)
            ->assertJsonPath('errors.0.error_code', 'INVALID_HEADER');

        $utf8 = $this->csv([
            $this->validRow('SYN-IMPORT-040', 'SYN-OSF-IMPORT-040', description: 'Contingencia sintética con acentuación válida.'),
        ]);
        $windows1252 = mb_convert_encoding($utf8, 'Windows-1252', 'UTF-8');

        $this->actingAs($admin)
            ->postJson(route('imports.preview'), [
                'source' => 'synthetic_csv_v1',
                'file' => UploadedFile::fake()->createWithContent('cp1252.csv', $windows1252),
            ])
            ->assertOk()
            ->assertJsonPath('encoding', 'Windows-1252')
            ->assertJsonPath('accepted_rows', 1);
    }

    public function test_template_is_an_excel_compatible_download(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('imports.template'))
            ->assertOk()
            ->assertDownload('plantilla_importacion_sigcel.csv')
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    /** @return array{Commune, Feeder} */
    private function createTerritory(): array
    {
        $commune = Commune::query()->create([
            'code' => 'COM-01',
            'name' => 'PARRAL SINTÉTICO',
            'center_lat' => -36.143,
            'center_lon' => -71.826,
            'active' => true,
        ]);
        $feeder = Feeder::query()->create([
            'commune_id' => $commune->id,
            'code' => 'SYN-AL-01',
            'name' => 'Alimentador sintético de importación',
            'active' => true,
        ]);

        return [$commune, $feeder];
    }

    /** @param list<string> $rows */
    private function csv(array $rows): string
    {
        return self::HEADER."\r\n".implode("\r\n", $rows)."\r\n";
    }

    private function validRow(
        string $code,
        string $osf,
        string $feeder = 'SYN-AL-01',
        string $description = 'Contingencia sintética válida para importar.',
    ): string {
        return implode(';', [
            $code,
            $osf,
            'COM-01',
            $feeder,
            'reported',
            'medium',
            'unknown',
            $description,
            '2026-09-16 08:00:00',
            '2026-09-16 12:00:00',
            '',
            '-36.1430000',
            '-71.8260000',
        ]);
    }
}
