<?php

namespace App\Http\Controllers;

use App\Models\Commune;
use App\Models\Contingency;
use App\Models\Feeder;
use App\Support\ContingencyPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContingencyMapController extends Controller
{
    private const ACTIVE_STATUSES = ['reported', 'assigned', 'in_progress'];

    /**
     * Display georeferenced synthetic contingencies.
     */
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            ...ContingencyPeriod::validationRules(),
            'commune' => ['nullable', 'integer', 'exists:communes,id'],
            'feeder' => ['nullable', 'integer', 'exists:feeders,id'],
            'priority' => ['nullable', Rule::in(['critical', 'high', 'medium', 'low'])],
            'status' => ['nullable', Rule::in(['active', 'reported', 'assigned', 'in_progress', 'restored', 'closed', 'all'])],
        ]);

        $period = ContingencyPeriod::normalize($validated);
        $filters = [
            ...$period,
            'commune' => isset($validated['commune']) ? (int) $validated['commune'] : null,
            'feeder' => isset($validated['feeder']) ? (int) $validated['feeder'] : null,
            'priority' => $validated['priority'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ];

        $latestDatasetDate = Contingency::query()->max('started_at');
        $referenceDate = $latestDatasetDate
            ? CarbonImmutable::parse($latestDatasetDate)
            : CarbonImmutable::now();

        $query = Contingency::query()->with(['commune:id,name', 'feeder:id,code,name']);
        ContingencyPeriod::apply($query, $filters, $referenceDate, 'started_at');

        $query
            ->when($filters['commune'], fn (Builder $builder, int $commune) => $builder->where('commune_id', $commune))
            ->when($filters['feeder'], fn (Builder $builder, int $feeder) => $builder->where('feeder_id', $feeder))
            ->when($filters['priority'], fn (Builder $builder, string $priority) => $builder->where('priority', $priority));

        if ($filters['status'] === 'active') {
            $query->whereIn('status', self::ACTIVE_STATUSES);
        } elseif ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        $contingencies = $query
            ->orderByDesc('started_at')
            ->get()
            ->map(fn (Contingency $contingency) => [
                'id' => $contingency->id,
                'code' => $contingency->code,
                'osf_code' => $contingency->osf_code,
                'commune' => $contingency->commune?->name,
                'feeder' => $contingency->feeder?->code,
                'status' => $contingency->status,
                'priority' => $contingency->priority,
                'cause' => $contingency->cause,
                'description' => $contingency->description,
                'latitude' => (float) $contingency->latitude,
                'longitude' => (float) $contingency->longitude,
                'affected_total' => $contingency->affected_total,
                'critical_affected' => $contingency->critical_affected,
                'electrodependent_affected' => $contingency->electrodependent_affected,
                'started_at' => $contingency->started_at?->toIso8601String(),
                'estimated_restore_at' => $contingency->estimated_restore_at?->toIso8601String(),
                'restored_at' => $contingency->restored_at?->toIso8601String(),
            ]);

        return Inertia::render('Contingencies/Map', [
            'filters' => $filters,
            'referenceDate' => $referenceDate->toIso8601String(),
            'filterOptions' => [
                'communes' => Commune::query()
                    ->where('active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'center_lat', 'center_lon']),
                'feeders' => Feeder::query()
                    ->where('active', true)
                    ->orderBy('code')
                    ->get(['id', 'commune_id', 'code', 'name']),
            ],
            'summary' => [
                'events' => $contingencies->count(),
                'affected' => $contingencies->sum('affected_total'),
                'critical' => $contingencies->sum('critical_affected'),
                'electrodependent' => $contingencies->sum('electrodependent_affected'),
            ],
            'contingencies' => $contingencies,
        ]);
    }
}
