<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Commune;
use App\Models\Contingency;
use App\Models\Feeder;
use App\Models\SupplyPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
                ->where('mapData.summary.events', 1)
                ->where('mapData.summary.affected', 30)
                ->where('mapData.summary.critical', 3)
                ->where('mapData.summary.electrodependent', 1)
                ->has('mapData.features', 1)
                ->where('mapData.features.0.contingency.code', 'CONT-MAP-ACTIVE')
                ->where('mapData.features.0.latitude', -36.14)
                ->where('mapData.features.0.longitude', -71.82));
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
                ->where('mapData.summary.events', 1)
                ->where('mapData.features.0.contingency.status', 'closed'));
    }

    public function test_visible_area_endpoint_excludes_outside_events_and_groups_nearby_markers(): void
    {
        $user = User::factory()->create();
        [$commune, $feeder] = $this->createLocation();

        $this->createContingency($commune->id, $feeder->id, [
            'code' => 'CONT-MAP-IN-001',
            'osf_code' => 'OSF-MAP-IN-001',
            'latitude' => -36.1401,
            'longitude' => -71.8201,
        ]);
        $this->createContingency($commune->id, $feeder->id, [
            'code' => 'CONT-MAP-IN-002',
            'osf_code' => 'OSF-MAP-IN-002',
            'latitude' => -36.1402,
            'longitude' => -71.8202,
        ]);
        $this->createContingency($commune->id, $feeder->id, [
            'code' => 'CONT-MAP-OUT',
            'osf_code' => 'OSF-MAP-OUT',
            'latitude' => -36.8,
            'longitude' => -72.2,
        ]);

        $this->actingAs($user)
            ->getJson('/contingencias/mapa/datos?north=-36&south=-36.3&east=-71.6&west=-72&zoom=10')
            ->assertOk()
            ->assertJsonPath('summary.events', 2)
            ->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.event_count', 2)
            ->assertJsonPath('features.0.contingency', null)
            ->assertJsonPath('meta.bounds_applied', true);
    }

    public function test_sensitive_layers_are_aggregated_and_hidden_from_viewer(): void
    {
        [$commune, $feeder] = $this->createLocation();
        $contingency = $this->createContingency($commune->id, $feeder->id);
        $point = SupplyPoint::query()->create([
            'synthetic_code' => 'SYN-SP-MAP-001',
            'customer_code' => 'SYN-CL-MAP-001',
            'commune_id' => $commune->id,
            'feeder_id' => $feeder->id,
            'latitude' => -36.1403,
            'longitude' => -71.8203,
            'criticality' => 'critical_electrodependent',
            'active' => true,
            'installed_at' => '2020-01-01',
        ]);
        DB::table('contingency_impacts')->insert([
            'contingency_id' => $contingency->id,
            'supply_point_id' => $point->id,
            'status' => 'affected',
            'affected_at' => '2026-09-09 10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $viewport = '/contingencias/mapa/datos?north=-36&south=-36.3&east=-71.6&west=-72&zoom=13';

        $operator = User::factory()->create(['role' => UserRole::Operator]);
        $this->actingAs($operator)
            ->getJson($viewport)
            ->assertOk()
            ->assertJsonPath('meta.can_view_sensitive_layers', true)
            ->assertJsonCount(1, 'layers.critical_zones')
            ->assertJsonCount(1, 'layers.electrodependent_zones')
            ->assertJsonPath('layers.critical_zones.0.latitude', -36.14)
            ->assertJsonMissingPath('layers.critical_zones.0.customer_code')
            ->assertJsonMissingPath('layers.critical_zones.0.synthetic_code');

        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $this->actingAs($viewer)
            ->getJson($viewport)
            ->assertOk()
            ->assertJsonPath('meta.can_view_sensitive_layers', false)
            ->assertJsonCount(0, 'layers.critical_zones')
            ->assertJsonCount(0, 'layers.electrodependent_zones');
    }

    public function test_map_response_remains_bounded_with_a_large_visible_dataset(): void
    {
        $user = User::factory()->create();
        [$commune, $feeder] = $this->createLocation();
        $rows = [];

        foreach (range(1, 450) as $index) {
            $row = intdiv($index - 1, 25);
            $column = ($index - 1) % 25;
            $rows[] = [
                'code' => sprintf('CONT-MAP-VOLUME-%04d', $index),
                'osf_code' => sprintf('OSF-MAP-VOLUME-%04d', $index),
                'commune_id' => $commune->id,
                'feeder_id' => $feeder->id,
                'status' => 'reported',
                'priority' => 'medium',
                'cause' => 'synthetic_test',
                'description' => 'Evento sintético para prueba de volumen del mapa.',
                'started_at' => '2026-09-09 10:00:00',
                'latitude' => -36.14 + ($row * 0.0002),
                'longitude' => -71.82 + ($column * 0.0002),
                'affected_total' => 1,
                'critical_affected' => 0,
                'electrodependent_affected' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('contingencies')->insert($rows);

        $this->actingAs($user)
            ->getJson('/contingencias/mapa/datos?north=-36&south=-36.3&east=-71.6&west=-72&zoom=15')
            ->assertOk()
            ->assertJsonPath('summary.events', 450)
            ->assertJsonCount(400, 'features')
            ->assertJsonPath('meta.feature_limit', 400)
            ->assertJsonPath('meta.features_truncated', true);
    }

    public function test_map_rejects_invalid_or_oversized_visible_areas(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/contingencias/mapa/datos?north=-36.5&south=-36&east=-71.6&west=-72&zoom=10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('viewport');

        $this->actingAs($user)
            ->getJson('/contingencias/mapa/datos?north=20&south=-20&east=-60&west=-80&zoom=10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('viewport');
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
