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
            ->has('contingencies.data', 1)
            ->where('contingencies.total', 1)
            ->where('contingencies.currentPage', 1)
            ->where('contingencies.lastPage', 1)
            ->where('contingencies.data.0.code', 'CONT-TEST-001'));
    }

    public function test_dashboard_paginates_every_filtered_contingency_in_pages_of_ten(): void
    {
        $user = User::factory()->create();
        $commune = Commune::query()->create([
            'code' => 'PAG',
            'name' => 'COMUNA PAGINADA',
            'center_lat' => -36.1400000,
            'center_lon' => -71.8200000,
            'active' => true,
        ]);
        $feeder = Feeder::query()->create([
            'commune_id' => $commune->id,
            'code' => 'ALIM-PAG-01',
            'name' => 'Alimentador paginado',
            'active' => true,
        ]);

        foreach (range(1, 12) as $index) {
            Contingency::query()->create([
                'code' => sprintf('CONT-PAG-%03d', $index),
                'osf_code' => sprintf('OSF-PAG-%03d', $index),
                'commune_id' => $commune->id,
                'feeder_id' => $feeder->id,
                'status' => 'reported',
                'priority' => 'high',
                'cause' => 'test',
                'description' => 'Evento sintético para comprobar paginación',
                'started_at' => sprintf('2026-09-%02d 10:00:00', $index),
                'latitude' => -36.1400000,
                'longitude' => -71.8200000,
                'affected_total' => $index,
                'critical_affected' => 0,
                'electrodependent_affected' => 0,
            ]);
        }

        $firstPage = $this->actingAs($user)->get('/dashboard?commune='.$commune->id.'&priority=high');

        $firstPage->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('contingencies.data', 10)
            ->where('contingencies.total', 12)
            ->where('contingencies.from', 1)
            ->where('contingencies.to', 10)
            ->where('contingencies.currentPage', 1)
            ->where('contingencies.lastPage', 2)
            ->where('contingencies.previousPageUrl', null)
            ->where('contingencies.data.0.code', 'CONT-PAG-012')
            ->where('contingencies.data.9.code', 'CONT-PAG-003')
            ->where('contingencies.nextPageUrl', fn (?string $url) => $url !== null
                && str_contains($url, 'page=2')
                && str_contains($url, 'priority=high')));

        $secondPage = $this->actingAs($user)->get('/dashboard?commune='.$commune->id.'&priority=high&page=2');

        $secondPage->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('contingencies.data', 2)
            ->where('contingencies.total', 12)
            ->where('contingencies.from', 11)
            ->where('contingencies.to', 12)
            ->where('contingencies.currentPage', 2)
            ->where('contingencies.lastPage', 2)
            ->where('contingencies.nextPageUrl', null)
            ->where('contingencies.data.0.code', 'CONT-PAG-002')
            ->where('contingencies.data.1.code', 'CONT-PAG-001')
            ->where('contingencies.previousPageUrl', fn (?string $url) => $url !== null
                && str_contains($url, 'page=1')
                && str_contains($url, 'commune='.$commune->id)));
    }

    public function test_dashboard_rejects_unknown_filter_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard?status=unknown')
            ->assertSessionHasErrors('status');
    }
}
