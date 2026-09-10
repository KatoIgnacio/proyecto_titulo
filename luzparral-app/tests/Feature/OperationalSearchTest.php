<?php

namespace Tests\Feature;

use App\Models\Commune;
use App\Models\Contingency;
use App\Models\Feeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_requires_authentication(): void
    {
        $this->get('/buscador-operacional')->assertRedirect('/login');
    }

    public function test_search_starts_without_exposing_records(): void
    {
        $user = User::factory()->create();
        [$commune, $feeder] = $this->createLocation();
        $this->createContingency($commune, $feeder, 'CONT-SEARCH-001', 'OSF-SEARCH-001', 'reported');

        $this->actingAs($user)
            ->get('/buscador-operacional')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contingencies/Search')
                ->where('hasSearched', false)
                ->where('results.total', 0)
                ->has('results.data', 0));
    }

    public function test_search_finds_a_synthetic_contingency_by_osf(): void
    {
        $user = User::factory()->create();
        [$commune, $feeder] = $this->createLocation();
        $this->createContingency($commune, $feeder, 'CONT-SEARCH-001', 'OSF-SEARCH-001', 'reported');
        $this->createContingency($commune, $feeder, 'CONT-SEARCH-002', 'OSF-OTHER-002', 'closed');

        $this->actingAs($user)
            ->get('/buscador-operacional?category=osf&query=SEARCH-001')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contingencies/Search')
                ->where('hasSearched', true)
                ->where('filters.category', 'osf')
                ->where('filters.query', 'SEARCH-001')
                ->where('results.total', 1)
                ->has('results.data', 1)
                ->where('results.data.0.code', 'CONT-SEARCH-001')
                ->missing('customers')
                ->missing('supplyPoints'));
    }

    public function test_search_can_filter_by_status_without_text(): void
    {
        $user = User::factory()->create();
        [$commune, $feeder] = $this->createLocation();
        $this->createContingency($commune, $feeder, 'CONT-SEARCH-001', 'OSF-SEARCH-001', 'reported');
        $this->createContingency($commune, $feeder, 'CONT-SEARCH-002', 'OSF-SEARCH-002', 'closed');

        $this->actingAs($user)
            ->get('/buscador-operacional?status=closed')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('hasSearched', true)
                ->where('filters.status', 'closed')
                ->where('results.total', 1)
                ->where('results.data.0.code', 'CONT-SEARCH-002'));
    }

    public function test_search_rejects_unknown_filter_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/buscador-operacional?category=customers')
            ->assertSessionHasErrors('category');
    }

    /** @return array{Commune, Feeder} */
    private function createLocation(): array
    {
        $commune = Commune::query()->create([
            'code' => 'SEARCH-TEST',
            'name' => 'COMUNA BUSQUEDA SINTETICA',
            'center_lat' => -36.1400000,
            'center_lon' => -71.8200000,
            'active' => true,
        ]);
        $feeder = Feeder::query()->create([
            'commune_id' => $commune->id,
            'code' => 'ALIM-SEARCH-01',
            'name' => 'Alimentador búsqueda sintético',
            'active' => true,
        ]);

        return [$commune, $feeder];
    }

    private function createContingency(
        Commune $commune,
        Feeder $feeder,
        string $code,
        string $osfCode,
        string $status,
    ): Contingency {
        return Contingency::query()->create([
            'code' => $code,
            'osf_code' => $osfCode,
            'commune_id' => $commune->id,
            'feeder_id' => $feeder->id,
            'status' => $status,
            'priority' => 'medium',
            'cause' => 'synthetic_test',
            'description' => 'Evento de búsqueda completamente sintético',
            'started_at' => '2026-09-09 10:00:00',
            'latitude' => -36.1400000,
            'longitude' => -71.8200000,
            'affected_total' => 10,
            'critical_affected' => 0,
            'electrodependent_affected' => 0,
        ]);
    }
}
