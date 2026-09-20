<?php

namespace App\Http\Controllers;

use App\Models\Commune;
use App\Models\Contingency;
use App\Models\Feeder;
use App\Support\ContingencyPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const ACTIVE_STATUSES = ['reported', 'assigned', 'in_progress'];

    /**
     * Display the operational dashboard using the synthetic dataset.
     */
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            ...ContingencyPeriod::validationRules(),
            'commune' => ['nullable', 'integer', 'exists:communes,id'],
            'feeder' => ['nullable', 'integer', 'exists:feeders,id'],
            'priority' => ['nullable', Rule::in(['critical', 'high', 'medium', 'low'])],
            'status' => ['nullable', Rule::in(['reported', 'assigned', 'in_progress', 'restored', 'closed'])],
            'search' => ['nullable', 'string', 'max:80'],
        ]);

        $latestDatasetDate = Contingency::query()->max('started_at');
        $referenceDate = $latestDatasetDate
            ? CarbonImmutable::parse($latestDatasetDate)
            : CarbonImmutable::now();
        $period = ContingencyPeriod::normalize($validated, $referenceDate);
        $filters = [
            ...$period,
            'commune' => isset($validated['commune']) ? (int) $validated['commune'] : null,
            'feeder' => isset($validated['feeder']) ? (int) $validated['feeder'] : null,
            'priority' => $validated['priority'] ?? null,
            'status' => $validated['status'] ?? null,
            'search' => trim($validated['search'] ?? ''),
        ];

        $query = $this->filteredQuery($filters, $referenceDate);
        $activeQuery = (clone $query)->whereIn('contingencies.status', self::ACTIVE_STATUSES);
        $filteredIds = (clone $query)->pluck('contingencies.id');

        $restoredCustomers = DB::table('contingency_impacts')
            ->whereIn('contingency_id', $filteredIds)
            ->whereNotNull('restored_at')
            ->count();

        $averageOutageMinutes = DB::table('contingency_impacts')
            ->whereIn('contingency_id', $filteredIds)
            ->whereNotNull('outage_minutes')
            ->avg('outage_minutes');

        $trend = (clone $query)
            ->selectRaw('DATE(contingencies.started_at) as event_date')
            ->selectRaw('COUNT(*) as incidents')
            ->selectRaw('COALESCE(SUM(contingencies.affected_total), 0) as affected')
            ->selectRaw('COALESCE(SUM(CASE WHEN contingencies.restored_at IS NOT NULL THEN contingencies.affected_total ELSE 0 END), 0) as restored')
            ->groupByRaw('DATE(contingencies.started_at)')
            ->orderBy('event_date')
            ->get()
            ->take(-12)
            ->values()
            ->map(fn ($row) => [
                'date' => $row->event_date,
                'incidents' => (int) $row->incidents,
                'affected' => (int) $row->affected,
                'restored' => (int) $row->restored,
            ]);

        $communeDistribution = (clone $query)
            ->join('communes', 'communes.id', '=', 'contingencies.commune_id')
            ->selectRaw('communes.id, communes.name, COUNT(*) as incidents')
            ->selectRaw('COALESCE(SUM(contingencies.affected_total), 0) as affected')
            ->groupBy('communes.id', 'communes.name')
            ->orderByDesc('affected')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'incidents' => (int) $row->incidents,
                'affected' => (int) $row->affected,
            ]);

        $statusDistribution = (clone $query)
            ->selectRaw('contingencies.status, COUNT(*) as total')
            ->groupBy('contingencies.status')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'total' => (int) $row->total,
            ]);

        $contingencies = (clone $query)
            ->with(['commune:id,name', 'feeder:id,code,name'])
            ->orderByDesc('contingencies.started_at')
            ->limit(10)
            ->get()
            ->map(fn (Contingency $contingency) => [
                'id' => $contingency->id,
                'code' => $contingency->code,
                'osf_code' => $contingency->osf_code,
                'commune' => $contingency->commune?->name,
                'feeder' => $contingency->feeder?->code,
                'status' => $contingency->status,
                'priority' => $contingency->priority,
                'affected_total' => $contingency->affected_total,
                'critical_affected' => $contingency->critical_affected,
                'electrodependent_affected' => $contingency->electrodependent_affected,
                'started_at' => $contingency->started_at?->toIso8601String(),
                'restored_at' => $contingency->restored_at?->toIso8601String(),
            ]);

        return Inertia::render('Dashboard', [
            'filters' => $filters,
            'referenceDate' => $referenceDate->toIso8601String(),
            'filterOptions' => [
                'communes' => Commune::query()
                    ->where('active', true)
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'feeders' => Feeder::query()
                    ->where('active', true)
                    ->orderBy('code')
                    ->get(['id', 'commune_id', 'code', 'name']),
            ],
            'metrics' => [
                'total' => (clone $query)->count(),
                'active' => (clone $activeQuery)->count(),
                'affected' => (int) (clone $activeQuery)->sum('affected_total'),
                'restored' => $restoredCustomers,
                'critical' => (int) (clone $activeQuery)->sum('critical_affected'),
                'electrodependent' => (int) (clone $activeQuery)->sum('electrodependent_affected'),
                'averageMinutes' => $averageOutageMinutes === null ? null : (int) round($averageOutageMinutes),
            ],
            'trend' => $trend,
            'communeDistribution' => $communeDistribution,
            'statusDistribution' => $statusDistribution,
            'contingencies' => $contingencies,
        ]);
    }

    /**
     * Build the common query used by every widget so the dashboard remains consistent.
     *
     * @param  array{range: string, date_day: ?string, date_month: ?string, date_year: ?string, date_from: ?string, date_to: ?string, commune: ?int, feeder: ?int, priority: ?string, status: ?string, search: string}  $filters
     */
    private function filteredQuery(array $filters, CarbonImmutable $referenceDate): Builder
    {
        $query = Contingency::query();
        ContingencyPeriod::apply($query, $filters, $referenceDate);

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
                        ->orWhereHas('feeder', fn (Builder $feederQuery) => $feederQuery
                            ->where('code', 'like', $term)
                            ->orWhere('name', 'like', $term));
                });
            });

        return $query;
    }
}
