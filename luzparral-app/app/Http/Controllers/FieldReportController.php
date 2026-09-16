<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFieldReportRequest;
use App\Models\Contingency;
use App\Services\Contingencies\StoreFieldReport;
use Illuminate\Http\RedirectResponse;

class FieldReportController extends Controller
{
    public function store(
        StoreFieldReportRequest $request,
        Contingency $contingency,
        StoreFieldReport $storeFieldReport,
    ): RedirectResponse {
        $validated = $request->validated();
        unset($validated['attachments']);

        $storeFieldReport->execute(
            $contingency,
            $request->user(),
            $validated,
            $request->file('attachments', []),
        );

        return back()->with('success', 'Antecedente de terreno registrado correctamente.');
    }
}
