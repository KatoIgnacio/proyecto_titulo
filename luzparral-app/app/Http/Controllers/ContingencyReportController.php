<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContingencyReportRequest;
use App\Models\Commune;
use App\Models\Contingency;
use App\Models\Feeder;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContingencyReportController extends Controller
{
    private const ACTIVE_STATUSES = ['reported', 'assigned', 'in_progress'];

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

    public function index(ContingencyReportRequest $request): InertiaResponse
    {
        $filters = $request->reportFilters();
        $referenceDate = $this->referenceDate();
        $query = $this->filteredQuery($filters, $referenceDate);
        $summary = $this->summary($query);
        $paginator = (clone $query)
            ->with(['commune:id,name', 'feeder:id,code,name'])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Reports/Index', [
            'filters' => $filters,
            'referenceDate' => $referenceDate->toIso8601String(),
            'filterOptions' => [
                'communes' => Commune::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
                'feeders' => Feeder::query()->where('active', true)->orderBy('code')->get(['id', 'commune_id', 'code', 'name']),
            ],
            'summary' => $summary,
            'results' => [
                'data' => collect($paginator->items())->map(fn (Contingency $contingency) => $this->pageRow($contingency))->values(),
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
        $filters = $request->reportFilters();
        $rows = $this->exportRows($this->reportCollection($filters));
        $filename = 'informe-contingencias-'.CarbonImmutable::now('America/Santiago')->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
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
        $filters = $request->reportFilters();
        $reportType = $request->reportType();
        $includesAnalytics = in_array($reportType, ['executive', 'complete'], true);
        $includesDetail = in_array($reportType, ['development', 'complete'], true);
        $referenceDate = $this->referenceDate();
        $query = $this->filteredQuery($filters, $referenceDate);
        $relations = [
            'commune:id,name',
            'feeder:id,code,name',
        ];

        if ($includesDetail) {
            $relations[] = 'history.user:id,name';
        }

        $contingencies = (clone $query)
            ->with($relations)
            ->withCount('history')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get();
        $rows = $includesDetail ? $this->exportRows($contingencies, $referenceDate) : collect();
        $analytics = $this->reportAnalytics($contingencies, $filters);
        $generatedAt = CarbonImmutable::now('America/Santiago');
        $reportDefinition = self::REPORT_TYPES[$reportType];

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('reports.contingencies-pdf', [
            'rows' => $rows,
            'analytics' => $analytics,
            'summary' => $this->summary($query),
            'filters' => $this->filterSummary($filters),
            'referenceDate' => $referenceDate,
            'generatedAt' => $generatedAt,
            'reportType' => $reportType,
            'reportTitle' => $reportDefinition['title'],
            'includesAnalytics' => $includesAnalytics,
            'includesDetail' => $includesDetail,
            'companyLogo' => $this->imageDataUri('images/logo-luzparral.png'),
            'systemLogo' => $this->imageDataUri('images/logo-sistema-transparente.png'),
        ])->render(), 'UTF-8');
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        $filename = $reportDefinition['filename'].'-'.$generatedAt->format('Ymd-His').'.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function referenceDate(): CarbonImmutable
    {
        $latestDatasetDate = Contingency::query()->max('started_at');

        return $latestDatasetDate
            ? CarbonImmutable::parse($latestDatasetDate)
            : CarbonImmutable::now('America/Santiago');
    }

    /**
     * @param  array{range: string, commune: ?int, feeder: ?int, priority: ?string, status: ?string, search: string}  $filters
     */
    private function filteredQuery(array $filters, CarbonImmutable $referenceDate): Builder
    {
        $query = Contingency::query();
        $startDate = match ($filters['range']) {
            '24h' => $referenceDate->subDay(),
            '7d' => $referenceDate->startOfDay()->subDays(6),
            '30d' => $referenceDate->startOfDay()->subDays(29),
            '12m' => $referenceDate->subYear(),
            default => null,
        };

        if ($startDate) {
            $query->where('contingencies.started_at', '>=', $startDate);
        }

        $query
            ->when($filters['commune'], fn (Builder $builder, int $commune) => $builder->where('contingencies.commune_id', $commune))
            ->when($filters['feeder'], fn (Builder $builder, int $feeder) => $builder->where('contingencies.feeder_id', $feeder))
            ->when($filters['priority'], fn (Builder $builder, string $priority) => $builder->where('contingencies.priority', $priority))
            ->when($filters['status'], fn (Builder $builder, string $status) => $builder->where('contingencies.status', $status))
            ->when($filters['search'], function (Builder $builder, string $search) {
                $term = '%'.$search.'%';

                $builder->where(function (Builder $searchQuery) use ($term) {
                    $searchQuery
                        ->where('contingencies.code', 'like', $term)
                        ->orWhere('contingencies.osf_code', 'like', $term)
                        ->orWhere('contingencies.description', 'like', $term)
                        ->orWhere('contingencies.cause', 'like', $term)
                        ->orWhereHas('commune', fn (Builder $commune) => $commune->where('name', 'like', $term))
                        ->orWhereHas('feeder', fn (Builder $feeder) => $feeder
                            ->where('code', 'like', $term)
                            ->orWhere('name', 'like', $term));
                });
            });

        return $query;
    }

    /** @return array<string, int|null> */
    private function summary(Builder $query): array
    {
        $filteredIds = (clone $query)->pluck('contingencies.id');
        $averageMinutes = DB::table('contingency_impacts')
            ->whereIn('contingency_id', $filteredIds)
            ->whereNotNull('outage_minutes')
            ->avg('outage_minutes');

        return [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->whereIn('contingencies.status', self::ACTIVE_STATUSES)->count(),
            'affected' => (int) (clone $query)->sum('contingencies.affected_total'),
            'critical' => (int) (clone $query)->sum('contingencies.critical_affected'),
            'electrodependent' => (int) (clone $query)->sum('contingencies.electrodependent_affected'),
            'averageMinutes' => $averageMinutes === null ? null : (int) round($averageMinutes),
        ];
    }

    /**
     * @param  array{range: string, commune: ?int, feeder: ?int, priority: ?string, status: ?string, search: string}  $filters
     */
    private function reportCollection(array $filters): Collection
    {
        return $this->filteredQuery($filters, $this->referenceDate())
            ->with(['commune:id,name', 'feeder:id,code,name'])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get();
    }

    /** @return array<string, mixed> */
    private function pageRow(Contingency $contingency): array
    {
        return [
            'id' => $contingency->id,
            'code' => $contingency->code,
            'osf_code' => $contingency->osf_code,
            'commune' => $contingency->commune?->name,
            'feeder' => $contingency->feeder?->code,
            'status' => $contingency->status,
            'priority' => $contingency->priority,
            'affected_total' => $contingency->affected_total,
            'started_at' => $contingency->started_at?->toIso8601String(),
        ];
    }

    private function exportRows(Collection $contingencies, ?CarbonImmutable $referenceDate = null): Collection
    {
        $referenceDate ??= $this->referenceDate();

        return $contingencies->map(fn (Contingency $contingency) => [
            'code' => $contingency->code,
            'osf_code' => $contingency->osf_code,
            'commune' => $contingency->commune?->name ?? '',
            'feeder' => $contingency->feeder?->code ?? '',
            'status' => $this->statusLabel($contingency->status),
            'priority' => $this->priorityLabel($contingency->priority),
            'cause' => $this->causeLabel($contingency->cause),
            'description' => $contingency->description,
            'affected_total' => $contingency->affected_total,
            'critical_affected' => $contingency->critical_affected,
            'electrodependent_affected' => $contingency->electrodependent_affected,
            'started_at' => $contingency->started_at?->format('d-m-Y H:i') ?? '',
            'estimated_restore_at' => $contingency->estimated_restore_at?->format('d-m-Y H:i') ?? '',
            'restored_at' => $contingency->restored_at?->format('d-m-Y H:i') ?? '',
            'duration_minutes' => $contingency->started_at
                ? max(0, (int) round($contingency->started_at->diffInMinutes($contingency->restored_at ?? $referenceDate)))
                : null,
            'history_count' => $contingency->relationLoaded('history') ? $contingency->history->count() : null,
            'last_event_at' => $contingency->relationLoaded('history')
                ? $contingency->history->max('event_at')?->format('d-m-Y H:i')
                : null,
            'history' => $contingency->relationLoaded('history')
                ? $contingency->history->sortBy('event_at')->values()->map(fn ($event) => [
                    'event_at' => $event->event_at?->format('d-m-Y H:i') ?? '',
                    'status' => $this->statusLabel($event->status),
                    'note' => $event->note,
                    'source' => match ($event->source) {
                        'system' => 'Sistema',
                        'user' => 'Usuario',
                        default => ucfirst((string) $event->source),
                    },
                    'responsible' => $event->user?->name ?? 'Proceso automático',
                ])->values()
                : collect(),
        ])->values();
    }

    /**
     * @param  array{range: string, commune: ?int, feeder: ?int, priority: ?string, status: ?string, search: string}  $filters
     * @return array<string, mixed>
     */
    private function reportAnalytics(Collection $contingencies, array $filters): array
    {
        $trend = $this->trend($contingencies, $filters['range']);
        $communes = $contingencies
            ->groupBy(fn (Contingency $contingency) => $contingency->commune?->name ?? 'Sin comuna')
            ->map(fn (Collection $items, string $name) => [
                'name' => $name,
                'incidents' => $items->count(),
                'active' => $items->whereIn('status', self::ACTIVE_STATUSES)->count(),
                'affected' => (int) $items->sum('affected_total'),
            ])
            ->sortByDesc('affected')
            ->values();
        $statuses = $contingencies
            ->groupBy('status')
            ->map(fn (Collection $items, string $status) => [
                'label' => $this->statusLabel($status),
                'total' => $items->count(),
                'percentage' => $contingencies->isEmpty() ? 0 : (int) round(($items->count() / $contingencies->count()) * 100),
            ])
            ->sortByDesc('total')
            ->values();
        $priorities = $contingencies
            ->groupBy('priority')
            ->map(fn (Collection $items, string $priority) => [
                'label' => $this->priorityLabel($priority),
                'total' => $items->count(),
                'percentage' => $contingencies->isEmpty() ? 0 : (int) round(($items->count() / $contingencies->count()) * 100),
            ])
            ->sortByDesc('total')
            ->values();
        $topCommune = $communes->first();
        $peak = $trend->sortByDesc('affected')->first();
        $affected = (int) $contingencies->sum('affected_total');
        $restoredAffected = (int) $contingencies
            ->filter(fn (Contingency $contingency) => $contingency->restored_at !== null)
            ->sum('affected_total');
        $historyCount = (int) $contingencies->sum(fn (Contingency $contingency) => $contingency->relationLoaded('history')
            ? $contingency->history->count()
            : (int) ($contingency->history_count ?? 0));

        return [
            'trend' => $trend->values(),
            'trendChart' => $this->trendChartDataUri($trend),
            'communes' => $communes,
            'statuses' => $statuses,
            'priorities' => $priorities,
            'topCommune' => $topCommune,
            'peak' => $peak,
            'restorationRate' => $affected === 0 ? 0 : (int) round(($restoredAffected / $affected) * 100),
            'historyCount' => $historyCount,
            'averageHistoryEvents' => $contingencies->isEmpty() ? 0 : round($historyCount / $contingencies->count(), 1),
        ];
    }

    private function trend(Collection $contingencies, string $range): Collection
    {
        $periods = [];

        foreach ($contingencies as $contingency) {
            if ($contingency->started_at) {
                $key = $this->periodKey(CarbonImmutable::parse($contingency->started_at), $range);
                $periods[$key] ??= ['key' => $key, 'affected' => 0, 'restored' => 0, 'started' => 0, 'completed' => 0];
                $periods[$key]['affected'] += (int) $contingency->affected_total;
                $periods[$key]['started']++;
            }

            if ($contingency->restored_at) {
                $key = $this->periodKey(CarbonImmutable::parse($contingency->restored_at), $range);
                $periods[$key] ??= ['key' => $key, 'affected' => 0, 'restored' => 0, 'started' => 0, 'completed' => 0];
                $periods[$key]['restored'] += (int) $contingency->affected_total;
                $periods[$key]['completed']++;
            }
        }

        ksort($periods);

        return collect($periods)->map(function (array $period) use ($range) {
            $period['label'] = $this->periodLabel($period['key'], $range);

            return $period;
        })->values();
    }

    private function periodKey(CarbonImmutable $date, string $range): string
    {
        return match ($range) {
            '24h' => $date->startOfHour()->format('Y-m-d H:00:00'),
            '7d', '30d' => $date->startOfDay()->format('Y-m-d 00:00:00'),
            default => $date->startOfMonth()->format('Y-m-01 00:00:00'),
        };
    }

    private function periodLabel(string $key, string $range): string
    {
        $date = CarbonImmutable::parse($key);

        if ($range === '24h') {
            return $date->format('d/m H:i');
        }

        if (in_array($range, ['7d', '30d'], true)) {
            return $date->format('d/m');
        }

        $months = [1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sept', 'oct', 'nov', 'dic'];

        return $months[(int) $date->format('n')].' '.$date->format('Y');
    }

    private function trendChartDataUri(Collection $trend): string
    {
        $width = 930;
        $height = 245;
        $left = 58;
        $top = 26;
        $right = 20;
        $bottom = 42;
        $plotWidth = $width - $left - $right;
        $plotHeight = $height - $top - $bottom;
        $max = max(1, (int) $trend->max(fn (array $point) => max($point['affected'], $point['restored'])));
        $count = max(1, $trend->count());
        $step = $count > 1 ? $plotWidth / ($count - 1) : 0;
        $affectedPoints = [];
        $restoredPoints = [];
        $grid = '';
        $labels = '';
        $markers = '';

        for ($index = 0; $index <= 4; $index++) {
            $value = (int) round($max * (4 - $index) / 4);
            $y = $top + ($plotHeight * $index / 4);
            $grid .= '<line x1="'.$left.'" y1="'.$y.'" x2="'.($width - $right).'" y2="'.$y.'" stroke="#dbe4f0" stroke-width="1" />';
            $grid .= '<text x="'.($left - 8).'" y="'.($y + 4).'" text-anchor="end" font-size="9" fill="#64748b">'.number_format($value, 0, ',', '.').'</text>';
        }

        foreach ($trend->values() as $index => $point) {
            $x = $count === 1 ? $left + ($plotWidth / 2) : $left + ($step * $index);
            $affectedY = $top + $plotHeight - (($point['affected'] / $max) * $plotHeight);
            $restoredY = $top + $plotHeight - (($point['restored'] / $max) * $plotHeight);
            $affectedPoints[] = round($x, 1).','.round($affectedY, 1);
            $restoredPoints[] = round($x, 1).','.round($restoredY, 1);
            $safeLabel = htmlspecialchars($point['label'], ENT_QUOTES | ENT_XML1, 'UTF-8');
            $labels .= '<text x="'.round($x, 1).'" y="'.($height - 15).'" text-anchor="middle" font-size="8" fill="#64748b">'.$safeLabel.'</text>';
            $markers .= '<circle cx="'.round($x, 1).'" cy="'.round($affectedY, 1).'" r="3" fill="#ef4444" stroke="#ffffff" stroke-width="1.2" />';
            $markers .= '<circle cx="'.round($x, 1).'" cy="'.round($restoredY, 1).'" r="3" fill="#10b981" stroke="#ffffff" stroke-width="1.2" />';
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'">'
            .'<rect width="100%" height="100%" fill="#ffffff" />'
            .$grid
            .'<line x1="'.$left.'" y1="'.($top + $plotHeight).'" x2="'.($width - $right).'" y2="'.($top + $plotHeight).'" stroke="#94a3b8" />'
            .'<polyline points="'.implode(' ', $affectedPoints).'" fill="none" stroke="#ef4444" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" />'
            .'<polyline points="'.implode(' ', $restoredPoints).'" fill="none" stroke="#10b981" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" />'
            .$markers.$labels
            .'<circle cx="650" cy="12" r="4" fill="#ef4444" /><text x="660" y="15" font-size="9" fill="#475569">Afectados</text>'
            .'<circle cx="760" cy="12" r="4" fill="#10b981" /><text x="770" y="15" font-size="9" fill="#475569">Repuestos</text>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private function imageDataUri(string $relativePath): string
    {
        $path = public_path($relativePath);

        if (! is_file($path)) {
            return '';
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
    }

    /**
     * @param  array{range: string, commune: ?int, feeder: ?int, priority: ?string, status: ?string, search: string}  $filters
     * @return array<int, array{label: string, value: string}>
     */
    private function filterSummary(array $filters): array
    {
        $items = [[
            'label' => 'Período',
            'value' => match ($filters['range']) {
                '24h' => 'Últimas 24 horas',
                '7d' => 'Últimos 7 días',
                '30d' => 'Últimos 30 días',
                '12m' => 'Últimos 12 meses',
                default => 'Todo el historial',
            },
        ]];

        if ($filters['commune']) {
            $items[] = ['label' => 'Comuna', 'value' => Commune::query()->find($filters['commune'])?->name ?? ''];
        }
        if ($filters['feeder']) {
            $items[] = ['label' => 'Alimentador', 'value' => Feeder::query()->find($filters['feeder'])?->code ?? ''];
        }
        if ($filters['priority']) {
            $items[] = ['label' => 'Criticidad', 'value' => $this->priorityLabel($filters['priority'])];
        }
        if ($filters['status']) {
            $items[] = ['label' => 'Estado', 'value' => $this->statusLabel($filters['status'])];
        }
        if ($filters['search'] !== '') {
            $items[] = ['label' => 'Búsqueda', 'value' => $filters['search']];
        }

        return $items;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'reported' => 'Reportada',
            'assigned' => 'Asignada',
            'in_progress' => 'En atención',
            'restored' => 'Repuesta',
            'closed' => 'Cerrada',
            default => $status,
        };
    }

    private function priorityLabel(string $priority): string
    {
        return match ($priority) {
            'critical' => 'Crítica',
            'high' => 'Alta',
            'medium' => 'Media',
            'low' => 'Baja',
            default => $priority,
        };
    }

    private function causeLabel(?string $cause): string
    {
        return match ($cause) {
            'weather' => 'Clima',
            'vegetation' => 'Vegetación',
            'equipment_failure' => 'Falla de equipo',
            'third_party' => 'Intervención de terceros',
            'vehicle_collision' => 'Choque vehicular',
            'unknown' => 'Sin determinar',
            null, '' => 'Sin informar',
            default => $cause,
        };
    }
}
