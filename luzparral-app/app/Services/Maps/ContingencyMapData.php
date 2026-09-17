<?php

namespace App\Services\Maps;

use App\Models\Contingency;
use App\Support\ContingencyPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContingencyMapData
{
    private const ACTIVE_STATUSES = ['reported', 'assigned', 'in_progress'];

    private const MAX_FEATURES = 400;

    private const MAX_ZONES_PER_LAYER = 200;

    /**
     * @param  array{range: string, date_day: ?string, date_month: ?string, date_year: ?string, date_from: ?string, date_to: ?string, commune: ?int, feeder: ?int, priority: ?string, status: string}  $filters
     * @param  array{north: float, south: float, east: float, west: float}|null  $bounds
     * @return array<string, mixed>
     */
    public function build(
        array $filters,
        CarbonImmutable $referenceDate,
        ?array $bounds,
        int $zoom,
        bool $canViewSensitiveLayers,
    ): array {
        $query = $this->filteredQuery($filters, $referenceDate, $bounds);
        $summary = $this->summary($query);
        [$features, $featuresTruncated] = $this->contingencyFeatures($query, $zoom);

        $criticalZones = [];
        $electrodependentZones = [];
        $zonesTruncated = false;

        if ($canViewSensitiveLayers) {
            [$criticalZones, $criticalTruncated] = $this->affectedZones(
                $query,
                $bounds,
                ['critical', 'critical_electrodependent'],
                'critical',
            );
            [$electrodependentZones, $electrodependentTruncated] = $this->affectedZones(
                $query,
                $bounds,
                ['electrodependent', 'critical_electrodependent'],
                'electrodependent',
            );
            $zonesTruncated = $criticalTruncated || $electrodependentTruncated;
        }

        return [
            'summary' => $summary,
            'features' => $features,
            'layers' => [
                'critical_zones' => $criticalZones,
                'electrodependent_zones' => $electrodependentZones,
            ],
            'meta' => [
                'zoom' => $zoom,
                'bounds_applied' => $bounds !== null,
                'feature_limit' => self::MAX_FEATURES,
                'features_truncated' => $featuresTruncated,
                'zones_truncated' => $zonesTruncated,
                'can_view_sensitive_layers' => $canViewSensitiveLayers,
            ],
        ];
    }

    /**
     * @param  array{range: string, date_day: ?string, date_month: ?string, date_year: ?string, date_from: ?string, date_to: ?string, commune: ?int, feeder: ?int, priority: ?string, status: string}  $filters
     * @param  array{north: float, south: float, east: float, west: float}|null  $bounds
     */
    private function filteredQuery(array $filters, CarbonImmutable $referenceDate, ?array $bounds): Builder
    {
        $query = Contingency::query();
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

        if ($bounds !== null) {
            $query
                ->whereBetween('latitude', [$bounds['south'], $bounds['north']])
                ->whereBetween('longitude', [$bounds['west'], $bounds['east']]);
        }

        return $query;
    }

    /** @return array{events: int, affected: int, critical: int, electrodependent: int} */
    private function summary(Builder $query): array
    {
        $row = (clone $query)
            ->selectRaw('COUNT(*) AS events')
            ->selectRaw('COALESCE(SUM(affected_total), 0) AS affected')
            ->selectRaw('COALESCE(SUM(critical_affected), 0) AS critical')
            ->selectRaw('COALESCE(SUM(electrodependent_affected), 0) AS electrodependent')
            ->first();

        return [
            'events' => (int) ($row?->events ?? 0),
            'affected' => (int) ($row?->affected ?? 0),
            'critical' => (int) ($row?->critical ?? 0),
            'electrodependent' => (int) ($row?->electrodependent ?? 0),
        ];
    }

    /** @return array{Collection<int, array<string, mixed>>, bool} */
    private function contingencyFeatures(Builder $query, int $zoom): array
    {
        $precision = $this->precisionForZoom($zoom);
        $groupExpression = "ROUND(latitude, {$precision}), ROUND(longitude, {$precision})";
        $rows = (clone $query)
            ->selectRaw("ROUND(latitude, {$precision}) AS cluster_latitude")
            ->selectRaw("ROUND(longitude, {$precision}) AS cluster_longitude")
            ->selectRaw('MIN(id) AS representative_id')
            ->selectRaw('COUNT(*) AS event_count')
            ->selectRaw('COALESCE(SUM(affected_total), 0) AS affected_total')
            ->selectRaw('COALESCE(SUM(critical_affected), 0) AS critical_affected')
            ->selectRaw('COALESCE(SUM(electrodependent_affected), 0) AS electrodependent_affected')
            ->selectRaw("SUM(CASE WHEN priority = 'critical' THEN 1 ELSE 0 END) AS critical_events")
            ->selectRaw("SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) AS high_events")
            ->selectRaw("SUM(CASE WHEN priority = 'medium' THEN 1 ELSE 0 END) AS medium_events")
            ->groupByRaw($groupExpression)
            ->orderByDesc('event_count')
            ->orderBy('cluster_latitude')
            ->orderBy('cluster_longitude')
            ->limit(self::MAX_FEATURES + 1)
            ->get();

        $truncated = $rows->count() > self::MAX_FEATURES;
        $rows = $rows->take(self::MAX_FEATURES)->values();
        $representatives = Contingency::query()
            ->with(['commune:id,name', 'feeder:id,code'])
            ->whereIn('id', $rows->pluck('representative_id'))
            ->get()
            ->keyBy('id');

        $features = $rows->map(function ($row) use ($representatives): array {
            $eventCount = (int) $row->event_count;
            $representative = $representatives->get((int) $row->representative_id);

            return [
                'key' => sprintf('%.5f:%.5f', (float) $row->cluster_latitude, (float) $row->cluster_longitude),
                'latitude' => (float) $row->cluster_latitude,
                'longitude' => (float) $row->cluster_longitude,
                'event_count' => $eventCount,
                'affected_total' => (int) $row->affected_total,
                'critical_affected' => (int) $row->critical_affected,
                'electrodependent_affected' => (int) $row->electrodependent_affected,
                'priority' => $this->dominantPriority($row),
                'contingency' => $eventCount === 1 && $representative
                    ? $this->contingencyData($representative)
                    : null,
            ];
        });

        return [$features, $truncated];
    }

    /**
     * @param  array{north: float, south: float, east: float, west: float}|null  $bounds
     * @param  list<string>  $criticalities
     * @return array{Collection<int, array<string, mixed>>, bool}
     */
    private function affectedZones(
        Builder $query,
        ?array $bounds,
        array $criticalities,
        string $layer,
    ): array {
        $precision = 2;
        $groupExpression = "ROUND(sp.latitude, {$precision}), ROUND(sp.longitude, {$precision})";
        $filteredContingencies = (clone $query)->select('contingencies.id')->toBase();
        $zoneQuery = DB::table('contingency_impacts as ci')
            ->joinSub($filteredContingencies, 'filtered_contingencies', fn ($join) => $join
                ->on('filtered_contingencies.id', '=', 'ci.contingency_id'))
            ->join('supply_points as sp', 'sp.id', '=', 'ci.supply_point_id')
            ->whereIn('sp.criticality', $criticalities)
            ->where('sp.active', true);

        if ($bounds !== null) {
            $zoneQuery
                ->whereBetween('sp.latitude', [$bounds['south'], $bounds['north']])
                ->whereBetween('sp.longitude', [$bounds['west'], $bounds['east']]);
        }

        $rows = $zoneQuery
            ->selectRaw("ROUND(sp.latitude, {$precision}) AS zone_latitude")
            ->selectRaw("ROUND(sp.longitude, {$precision}) AS zone_longitude")
            ->selectRaw('COUNT(DISTINCT sp.id) AS supply_points')
            ->selectRaw('COUNT(DISTINCT ci.contingency_id) AS contingencies')
            ->groupByRaw($groupExpression)
            ->orderByDesc('supply_points')
            ->limit(self::MAX_ZONES_PER_LAYER + 1)
            ->get();

        $truncated = $rows->count() > self::MAX_ZONES_PER_LAYER;
        $zones = $rows->take(self::MAX_ZONES_PER_LAYER)->values()->map(fn ($row): array => [
            'key' => $layer.':'.sprintf('%.5f:%.5f', (float) $row->zone_latitude, (float) $row->zone_longitude),
            'latitude' => (float) $row->zone_latitude,
            'longitude' => (float) $row->zone_longitude,
            'supply_points' => (int) $row->supply_points,
            'contingencies' => (int) $row->contingencies,
        ]);

        return [$zones, $truncated];
    }

    private function precisionForZoom(int $zoom): int
    {
        return match (true) {
            $zoom <= 8 => 1,
            $zoom <= 11 => 2,
            $zoom <= 14 => 3,
            default => 4,
        };
    }

    private function dominantPriority(object $row): string
    {
        return match (true) {
            (int) $row->critical_events > 0 => 'critical',
            (int) $row->high_events > 0 => 'high',
            (int) $row->medium_events > 0 => 'medium',
            default => 'low',
        };
    }

    /** @return array<string, mixed> */
    private function contingencyData(Contingency $contingency): array
    {
        return [
            'id' => $contingency->id,
            'code' => $contingency->code,
            'osf_code' => $contingency->osf_code,
            'commune' => $contingency->commune?->name,
            'feeder' => $contingency->feeder?->code,
            'status' => $contingency->status,
            'priority' => $contingency->priority,
            'cause' => $contingency->cause,
            'description' => $contingency->description,
            'affected_total' => $contingency->affected_total,
            'critical_affected' => $contingency->critical_affected,
            'electrodependent_affected' => $contingency->electrodependent_affected,
            'started_at' => $contingency->started_at?->toIso8601String(),
            'estimated_restore_at' => $contingency->estimated_restore_at?->toIso8601String(),
            'restored_at' => $contingency->restored_at?->toIso8601String(),
        ];
    }
}
