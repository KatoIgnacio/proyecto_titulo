<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContingencyReportRequest;
use App\Models\Contingency;
use App\Services\Reports\ContingencyReportData;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContingencyReportController extends Controller
{
    private const REPORT_TYPES = [
        'executive' => [
            'title' => 'Informe ejecutivo de contingencias eléctricas',
            'filename' => 'informe-ejecutivo-contingencias',
        ],
        'development' => [
            'title' => 'Informe de desarrollo de contingencias eléctricas',
            'filename' => 'informe-desarrollo-contingencias',
        ],
        'complete' => [
            'title' => 'Informe integral de contingencias eléctricas',
            'filename' => 'informe-integral-contingencias',
        ],
    ];

    public function __construct(private readonly ContingencyReportData $reports) {}

    public function index(ContingencyReportRequest $request): InertiaResponse
    {
        $referenceDate = $this->reports->referenceDate();
        $filters = $request->reportFilters($referenceDate);
        $query = $this->reports->filteredQuery($filters, $referenceDate);
        $paginator = (clone $query)
            ->with(['commune:id,name', 'feeder:id,code,name'])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Reports/Index', [
            'filters' => $filters,
            'referenceDate' => $referenceDate->toIso8601String(),
            'filterOptions' => $this->reports->filterOptions(),
            'summary' => $this->reports->summary($query),
            'results' => [
                'data' => collect($paginator->items())
                    ->map(fn (Contingency $contingency) => $this->reports->pageRow($contingency))
                    ->values(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'previousPageUrl' => $paginator->previousPageUrl(),
                'nextPageUrl' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    public function exportCsv(ContingencyReportRequest $request): StreamedResponse
    {
        $referenceDate = $this->reports->referenceDate();
        $rows = $this->reports->exportRows(
            $this->reports->reportCollection($request->reportFilters($referenceDate)),
        );
        $filename = 'informe-contingencias-'.CarbonImmutable::now('America/Santiago')->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Código',
                'OSF',
                'Comuna',
                'Alimentador',
                'Estado',
                'Criticidad',
                'Causa',
                'Clientes afectados',
                'Clientes críticos',
                'Electrodependientes',
                'Inicio',
                'Reposición estimada',
                'Reposición efectiva',
            ], ';', '"', '');

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['code'],
                    $row['osf_code'],
                    $row['commune'],
                    $row['feeder'],
                    $row['status'],
                    $row['priority'],
                    $row['cause'],
                    $row['affected_total'],
                    $row['critical_affected'],
                    $row['electrodependent_affected'],
                    $row['started_at'],
                    $row['estimated_restore_at'],
                    $row['restored_at'],
                ], ';', '"', '');
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportPdf(ContingencyReportRequest $request): HttpResponse
    {
        $referenceDate = $this->reports->referenceDate();
        $filters = $request->reportFilters($referenceDate);
        $reportType = $request->reportType();
        $includesAnalytics = in_array($reportType, ['executive', 'complete'], true);
        $includesDetail = in_array($reportType, ['development', 'complete'], true);
        $query = $this->reports->filteredQuery($filters, $referenceDate);
        $contingencies = $this->reports->pdfCollection($query, $includesDetail);
        $generatedAt = CarbonImmutable::now('America/Santiago');
        $definition = self::REPORT_TYPES[$reportType];

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('reports.contingencies-pdf', [
            'rows' => $includesDetail ? $this->reports->exportRows($contingencies, $referenceDate) : collect(),
            'analytics' => $this->reports->analytics($contingencies, $filters),
            'summary' => $this->reports->summary($query),
            'filters' => $this->reports->filterSummary($filters),
            'referenceDate' => $referenceDate,
            'generatedAt' => $generatedAt,
            'reportType' => $reportType,
            'reportTitle' => $definition['title'],
            'includesAnalytics' => $includesAnalytics,
            'includesDetail' => $includesDetail,
            'companyLogo' => $this->reports->imageDataUri('images/logo-luzparral.png'),
            'systemLogo' => $this->reports->imageDataUri('images/logo-sigcel-transparente.png'),
        ])->render(), 'UTF-8');
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        $filename = $definition['filename'].'-'.$generatedAt->format('Ymd-His').'.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
