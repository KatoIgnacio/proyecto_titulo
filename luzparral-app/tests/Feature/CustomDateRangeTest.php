<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Commune;
use App\Models\Contingency;
use App\Models\Feeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomDateRangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_day_month_and_year_modes_apply_complete_calendar_periods(): void
    {
        $user = User::factory()->create(['role' => UserRole::Supervisor]);
        [$commune, $feeder] = $this->createLocation();

        $this->createContingency($commune, $feeder, '2025', '2025-12-31 23:59:59');
        $this->createContingency($commune, $feeder, 'AUGUST', '2026-08-31 23:59:59');
        $this->createContingency($commune, $feeder, 'DAY-FIRST', '2026-09-02 00:00:00');
        $this->createContingency($commune, $feeder, 'DAY-LAST', '2026-09-02 23:59:59');
        $this->createContingency($commune, $feeder, 'SEPTEMBER', '2026-09-30 23:59:59');
        $this->createContingency($commune, $feeder, 'OCTOBER', '2026-10-01 00:00:00');

        $this->actingAs($user)
            ->get('/dashboard?range=day&date_day=2026-09-02')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.range', 'day')
                ->where('filters.date_day', '2026-09-02')
                ->where('filters.date_from', '2026-09-02')
                ->where('filters.date_to', '2026-09-02')
                ->where('metrics.total', 2));

        $this->actingAs($user)
            ->get('/dashboard?range=month&date_month=2026-09')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.range', 'month')
                ->where('filters.date_month', '2026-09')
                ->where('filters.date_from', '2026-09-01')
                ->where('filters.date_to', '2026-09-30')
                ->where('metrics.total', 3));

        $this->actingAs($user)
            ->get('/dashboard?range=year&date_year=2026')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.range', 'year')
                ->where('filters.date_year', '2026')
                ->where('filters.date_from', '2026-01-01')
                ->where('filters.date_to', '2026-12-31')
                ->where('metrics.total', 5));
    }

    public function test_calendar_period_is_consistent_across_operational_modules(): void
    {
        $user = User::factory()->create(['role' => UserRole::Supervisor]);
        [$commune, $feeder] = $this->createLocation();

        $this->createContingency($commune, $feeder, 'IN-MONTH', '2026-09-15 10:00:00');
        $this->createContingency($commune, $feeder, 'OUT-MONTH', '2026-08-15 10:00:00');
        $query = '?range=month&date_month=2026-09';

        $this->actingAs($user)->get('/dashboard'.$query)
            ->assertInertia(fn (Assert $page) => $page->where('metrics.total', 1));
        $this->actingAs($user)->get('/contingencias/mapa'.$query)
            ->assertInertia(fn (Assert $page) => $page->where('summary.events', 1));
        $this->actingAs($user)->get('/informes'.$query)
            ->assertInertia(fn (Assert $page) => $page->where('summary.total', 1));
    }

    public function test_custom_date_range_is_inclusive_and_consistent_across_operational_modules(): void
    {
        $user = User::factory()->create(['role' => UserRole::Supervisor]);
        [$commune, $feeder] = $this->createLocation();

        $this->createContingency($commune, $feeder, 'BEFORE', '2026-08-31 23:59:59');
        $this->createContingency($commune, $feeder, 'FIRST', '2026-09-01 00:00:00');
        $this->createContingency($commune, $feeder, 'LAST', '2026-09-03 23:59:59');
        $this->createContingency($commune, $feeder, 'AFTER', '2026-09-04 00:00:00');

        $query = '?range=custom&date_from=2026-09-01&date_to=2026-09-03';

        $this->actingAs($user)
            ->get('/dashboard'.$query)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.range', 'custom')
                ->where('filters.date_from', '2026-09-01')
                ->where('filters.date_to', '2026-09-03')
                ->where('metrics.total', 2)
                ->has('contingencies', 2));

        $this->actingAs($user)
            ->get('/contingencias/mapa'.$query)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.range', 'custom')
                ->where('summary.events', 2)
                ->has('contingencies', 2));

        $this->actingAs($user)
            ->get('/informes'.$query)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.range', 'custom')
                ->where('summary.total', 2)
                ->has('results.data', 2));
    }

    public function test_custom_date_range_is_preserved_in_csv_and_pdf_exports(): void
    {
        $user = User::factory()->create(['role' => UserRole::Supervisor]);
        [$commune, $feeder] = $this->createLocation();

        $this->createContingency($commune, $feeder, 'IN-RANGE', '2026-09-02 10:00:00');
        $this->createContingency($commune, $feeder, 'OUT-RANGE', '2026-08-15 10:00:00');
        $query = '?range=custom&date_from=2026-09-01&date_to=2026-09-03';

        $csv = $this->actingAs($user)->get('/informes/contingencias.csv'.$query);
        $csv->assertOk()->assertDownload();
        ob_start();
        $csv->sendContent();
        $content = (string) ob_get_clean();
        $this->assertStringContainsString('CONT-IN-RANGE', $content);
        $this->assertStringNotContainsString('CONT-OUT-RANGE', $content);

        $this->actingAs($user)
            ->get('/informes/contingencias.pdf'.$query.'&report_type=executive')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_custom_date_range_requires_valid_ordered_dates(): void
    {
        $user = User::factory()->create(['role' => UserRole::Supervisor]);

        $this->actingAs($user)
            ->get('/dashboard?range=custom&date_from=2026-09-03&date_to=2026-09-01')
            ->assertSessionHasErrors('date_to');

        $this->actingAs($user)
            ->get('/contingencias/mapa?range=custom')
            ->assertSessionHasErrors(['date_from', 'date_to']);

        $this->actingAs($user)
            ->get('/informes?range=custom&date_from=incorrecta&date_to=2026-09-03')
            ->assertSessionHasErrors('date_from');

        $this->actingAs($user)->get('/dashboard?range=day')->assertSessionHasErrors('date_day');
        $this->actingAs($user)->get('/dashboard?range=month&date_month=2026-13')->assertSessionHasErrors('date_month');
        $this->actingAs($user)->get('/dashboard?range=year&date_year=26')->assertSessionHasErrors('date_year');
    }

    /** @return array{Commune, Feeder} */
    private function createLocation(): array
    {
        $commune = Commune::query()->create([
            'code' => 'DATE-TEST',
            'name' => 'COMUNA FECHA SINTETICA',
            'center_lat' => -36.14,
            'center_lon' => -71.82,
            'active' => true,
        ]);
        $feeder = Feeder::query()->create([
            'commune_id' => $commune->id,
            'code' => 'ALIM-DATE-01',
            'name' => 'Alimentador fecha sintético',
            'active' => true,
        ]);

        return [$commune, $feeder];
    }

    private function createContingency(Commune $commune, Feeder $feeder, string $suffix, string $startedAt): Contingency
    {
        return Contingency::query()->create([
            'code' => 'CONT-'.$suffix,
            'osf_code' => 'OSF-'.$suffix,
            'commune_id' => $commune->id,
            'feeder_id' => $feeder->id,
            'status' => 'reported',
            'priority' => 'medium',
            'cause' => 'test',
            'description' => 'Contingencia sintética para validar fechas',
            'started_at' => $startedAt,
            'estimated_restore_at' => '2026-09-05 12:00:00',
            'latitude' => -36.14,
            'longitude' => -71.82,
            'affected_total' => 10,
            'critical_affected' => 1,
            'electrodependent_affected' => 0,
        ]);
    }
}
