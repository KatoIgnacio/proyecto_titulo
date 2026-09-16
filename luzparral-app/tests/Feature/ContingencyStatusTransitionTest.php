<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Commune;
use App\Models\Contingency;
use App\Models\Feeder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContingencyStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_admin_supervisor_and_operator_can_register_an_allowed_transition(): void
    {
        foreach ([UserRole::Admin, UserRole::Supervisor, UserRole::Operator] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $contingency = $this->createContingency('reported');

            $this->actingAs($user)
                ->patch(route('contingencies.status.update', $contingency), [
                    'current_status' => 'reported',
                    'status' => 'assigned',
                    'note' => 'Asignación sintética confirmada para la prueba.',
                ])
                ->assertRedirect()
                ->assertSessionHas('success');

            $this->assertDatabaseHas('contingencies', [
                'id' => $contingency->id,
                'status' => 'assigned',
            ]);
            $this->assertDatabaseHas('contingency_history', [
                'contingency_id' => $contingency->id,
                'status' => 'assigned',
                'user_id' => $user->id,
                'source' => 'manual',
            ]);
        }
    }

    public function test_viewer_cannot_register_a_transition(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $contingency = $this->createContingency('reported');

        $this->actingAs($viewer)
            ->patch(route('contingencies.status.update', $contingency), [
                'current_status' => 'reported',
                'status' => 'assigned',
                'note' => 'Intento sintético que debe ser rechazado.',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('contingencies', [
            'id' => $contingency->id,
            'status' => 'reported',
        ]);
        $this->assertDatabaseCount('contingency_history', 0);
    }

    public function test_transition_must_follow_the_defined_state_sequence(): void
    {
        $operator = User::factory()->create(['role' => UserRole::Operator]);
        $contingency = $this->createContingency('reported');

        $this->actingAs($operator)
            ->patch(route('contingencies.status.update', $contingency), [
                'current_status' => 'reported',
                'status' => 'closed',
                'note' => 'Cierre directo que no corresponde al flujo.',
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('contingencies', [
            'id' => $contingency->id,
            'status' => 'reported',
        ]);
        $this->assertDatabaseCount('contingency_history', 0);
    }

    public function test_stale_form_cannot_overwrite_a_more_recent_transition(): void
    {
        $operator = User::factory()->create(['role' => UserRole::Operator]);
        $contingency = $this->createContingency('assigned');

        $this->actingAs($operator)
            ->patch(route('contingencies.status.update', $contingency), [
                'current_status' => 'reported',
                'status' => 'in_progress',
                'note' => 'Formulario abierto antes de otra actualización.',
            ])
            ->assertSessionHasErrors('current_status');

        $this->assertDatabaseHas('contingencies', [
            'id' => $contingency->id,
            'status' => 'assigned',
        ]);
        $this->assertDatabaseCount('contingency_history', 0);
    }

    public function test_restoration_records_the_effective_time_and_closing_preserves_it(): void
    {
        CarbonImmutable::setTestNow('2026-09-16 12:30:00');

        $operator = User::factory()->create(['role' => UserRole::Operator]);
        $contingency = $this->createContingency('in_progress');

        $this->actingAs($operator)
            ->patch(route('contingencies.status.update', $contingency), [
                'current_status' => 'in_progress',
                'status' => 'restored',
                'note' => 'Suministro sintético repuesto y verificado.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('contingencies', [
            'id' => $contingency->id,
            'status' => 'restored',
            'restored_at' => '2026-09-16 12:30:00',
        ]);

        CarbonImmutable::setTestNow('2026-09-16 13:00:00');

        $this->actingAs($operator)
            ->patch(route('contingencies.status.update', $contingency), [
                'current_status' => 'restored',
                'status' => 'closed',
                'note' => 'Expediente sintético revisado y cerrado.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('contingencies', [
            'id' => $contingency->id,
            'status' => 'closed',
            'restored_at' => '2026-09-16 12:30:00',
        ]);
        $this->assertDatabaseCount('contingency_history', 2);
    }

    public function test_detail_exposes_only_the_next_transition_and_the_role_permission(): void
    {
        $operator = User::factory()->create(['role' => UserRole::Operator]);
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $contingency = $this->createContingency('assigned');

        $this->actingAs($operator)
            ->get(route('contingencies.show', $contingency))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions.updateContingencies', true)
                ->has('availableStatusTransitions', 1)
                ->where('availableStatusTransitions.0.value', 'in_progress')
                ->where('availableStatusTransitions.0.label', 'En atención'));

        $this->actingAs($viewer)
            ->get(route('contingencies.show', $contingency))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions.updateContingencies', false)
                ->where('availableStatusTransitions.0.value', 'in_progress'));
    }

    private function createContingency(string $status): Contingency
    {
        $this->sequence++;
        $suffix = str_pad((string) $this->sequence, 3, '0', STR_PAD_LEFT);
        $commune = Commune::query()->create([
            'code' => 'TRACE-'.$suffix,
            'name' => 'COMUNA TRAZABILIDAD '.$suffix,
            'center_lat' => -36.1400000,
            'center_lon' => -71.8200000,
            'active' => true,
        ]);
        $feeder = Feeder::query()->create([
            'commune_id' => $commune->id,
            'code' => 'ALIM-TRACE-'.$suffix,
            'name' => 'Alimentador trazabilidad '.$suffix,
            'active' => true,
        ]);

        return Contingency::query()->create([
            'code' => 'CONT-TRACE-'.$suffix,
            'osf_code' => 'OSF-TRACE-'.$suffix,
            'commune_id' => $commune->id,
            'feeder_id' => $feeder->id,
            'status' => $status,
            'priority' => 'medium',
            'cause' => 'unknown',
            'description' => 'Contingencia completamente sintética para validar la trazabilidad.',
            'started_at' => '2026-09-09 10:00:00',
            'latitude' => -36.1400000,
            'longitude' => -71.8200000,
            'affected_total' => 10,
            'critical_affected' => 0,
            'electrodependent_affected' => 0,
        ]);
    }
}
