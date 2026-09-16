<?php

namespace App\Services\Contingencies;

use App\Enums\ContingencyStatus;
use App\Models\Contingency;
use App\Models\ContingencyHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionContingencyStatus
{
    public function handle(
        Contingency $contingency,
        User $user,
        ContingencyStatus $target,
        string $note,
        ContingencyStatus $expectedCurrent,
    ): ContingencyHistory {
        return DB::transaction(function () use ($contingency, $user, $target, $note, $expectedCurrent) {
            $lockedContingency = Contingency::query()
                ->lockForUpdate()
                ->findOrFail($contingency->getKey());

            $current = ContingencyStatus::tryFrom($lockedContingency->status);

            if ($current === null) {
                throw ValidationException::withMessages([
                    'status' => 'La contingencia posee un estado no reconocido. No se realizaron cambios.',
                ]);
            }

            if ($current !== $expectedCurrent) {
                throw ValidationException::withMessages([
                    'current_status' => 'El estado cambió desde que se abrió el expediente. Recargue la página antes de continuar.',
                ]);
            }

            if (! $current->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => "No se permite cambiar de {$current->label()} a {$target->label()}.",
                ]);
            }

            $eventAt = now();
            $lockedContingency->status = $target->value;

            if ($target === ContingencyStatus::Restored && $lockedContingency->restored_at === null) {
                $lockedContingency->restored_at = $eventAt;
            }

            $lockedContingency->save();

            return $lockedContingency->history()->create([
                'status' => $target->value,
                'note' => trim($note),
                'event_at' => $eventAt,
                'user_id' => $user->id,
                'source' => 'manual',
                'created_at' => $eventAt,
            ]);
        });
    }
}
