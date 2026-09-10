<?php

namespace Tests\Feature;

use App\Models\Commune;
use App\Models\Contingency;
use App\Models\Feeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_dashboard_returns_filtered_operational_data(): void
    {
        $user = User::factory()->create();
        $commune = Commune::query()->create([
            'code' => 'TEST',
            'name' => 'COMUNA SINTETICA',
            'center_lat' => -36.1400000,
            'center_lon' => -71.8200000,
            'active' => true,
        ]);
        $feeder = Feeder::query()->create([
            'commune_id' => $commune->id,
            'code' => 'ALIM-TEST-01',
            'name' => 'Alimentador sintético',
            'active' => true,
        ]);

        Contingency::query()->create([
            'code' => 'CONT-TEST-001',
            'osf_code' => 'OSF-TEST-001',
            'commune_id' => $commune->id,
            'feeder_id' => $feeder->id,
            'status' => 'reported',
            'priority' => 'critical',
            'cause' => 'test',
            'description' => 'Evento completamente sintético',
            'started_at' => '2026-09-09 10:00:00',
            'latitude' => -36.1400000,
            'longitude' => -71.8200000,
            'affected_total' => 25,
            'critical_affected' => 2,
            'electrodependent_affected' => 1,
        ]);

        $response = $this->actingAs($user)->get('/dashboard?commune='.$commune->id.'&priority=critical');

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('filters.commune', $commune->id)
            ->where('filters.priority', 'critical')
            ->where('metrics.total', 1)
            ->where('metrics.active', 1)
            ->where('metrics.affected', 25)
            ->where('metrics.critical', 2)
            ->where('metrics.electrodependent', 1)
            ->has('filterOptions.communes', 1)
            ->has('filterOptions.feeders', 1)
            ->has('trend', 1)
            ->has('communeDistribution', 1)
            ->has('contingencies', 1)
            ->where('contingencies.0.code', 'CONT-TEST-001'));
    }

    public function test_dashboard_rejects_unknown_filter_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard?status=unknown')
            ->assertSessionHasErrors('status');
    }
}
