<?php

namespace App\Http\Controllers;

use App\Models\Contingency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            'category' => ['nullable', Rule::in(['all', 'code', 'osf', 'commune', 'feeder', 'description'])],
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
