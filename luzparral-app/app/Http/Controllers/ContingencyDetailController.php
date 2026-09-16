<?php

namespace App\Http\Controllers;

use App\Enums\ContingencyStatus;
use App\Enums\FieldReportProgress;
use App\Models\Contingency;
use Inertia\Inertia;
use Inertia\Response;

class ContingencyDetailController extends Controller
{
    /**
     * Display the read-only operational record for one synthetic contingency.
     */
    public function __invoke(Contingency $contingency): Response
    {
        $contingency->load([
            'commune:id,name',
            'feeder:id,code,name',
            'sourceBatch:id,source_name,synthetic_file_name,completed_at',
            'history.user:id,name',
            'fieldReports' => fn ($query) => $query->orderByDesc('observed_at'),
            'fieldReports.reporter:id,name',
            'fieldReports.attachments:id,field_report_id,original_name,mime_type,size_bytes',
        ]);

        $impactSummary = $contingency->impacts()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN restored_at IS NOT NULL THEN 1 ELSE 0 END) as restored')
            ->selectRaw('AVG(outage_minutes) as average_minutes')
            ->first();

        $totalImpacts = (int) ($impactSummary?->total ?? 0);
        $restoredImpacts = (int) ($impactSummary?->restored ?? 0);
        $currentStatus = ContingencyStatus::tryFrom($contingency->status);

        return Inertia::render('Contingencies/Show', [
            'contingency' => [
                'id' => $contingency->id,
                'code' => $contingency->code,
                'osf_code' => $contingency->osf_code,
                'commune' => $contingency->commune?->name,
                'feeder' => [
                    'code' => $contingency->feeder?->code,
                    'name' => $contingency->feeder?->name,
                ],
                'status' => $contingency->status,
                'priority' => $contingency->priority,
                'cause' => $contingency->cause,
                'description' => $contingency->description,
                'started_at' => $contingency->started_at?->toIso8601String(),
                'estimated_restore_at' => $contingency->estimated_restore_at?->toIso8601String(),
                'restored_at' => $contingency->restored_at?->toIso8601String(),
                'latitude' => (float) $contingency->latitude,
                'longitude' => (float) $contingency->longitude,
                'affected_total' => $contingency->affected_total,
                'critical_affected' => $contingency->critical_affected,
                'electrodependent_affected' => $contingency->electrodependent_affected,
                'created_at' => $contingency->created_at?->toIso8601String(),
            ],
            'impactSummary' => [
                'registered' => $totalImpacts,
                'restored' => $restoredImpacts,
                'pending' => max(0, $totalImpacts - $restoredImpacts),
                'averageMinutes' => $impactSummary?->average_minutes === null
                    ? null
                    : (int) round($impactSummary->average_minutes),
            ],
            'history' => $contingency->history
                ->sortByDesc('event_at')
                ->values()
                ->map(fn ($event) => [
                    'id' => $event->id,
                    'status' => $event->status,
                    'note' => $event->note,
                    'event_at' => $event->event_at?->toIso8601String(),
                    'source' => $event->source,
                    'user' => $event->user?->name,
                ]),
            'fieldReports' => $contingency->fieldReports
                ->map(fn ($report) => [
                    'id' => $report->id,
                    'progress_status' => $report->progress_status->value,
                    'progress_label' => $report->progress_status->label(),
                    'description' => $report->description,
                    'observed_at' => $report->observed_at?->toIso8601String(),
                    'latitude' => $report->latitude === null ? null : (float) $report->latitude,
                    'longitude' => $report->longitude === null ? null : (float) $report->longitude,
                    'reporter' => $report->reporter?->name,
                    'attachments' => $report->attachments->map(fn ($attachment) => [
                        'id' => $attachment->id,
                        'name' => $attachment->original_name,
                        'mime_type' => $attachment->mime_type,
                        'size_bytes' => $attachment->size_bytes,
                        'download_url' => route('field-reports.attachments.download', $attachment),
                    ])->values(),
                ])->values(),
            'fieldReportProgressOptions' => collect(FieldReportProgress::cases())
                ->map(fn (FieldReportProgress $progress) => [
                    'value' => $progress->value,
                    'label' => $progress->label(),
                ]),
            'availableStatusTransitions' => collect($currentStatus?->allowedTransitions() ?? [])
                ->map(fn (ContingencyStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->values(),
            'source' => $contingency->sourceBatch ? [
                'name' => $contingency->sourceBatch->source_name,
                'file' => $contingency->sourceBatch->synthetic_file_name,
                'completed_at' => $contingency->sourceBatch->completed_at?->toIso8601String(),
            ] : null,
            'contingencyOptions' => Contingency::query()
                ->orderByDesc('started_at')
                ->get(['id', 'code', 'status'])
                ->map(fn (Contingency $option) => [
                    'id' => $option->id,
                    'code' => $option->code,
                    'status' => $option->status,
                ]),
        ]);
    }
}
