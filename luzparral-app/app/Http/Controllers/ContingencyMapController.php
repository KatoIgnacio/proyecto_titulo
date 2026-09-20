<?php

namespace App\Http\Controllers;

use App\Models\Commune;
use App\Models\Contingency;
use App\Models\Feeder;
use App\Services\Maps\ContingencyMapData;
use App\Support\ContingencyPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ContingencyMapController extends Controller
{
    public function __invoke(Request $request, ContingencyMapData $mapData): Response
    {
        $referenceDate = $this->referenceDate();
        $filters = $this->validatedFilters($request, $referenceDate);
        $canViewSensitiveLayers = $request->user()?->role?->canViewSupplyIdentifiers() ?? false;

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
            'mapData' => $mapData->build(
                $filters,
                $referenceDate,
                null,
                10,
                $canViewSensitiveLayers,
            ),
        ]);
    }

    public function data(Request $request, ContingencyMapData $mapData): JsonResponse
    {
        $referenceDate = $this->referenceDate();
        $filters = $this->validatedFilters($request, $referenceDate);
        $viewport = $request->validate([
            'north' => ['required', 'numeric', 'between:-90,90'],
            'south' => ['required', 'numeric', 'between:-90,90'],
            'east' => ['required', 'numeric', 'between:-180,180'],
            'west' => ['required', 'numeric', 'between:-180,180'],
            'zoom' => ['required', 'integer', 'between:6,18'],
        ]);
        $bounds = [
            'north' => (float) $viewport['north'],
            'south' => (float) $viewport['south'],
            'east' => (float) $viewport['east'],
            'west' => (float) $viewport['west'],
        ];

        if ($bounds['north'] <= $bounds['south'] || $bounds['east'] <= $bounds['west']) {
            throw ValidationException::withMessages([
                'viewport' => 'Los límites del área visible no son válidos.',
            ]);
        }

        if (($bounds['north'] - $bounds['south']) > 10 || ($bounds['east'] - $bounds['west']) > 10) {
            throw ValidationException::withMessages([
                'viewport' => 'El área visible solicitada es demasiado extensa.',
            ]);
        }

        $payload = $mapData->build(
            $filters,
            $referenceDate,
            $bounds,
            (int) $viewport['zoom'],
            $request->user()?->role?->canViewSupplyIdentifiers() ?? false,
        );

        return response()
            ->json($payload)
            ->header('Cache-Control', 'private, max-age=15');
    }

    /**
     * @return array{range: string, date_day: ?string, date_month: ?string, date_year: ?string, date_from: ?string, date_to: ?string, commune: ?int, feeder: ?int, priority: ?string, status: string}
     */
    private function validatedFilters(Request $request, CarbonImmutable $referenceDate): array
    {
        $validated = $request->validate([
            ...ContingencyPeriod::validationRules(),
            'commune' => ['nullable', 'integer', 'exists:communes,id'],
            'feeder' => ['nullable', 'integer', 'exists:feeders,id'],
            'priority' => ['nullable', Rule::in(['critical', 'high', 'medium', 'low'])],
            'status' => ['nullable', Rule::in(['active', 'reported', 'assigned', 'in_progress', 'restored', 'closed', 'all'])],
        ]);
        $period = ContingencyPeriod::normalize($validated, $referenceDate);

        return [
            ...$period,
            'commune' => isset($validated['commune']) ? (int) $validated['commune'] : null,
            'feeder' => isset($validated['feeder']) ? (int) $validated['feeder'] : null,
            'priority' => $validated['priority'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ];
    }

    private function referenceDate(): CarbonImmutable
    {
        $latestDatasetDate = Contingency::query()->max('started_at');

        return $latestDatasetDate
            ? CarbonImmutable::parse($latestDatasetDate)
            : CarbonImmutable::now();
    }
}
