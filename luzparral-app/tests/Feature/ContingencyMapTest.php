<?php

namespace Tests\Feature;

use App\Models\Commune;
use App\Models\Contingency;
use App\Models\Feeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContingencyMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_map_requires_authentication(): void
    {
        $this->get('/contingencias/mapa')->assertRedirect('/login');
    }

    public function test_map_displays_active_synthetic_contingencies_by_default(): void
    {
        $user = User::factory()->create();
        [$commune, $feeder] = $this->createLocation();

        $this->createContingency($commune->id, $feeder->id, [
            'code' => 'CONT-MAP-ACTIVE',
            'osf_code' => 'OSF-MAP-ACTIVE',
            'status' => 'in_progress',
            'affected_total' => 30,
            'critical_affected' => 3,
            'electrodependent_affected' => 1,
        ]);
        $this->createContingency($commune->id, $feeder->id, [
            'code' => 'CONT-MAP-CLOSED',
            'osf_code' => 'OSF-MAP-CLOSED',
            'status' => 'closed',
            'restored_at' => '2026-09-09 12:00:00',
        ]);

        $this->actingAs($user)
            ->get('/contingencias/mapa')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contingencies/Map')
                ->where('filters.status', 'active')
                ->where('summary.events', 1)
                ->where('summary.affected', 30)
                ->where('summary.critical', 3)
                ->where('summary.electrodependent', 1)
                ->has('contingencies', 1)
                ->where('contingencies.0.code', 'CONT-MAP-ACTIVE')
                ->where('contingencies.0.latitude', -36.14)
                ->where('contingencies.0.longitude', -71.82));
    }

    public function test_map_can_filter_closed_contingencies(): void
    {
        $user = User::factory()->create();
        [$commune, $feeder] = $this->createLocation();

        $this->createContingency($commune->id, $feeder->id, [
            'code' => 'CONT-MAP-CLOSED',
            'osf_code' => 'OSF-MAP-CLOSED',
            'status' => 'closed',
            'restored_at' => '2026-09-09 12:00:00',
        ]);

        $this->actingAs($user)
            ->get('/contingencias/mapa?status=closed')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'closed')
                ->where('summary.events', 1)
                ->where('contingencies.0.status', 'closed'));
    }

    /** @return array{Commune, Feeder} */
    private function createLocation(): array
    {
        $commune = Commune::query()->create([
            'code' => 'MAP-TEST',
            'name' => 'COMUNA MAPA SINTETICA',
            'center_lat' => -36.1400000,
            'center_lon' => -71.8200000,
            'active' => true,
        ]);
        $feeder = Feeder::query()->create([
            'commune_id' => $commune->id,
            'code' => 'ALIM-MAP-01',
            'name' => 'Alimentador mapa sintético',
            'active' => true,
        ]);

        return [$commune, $feeder];
    }

    /** @param array<string, mixed> $overrides */
    private function createContingency(int $communeId, int $feederId, array $overrides = []): Contingency
    {
        return Contingency::query()->create(array_merge([
            'code' => 'CONT-MAP-001',
            'osf_code' => 'OSF-MAP-001',
            'commune_id' => $communeId,
            'feeder_id' => $feederId,
            'status' => 'reported',
            'priority' => 'critical',
            'cause' => 'equipment_failure',
            'description' => 'Evento de mapa completamente sintético',
            'started_at' => '2026-09-09 10:00:00',
            'estimated_restore_at' => '2026-09-09 13:00:00',
            'latitude' => -36.1400000,
            'longitude' => -71.8200000,
            'affected_total' => 10,
            'critical_affected' => 0,
            'electrodependent_affected' => 0,
        ], $overrides));
    }
}
