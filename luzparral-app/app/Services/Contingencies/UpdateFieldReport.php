<?php

namespace App\Services\Contingencies;

use App\Enums\FieldReportProgress;
use App\Models\Contingency;
use App\Models\FieldReport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateFieldReport
{
    /**
     * @param  array{current_updated_at: string, progress_status: string, description: string, observed_at: string, latitude?: mixed, longitude?: mixed}  $data
     */
    public function execute(Contingency $contingency, FieldReport $fieldReport, User $user, array $data): FieldReport
    {
        return DB::transaction(function () use ($contingency, $fieldReport, $user, $data): FieldReport {
            $lockedReport = FieldReport::query()
                ->lockForUpdate()
                ->findOrFail($fieldReport->getKey());

            abort_unless($lockedReport->contingency_id === $contingency->id, 404);

            $expectedUpdatedAt = CarbonImmutable::parse($data['current_updated_at']);
            if ($lockedReport->updated_at === null || ! $lockedReport->updated_at->equalTo($expectedUpdatedAt)) {
                throw ValidationException::withMessages([
                    'current_updated_at' => 'El antecedente cambió desde que se abrió. Recargue la página antes de continuar.',
                ]);
            }

            $progress = FieldReportProgress::from($data['progress_status']);
            $lockedReport->update([
                'progress_status' => $progress,
                'description' => trim($data['description']),
                'observed_at' => $data['observed_at'],
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
            ]);

            $eventAt = now();
            $contingency->history()->create([
                'status' => $contingency->status,
                'note' => Str::limit(
                    "Antecedente de terreno #{$lockedReport->id} editado: {$progress->label()}. {$lockedReport->description}",
                    300,
                    '',
                ),
                'event_at' => $eventAt,
                'user_id' => $user->id,
                'source' => 'manual',
                'created_at' => $eventAt,
            ]);

            return $lockedReport->refresh()->load(['reporter:id,name', 'attachments']);
        });
    }
}
