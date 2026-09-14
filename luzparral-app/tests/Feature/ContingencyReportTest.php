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

class ContingencyReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_require_authentication(): void
    {
        $this->get('/informes')->assertRedirect('/login');
        $this->get('/informes/contingencias.csv')->assertRedirect('/login');
        $this->get('/informes/contingencias.pdf')->assertRedirect('/login');
    }

    public function test_report_page_uses_filters_for_summary_and_rows(): void
    {
        $user = $this->reportUser();
        [$commune, $feeder] = $this->createLocation();
        $this->createContingency($commune, $feeder, 'CONT-REPORT-001', 'reported', 'critical', 25);
        $this->createContingency($commune, $feeder, 'CONT-REPORT-002', 'closed', 'medium', 10);

        $this->actingAs($user)
            ->get('/informes?range=all&status=reported')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Index')
                ->where('filters.status', 'reported')
                ->where('summary.total', 1)
                ->where('summary.active', 1)
                ->where('summary.affected', 25)
                ->has('results.data', 1)
                ->where('results.data.0.code', 'CONT-REPORT-001')
                ->missing('customers')
                ->missing('supplyPoints'));
    }

    public function test_csv_export_is_excel_compatible_and_filtered(): void
    {
        $user = $this->reportUser();
        [$commune, $feeder] = $this->createLocation();
        $contingency = $this->createContingency($commune, $feeder, 'CONT-CSV-001', 'reported', 'critical', 25);
        $contingency->update(['cause' => 'weather']);
        $this->createContingency($commune, $feeder, 'CONT-CSV-002', 'closed', 'medium', 10);

        $response = $this->actingAs($user)->get('/informes/contingencias.csv?range=all&status=reported');

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload();

        ob_start();
        $response->sendContent();
        $content = (string) ob_get_clean();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Código;OSF;Comuna;Alimentador', $content);
        $this->assertStringContainsString('CONT-CSV-001', $content);
        $this->assertStringContainsString('Clima', $content);
        $this->assertStringNotContainsString('weather', $content);
        $this->assertStringNotContainsString('CONT-CSV-002', $content);
        $this->assertStringNotContainsString('customer_code', $content);
    }

    public function test_pdf_export_is_a_filtered_pdf_download(): void
    {
        $user = $this->reportUser();
        [$commune, $feeder] = $this->createLocation();
        $this->createContingency($commune, $feeder, 'CONT-PDF-001', 'reported', 'critical', 25);

        $response = $this->actingAs($user)->get('/informes/contingencias.pdf?range=all&priority=critical');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertGreaterThan(1000, strlen($response->getContent()));
        $this->assertStringContainsString('informe-ejecutivo-contingencias-', (string) $response->headers->get('content-disposition'));
    }

    public function test_pdf_export_supports_the_three_report_types(): void
    {
        $user = $this->reportUser();
        [$commune, $feeder] = $this->createLocation();
        $this->createContingency($commune, $feeder, 'CONT-PDF-MODES', 'reported', 'critical', 25);

        foreach ([
            'executive' => 'informe-ejecutivo-contingencias-',
            'development' => 'informe-desarrollo-contingencias-',
            'complete' => 'informe-integral-contingencias-',
        ] as $reportType => $filenamePrefix) {
            $response = $this->actingAs($user)->get('/informes/contingencias.pdf?range=all&report_type='.$reportType);

            $response->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $response->getContent());
            $this->assertStringContainsString($filenamePrefix, (string) $response->headers->get('content-disposition'));
        }
    }

    public function test_report_rejects_invalid_filters(): void
    {
        $user = $this->reportUser();

        $this->actingAs($user)
            ->get('/informes?range=century')
            ->assertSessionHasErrors('range');

        $this->actingAs($user)
            ->get('/informes/contingencias.pdf?report_type=raw')
            ->assertSessionHasErrors('report_type');
    }

    private function reportUser(): User
    {
        return User::factory()->create([
            'role' => UserRole::Supervisor,
        ]);
    }

    /** @return array{Commune, Feeder} */
    private function createLocation(): array
    {
        $commune = Commune::query()->create([
            'code' => 'REPORT-TEST',
            'name' => 'COMUNA INFORME SINTETICA',
            'center_lat' => -36.1400000,
            'center_lon' => -71.8200000,
            'active' => true,
        ]);
        $feeder = Feeder::query()->create([
            'commune_id' => $commune->id,
            'code' => 'ALIM-REPORT-01',
            'name' => 'Alimentador informe sintético',
            'active' => true,
        ]);

        return [$commune, $feeder];
    }

    private function createContingency(Commune $commune, Feeder $feeder, string $code, string $status, string $priority, int $affected): Contingency
    {
        return Contingency::query()->create([
            'code' => $code,
            'osf_code' => str_replace('CONT', 'OSF', $code),
            'commune_id' => $commune->id,
            'feeder_id' => $feeder->id,
            'status' => $status,
            'priority' => $priority,
            'cause' => 'Prueba sintética',
            'description' => 'Contingencia de informe completamente sintética',
            'started_at' => '2026-09-09 10:00:00',
            'estimated_restore_at' => '2026-09-09 12:00:00',
            'latitude' => -36.1400000,
            'longitude' => -71.8200000,
            'affected_total' => $affected,
            'critical_affected' => 2,
            'electrodependent_affected' => 1,
        ]);
    }
}
