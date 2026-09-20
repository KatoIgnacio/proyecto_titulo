<?php

namespace App\Services\Contingencies;

use App\Models\Contingency;
use App\Models\FieldReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DeleteFieldReport
{
    public function execute(Contingency $contingency, FieldReport $fieldReport, User $user): void
    {
        $attachments = collect();

        DB::transaction(function () use ($contingency, $fieldReport, $user, &$attachments): void {
            $lockedReport = FieldReport::query()
                ->with('attachments')
                ->lockForUpdate()
                ->findOrFail($fieldReport->getKey());

            abort_unless($lockedReport->contingency_id === $contingency->id, 404);

            $attachments = $lockedReport->attachments
                ->map(fn ($attachment) => ['disk' => $attachment->disk, 'path' => $attachment->path]);
            $progressLabel = $lockedReport->progress_status->label();
            $description = $lockedReport->description;
            $reportId = $lockedReport->id;

            $lockedReport->delete();

            $eventAt = now();
            $contingency->history()->create([
                'status' => $contingency->status,
                'note' => Str::limit(
                    "Antecedente de terreno #{$reportId} eliminado: {$progressLabel}. {$description}",
                    300,
                    '',
                ),
                'event_at' => $eventAt,
                'user_id' => $user->id,
                'source' => 'manual',
                'created_at' => $eventAt,
            ]);
        });

        $attachments->each(
            fn (array $attachment) => Storage::disk($attachment['disk'])->delete($attachment['path']),
        );
    }
}
