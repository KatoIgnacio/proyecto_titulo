<?php

namespace Tests\Feature;

use App\Models\Commune;
use App\Models\Contingency;
use App\Models\ContingencyHistory;
use App\Models\ContingencyImpact;
use App\Models\Feeder;
use App\Models\SupplyPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContingencyDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_requires_authentication(): void
    {
        $this->get('/contingencias/999')->assertRedirect('/login');
    }

    public function test_detail_shows_summary_impacts_and_history_without_customer_data(): void
    {
        $user = User::factory()->create();
        $commune = Commune::query()->create([
            'code' => 'DETAIL-TEST',
            'name' => 'COMUNA DETALLE SINTETICA',
            'center_lat' => -36.1400000,
            'center_lon' => -71.8200000,
            'active' => true,
        ]);
        $feeder = Feeder::query()->create([
            'commune_id' => $commune->id,
            'code' => 'ALIM-DETAIL-01',
            'name' => 'Alimentador detalle sintético',
            'active' => true,
        ]);
        $contingency = Contingency::query()->create([
            'code' => 'CONT-DETAIL-001',
            'osf_code' => 'OSF-DETAIL-001',
            'commune_id' => $commune->id,
            'feeder_id' => $feeder->id,
            'status' => 'in_progress',
            'priority' => 'critical',
            'cause' => 'equipment_failure',
            'description' => 'Evento de detalle completamente sintético',
            'started_at' => '2026-09-09 10:00:00',
            'estimated_restore_at' => '2026-09-09 14:00:00',
            'latitude' => -36.1400000,
            'longitude' => -71.8200000,
            'affected_total' => 2,
            'critical_affected' => 1,
            'electrodependent_affected' => 1,
        ]);

        $restoredPoint = $this->createSupplyPoint($commune->id, $feeder->id, 'SP-DETAIL-001');
        $pendingPoint = $this->createSupplyPoint($commune->id, $feeder->id, 'SP-DETAIL-002');

        ContingencyImpact::query()->create([
            'contingency_id' => $contingency->id,
            'supply_point_id' => $restoredPoint->id,
            'status' => 'restored',
            'affected_at' => '2026-09-09 10:00:00',
            'restored_at' => '2026-09-09 11:30:00',
            'outage_minutes' => 90,
        ]);
        ContingencyImpact::query()->create([
            'contingency_id' => $contingency->id,
            'supply_point_id' => $pendingPoint->id,
            'status' => 'affected',
            'affected_at' => '2026-09-09 10:00:00',
        ]);
        ContingencyHistory::query()->create([
            'contingency_id' => $contingency->id,
            'status' => 'reported',
            'note' => 'Evento sintético reportado.',
            'event_at' => '2026-09-09 10:00:00',
            'user_id' => $user->id,
            'source' => 'synthetic',
            'created_at' => '2026-09-09 10:00:00',
        ]);
        ContingencyHistory::query()->create([
            'contingency_id' => $contingency->id,
            'status' => 'in_progress',
            'note' => 'Evento sintético en atención.',
            'event_at' => '2026-09-09 10:30:00',
            'user_id' => $user->id,
            'source' => 'synthetic',
            'created_at' => '2026-09-09 10:30:00',
        ]);

        $this->actingAs($user)
            ->get('/contingencias/'.$contingency->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contingencies/Show')
                ->where('contingency.code', 'CONT-DETAIL-001')
                ->where('contingency.commune', 'COMUNA DETALLE SINTETICA')
                ->where('impactSummary.registered', 2)
                ->where('impactSummary.restored', 1)
                ->where('impactSummary.pending', 1)
                ->where('impactSummary.averageMinutes', 90)
                ->has('history', 2)
                ->where('history.0.status', 'in_progress')
                ->missing('customers')
                ->missing('fieldReports'));
    }

    private function createSupplyPoint(int $communeId, int $feederId, string $code): SupplyPoint
    {
        return SupplyPoint::query()->create([
            'synthetic_code' => $code,
            'customer_code' => 'CUSTOMER-'.$code,
            'commune_id' => $communeId,
            'feeder_id' => $feederId,
            'latitude' => -36.1400000,
            'longitude' => -71.8200000,
            'criticality' => 'normal',
            'active' => true,
            'installed_at' => '2025-01-01',
        ]);
    }
}
