<?php

namespace App\Services\Reports;

use App\Models\Commune;
use App\Models\Contingency;
use App\Models\Feeder;
use App\Support\ContingencyPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContingencyReportData
{
    private const ACTIVE_STATUSES = ['reported', 'assigned', 'in_progress'];

    public function __construct(private readonly TrendChartRenderer $chartRenderer) {}

    public function referenceDate(): CarbonImmutable
    {
        $latestDatasetDate = Contingency::query()->max('started_at');

        return $latestDatasetDate
            ? CarbonImmutable::parse($latestDatasetDate)
            : CarbonImmutable::now('America/Santiago');
    }

    /**
     * @param  array{range: string, date_day: ?string, date_month: ?string, date_year: ?string, date_from: ?string, date_to: ?string, commune: ?int, feeder: ?int, priority: ?string, status: ?string, search: string}  $filters
     */
    public function filteredQuery(array $filters, CarbonImmutable $referenceDate): Builder
    {
        $query = Contingency::query();
        ContingencyPeriod::apply($query, $filters, $referenceDate);

        $query
            ->when($filters['commune'], fn (Builder $builder, int $commune) => $builder->where('contingencies.commune_id', $commune))
            ->when($filters['feeder'], fn (Builder $builder, int $feeder) => $builder->where('contingencies.feeder_id', $feeder))
            ->when($filters['priority'], fn (Builder $builder, string $priority) => $builder->where('contingencies.priority', $priority))
            ->when($filters['status'], fn (Builder $builder, string $status) => $builder->where('contingencies.status', $status))
            ->when($filters['search'], function (Builder $builder, string $search): void {
                $term = '%'.$search.'%';

                $builder->where(function (Builder $searchQuery) use ($term): void {
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
    public function summary(Builder $query): array
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
     * @param  array{range: string, date_day: ?string, date_month: ?string, date_year: ?string, date_from: ?string, date_to: ?string, commune: ?int, feeder: ?int, priority: ?string, status: ?string, search: string}  $filters
     */
    public function reportCollection(array $filters): Collection
    {
        return $this->filteredQuery($filters, $this->referenceDate())
            ->with(['commune:id,name', 'feeder:id,code,name'])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get();
    }

    public function pdfCollection(Builder $query, bool $includesDetail): Collection
    {
        $relations = ['commune:id,name', 'feeder:id,code,name'];

        if ($includesDetail) {
            $relations[] = 'history.user:id,name';
        }

        return (clone $query)
            ->with($relations)
            ->withCount('history')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get();
    }

    /** @return array<string, Collection> */
    public function filterOptions(): array
    {
        return [
            'communes' => Commune::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'feeders' => Feeder::query()->where('active', true)->orderBy('code')->get(['id', 'commune_id', 'code', 'name']),
        ];
    }

    /** @return array<string, mixed> */
    public function pageRow(Contingency $contingency): array
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

    public function exportRows(Collection $contingencies, ?CarbonImmutable $referenceDate = null): Collection
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
     * @param  array{range: string, date_day: ?string, date_month: ?string, date_year: ?string, date_from: ?string, date_to: ?string, commune: ?int, feeder: ?int, priority: ?string, status: ?string, search: string}  $filters
     * @return array<string, mixed>
     */
    public function analytics(Collection $contingencies, array $filters): array
    {
        $trend = $this->trend($contingencies, ContingencyPeriod::trendRange($filters));
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
        $affected = (int) $contingencies->sum('affected_total');
        $restoredAffected = (int) $contingencies
            ->filter(fn (Contingency $contingency) => $contingency->restored_at !== null)
            ->sum('affected_total');
        $historyCount = (int) $contingencies->sum(fn (Contingency $contingency) => $contingency->relationLoaded('history')
            ? $contingency->history->count()
            : (int) ($contingency->history_count ?? 0));

        return [
            'trend' => $trend->values(),
            'trendChart' => $this->chartRenderer->render($trend),
            'communes' => $communes,
            'statuses' => $statuses,
            'priorities' => $priorities,
            'topCommune' => $communes->first(),
            'peak' => $trend->sortByDesc('affected')->first(),
            'restorationRate' => $affected === 0 ? 0 : (int) round(($restoredAffected / $affected) * 100),
            'historyCount' => $historyCount,
            'averageHistoryEvents' => $contingencies->isEmpty() ? 0 : round($historyCount / $contingencies->count(), 1),
        ];
    }

    /**
     * @param  array{range: string, date_day: ?string, date_month: ?string, date_year: ?string, date_from: ?string, date_to: ?string, commune: ?int, feeder: ?int, priority: ?string, status: ?string, search: string}  $filters
     * @return array<int, array{label: string, value: string}>
     */
    public function filterSummary(array $filters): array
    {
        $items = [[
            'label' => 'Período',
            'value' => ContingencyPeriod::label($filters),
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

    public function imageDataUri(string $relativePath): string
    {
        $path = public_path($relativePath);

        if (! is_file($path)) {
            return '';
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
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

        return collect($periods)->map(function (array $period) use ($range): array {
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
