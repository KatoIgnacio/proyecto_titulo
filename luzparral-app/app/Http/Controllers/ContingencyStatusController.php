<?php

namespace App\Http\Controllers;

use App\Enums\ContingencyStatus;
use App\Http\Requests\UpdateContingencyStatusRequest;
use App\Models\Contingency;
use App\Services\Contingencies\TransitionContingencyStatus;
use Illuminate\Http\RedirectResponse;

class ContingencyStatusController extends Controller
{
    public function update(
        UpdateContingencyStatusRequest $request,
        Contingency $contingency,
        TransitionContingencyStatus $transition,
    ): RedirectResponse {
        $validated = $request->validated();

        $transition->handle(
            $contingency,
            $request->user(),
            ContingencyStatus::from($validated['status']),
            $validated['note'],
            ContingencyStatus::from($validated['current_status']),
        );

        return back()->with('success', 'El cambio de estado quedó registrado en la bitácora.');
    }
}
