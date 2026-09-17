<?php

namespace App\Http\Controllers;

use App\Models\Contingency;
use App\Models\SupplyPoint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OperationalSearchController extends Controller
{
    /**
     * Search the synthetic operational contingency records.
     */
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'category' => ['nullable', Rule::in(['all', 'code', 'osf', 'commune', 'feeder', 'description', 'customer', 'supply'])],
            'query' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(['reported', 'assigned', 'in_progress', 'restored', 'closed'])],
            'priority' => ['nullable', Rule::in(['critical', 'high', 'medium', 'low'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $filters = [
            'category' => $validated['category'] ?? 'all',
            'query' => trim($validated['query'] ?? ''),
            'status' => $validated['status'] ?? null,
            'priority' => $validated['priority'] ?? null,
        ];

        $hasSearched = $filters['query'] !== ''
            || $filters['status'] !== null
            || $filters['priority'] !== null;
        $supplySearch = in_array($filters['category'], ['customer', 'supply'], true);

        if ($supplySearch && ! ($request->user()?->role?->canViewSupplyIdentifiers() ?? false)) {
            abort(403);
        }

        if ($supplySearch && mb_strlen($filters['query'], 'UTF-8') < 3) {
            throw ValidationException::withMessages([
                'query' => 'Ingrese al menos tres caracteres del código sintético.',
            ]);
        }

        $emptyResults = [
            'data' => [],
            'total' => 0,
            'from' => null,
            'to' => null,
            'currentPage' => 1,
            'lastPage' => 1,
            'previousPageUrl' => null,
            'nextPageUrl' => null,
        ];

        if (! $hasSearched) {
            return Inertia::render('Contingencies/Search', [
                'filters' => $filters,
                'hasSearched' => false,
                'results' => $emptyResults,
            ]);
        }

        if ($supplySearch) {
            return $this->supplyPointResults($filters);
        }

        $query = Contingency::query()
            ->with(['commune:id,name', 'feeder:id,code,name'])
            ->when($filters['status'], fn (Builder $builder, string $status) => $builder->where('status', $status))
            ->when($filters['priority'], fn (Builder $builder, string $priority) => $builder->where('priority', $priority));

        if ($filters['query'] !== '') {
            $this->applyTextSearch($query, $filters['category'], $filters['query']);
        }

        $paginator = $query
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Contingencies/Search', [
            'filters' => $filters,
            'hasSearched' => true,
            'results' => [
                'data' => collect($paginator->items())->map(fn (Contingency $contingency) => [
                    'result_type' => 'contingency',
                    'id' => $contingency->id,
                    'code' => $contingency->code,
                    'osf_code' => $contingency->osf_code,
                    'commune' => $contingency->commune?->name,
                    'feeder' => $contingency->feeder?->code,
                    'status' => $contingency->status,
                    'priority' => $contingency->priority,
                    'description' => $contingency->description,
                    'affected_total' => $contingency->affected_total,
                    'started_at' => $contingency->started_at?->toIso8601String(),
                ])->values(),
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

    /**
     * @param  array{category: string, query: string, status: ?string, priority: ?string}  $filters
     */
    private function supplyPointResults(array $filters): Response
    {
        $column = $filters['category'] === 'customer' ? 'customer_code' : 'synthetic_code';
        $paginator = SupplyPoint::query()
            ->with(['commune:id,name', 'feeder:id,code,name'])
            ->withCount('impacts')
            ->where($column, 'like', mb_strtoupper($filters['query'], 'UTF-8').'%')
            ->orderBy($column)
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Contingencies/Search', [
            'filters' => $filters,
            'hasSearched' => true,
            'results' => [
                'data' => collect($paginator->items())->map(fn (SupplyPoint $point) => [
                    'result_type' => 'supply_point',
                    'id' => $point->id,
                    'supply_code' => $point->synthetic_code,
                    'customer_code' => $point->customer_code,
                    'commune' => $point->commune?->name,
                    'feeder' => $point->feeder?->code,
                    'criticality' => $point->criticality,
                    'active' => $point->active,
                    'related_contingencies' => $point->impacts_count,
                ])->values(),
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

    private function applyTextSearch(Builder $query, string $category, string $search): void
    {
        $term = '%'.$search.'%';

        match ($category) {
            'code' => $query->where('code', 'like', $term),
            'osf' => $query->where('osf_code', 'like', $term),
            'commune' => $query->whereHas('commune', fn (Builder $commune) => $commune->where('name', 'like', $term)),
            'feeder' => $query->whereHas('feeder', fn (Builder $feeder) => $feeder
                ->where('code', 'like', $term)
                ->orWhere('name', 'like', $term)),
            'description' => $query->where(function (Builder $description) use ($term) {
                $description
                    ->where('description', 'like', $term)
                    ->orWhere('cause', 'like', $term);
            }),
            default => $query->where(function (Builder $searchQuery) use ($term) {
                $searchQuery
                    ->where('code', 'like', $term)
                    ->orWhere('osf_code', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('cause', 'like', $term)
                    ->orWhereHas('commune', fn (Builder $commune) => $commune->where('name', 'like', $term))
                    ->orWhereHas('feeder', fn (Builder $feeder) => $feeder
                        ->where('code', 'like', $term)
                        ->orWhere('name', 'like', $term));
            }),
        };
    }
}
