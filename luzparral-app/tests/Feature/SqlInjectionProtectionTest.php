<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Commune;
use App\Models\Contingency;
use App\Models\Feeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SqlInjectionProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_text_filters_treat_sql_payloads_as_literal_values(): void
    {
        $user = User::factory()->create(['role' => UserRole::Supervisor]);
        $this->createContingency();

        foreach (["' OR 1=1 --", "'; DROP TABLE contingencies; --"] as $payload) {
            $dashboardQuery = http_build_query([
                'range' => 'all',
                'search' => $payload,
            ]);
            $this->actingAs($user)
                ->get('/dashboard?'.$dashboardQuery)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('filters.search', $payload)
                    ->where('metrics.total', 0));

            $searchQuery = http_build_query([
                'category' => 'all',
                'query' => $payload,
            ]);
            $this->actingAs($user)
                ->get('/buscador-operacional?'.$searchQuery)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('filters.query', $payload)
                    ->where('results.total', 0));

            $reportQuery = http_build_query([
                'range' => 'all',
                'search' => $payload,
            ]);
            $this->actingAs($user)
                ->get('/informes?'.$reportQuery)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('filters.search', $payload)
                    ->where('summary.total', 0));
        }

        $this->assertTrue(Schema::hasTable('contingencies'));
        $this->assertDatabaseCount('contingencies', 1);
    }

    public function test_structured_filters_reject_sql_fragments_before_querying(): void
    {
        $user = User::factory()->create(['role' => UserRole::Supervisor]);
        $this->createContingency();
        $fragment = "reported' OR 1=1 --";

        $this->actingAs($user)
            ->get('/dashboard?'.http_build_query(['status' => $fragment]))
            ->assertSessionHasErrors('status');

        $this->actingAs($user)
            ->get('/buscador-operacional?'.http_build_query(['category' => "code' UNION SELECT"]))
            ->assertSessionHasErrors('category');

        $this->actingAs($user)
            ->get('/informes?'.http_build_query(['report_type' => "complete' OR 1=1"]))
            ->assertSessionHasErrors('report_type');

        $this->actingAs($user)
            ->get('/contingencias/mapa/datos?'.http_build_query([
                'north' => -36.0,
                'south' => -36.3,
                'east' => -71.6,
                'west' => -72.0,
                'zoom' => '10 OR 1=1',
            ]))
            ->assertSessionHasErrors('zoom');

        $this->assertTrue(Schema::hasTable('contingencies'));
        $this->assertDatabaseCount('contingencies', 1);
    }

    private function createContingency(): Contingency
    {
        $commune = Commune::query()->create([
            'code' => 'SQL-TEST',
            'name' => 'COMUNA SEGURIDAD SINTETICA',
            'center_lat' => -36.1400000,
            'center_lon' => -71.8200000,
            'active' => true,
        ]);
        $feeder = Feeder::query()->create([
            'commune_id' => $commune->id,
            'code' => 'ALIM-SQL-01',
            'name' => 'Alimentador seguridad sintético',
            'active' => true,
        ]);

        return Contingency::query()->create([
            'code' => 'CONT-SQL-001',
            'osf_code' => 'OSF-SQL-001',
            'commune_id' => $commune->id,
            'feeder_id' => $feeder->id,
            'status' => 'reported',
            'priority' => 'medium',
            'cause' => 'Prueba sintética',
            'description' => 'Registro de control para seguridad de filtros',
            'started_at' => '2026-09-09 10:00:00',
            'latitude' => -36.1400000,
            'longitude' => -71.8200000,
            'affected_total' => 10,
            'critical_affected' => 0,
            'electrodependent_affected' => 0,
        ]);
    }
}
