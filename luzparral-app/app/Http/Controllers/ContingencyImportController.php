<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewContingencyImportRequest;
use App\Http\Requests\StoreContingencyImportRequest;
use App\Models\ImportBatch;
use App\Services\Imports\ContingencyImportManager;
use App\Services\Imports\ImportContingencies;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ContingencyImportController extends Controller
{
    public function index(Request $request, ContingencyImportManager $manager): Response
    {
        $recentBatches = ImportBatch::query()
            ->with('importedBy:id,name')
            ->withCount('errors')
            ->orderByDesc('started_at')
            ->limit(10)
            ->get();
        $selectedBatch = null;

        if ($request->filled('batch')) {
            $selected = ImportBatch::query()
                ->with('importedBy:id,name')
                ->withCount('errors')
                ->findOrFail($request->integer('batch'));
            $selectedBatch = $this->batchData($selected) + [
                'errors' => $selected->errors()
                    ->orderBy('source_row_number')
                    ->orderBy('id')
                    ->limit(100)
                    ->get()
                    ->map(fn ($error) => [
                        'row' => $error->source_row_number,
                        'field' => $error->field_name,
                        'code' => $error->error_code,
                        'message' => $error->message,
                        'reference' => $error->synthetic_reference,
                    ])->values(),
                'errors_truncated' => max(0, $selected->errors_count - 100),
            ];
        }

        return Inertia::render('Imports/Index', [
            'sources' => $manager->options(),
            'recentBatches' => $recentBatches->map(fn (ImportBatch $batch) => $this->batchData($batch)),
            'selectedBatch' => $selectedBatch,
        ]);
    }

    public function preview(
        PreviewContingencyImportRequest $request,
        ContingencyImportManager $manager,
    ): JsonResponse {
        $preview = $manager
            ->adapter($request->string('source')->toString())
            ->preview($request->file('file'));

        return response()->json($preview->toPublicArray());
    }

    public function store(
        StoreContingencyImportRequest $request,
        ImportContingencies $importer,
    ): RedirectResponse {
        $batch = $importer->execute(
            $request->string('source')->toString(),
            $request->file('file'),
            $request->string('checksum')->toString(),
            $request->user(),
        );

        return redirect()
            ->route('imports.index', ['batch' => $batch->id])
            ->with('success', $this->importResultMessage($batch));
    }

    public function template(): HttpResponse
    {
        $header = 'codigo;osf;comuna;alimentador;estado;prioridad;causa;descripcion;inicio;reposicion_estimada;reposicion_efectiva;latitud;longitud';
        $example = 'SYN-IMPORT-EJEMPLO-001;SYN-OSF-EJEMPLO-001;COM-01;SYN-AL-01;reported;medium;unknown;Contingencia sintetica de ejemplo;2026-09-16 08:00:00;2026-09-16 12:00:00;;-36.1430000;-71.8260000';
        $contents = "\xEF\xBB\xBF{$header}\r\n{$example}\r\n";

        return response($contents, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla_importacion_sigcel.csv"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array<string, mixed> */
    private function batchData(ImportBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'source' => $batch->source_name,
            'file' => $batch->synthetic_file_name,
            'status' => $batch->status,
            'total_rows' => $batch->total_rows,
            'accepted_rows' => $batch->accepted_rows,
            'rejected_rows' => $batch->rejected_rows,
            'errors_count' => $batch->errors_count ?? $batch->errors()->count(),
            'started_at' => $batch->started_at?->toIso8601String(),
            'completed_at' => $batch->completed_at?->toIso8601String(),
            'user' => $batch->importedBy?->name,
            'reference' => 'IMP-'.Str::padLeft((string) $batch->id, 6, '0'),
        ];
    }

    private function importResultMessage(ImportBatch $batch): string
    {
        $reference = 'IMP-'.Str::padLeft((string) $batch->id, 6, '0');

        return match ($batch->status) {
            'completed' => "Importación {$reference} completada. Filas incorporadas: {$batch->accepted_rows}.",
            'completed_with_warnings' => "Importación {$reference} completada con observaciones. Filas incorporadas: {$batch->accepted_rows}; filas rechazadas: {$batch->rejected_rows}. Revise los motivos indicados.",
            default => "Importación {$reference} rechazada. No se incorporaron filas; {$batch->rejected_rows} filas fueron rechazadas. Revise los motivos indicados.",
        };
    }
}
