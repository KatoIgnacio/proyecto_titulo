<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteFieldReportRequest;
use App\Http\Requests\StoreFieldReportRequest;
use App\Http\Requests\UpdateFieldReportRequest;
use App\Models\Contingency;
use App\Models\FieldReport;
use App\Services\Contingencies\DeleteFieldReport;
use App\Services\Contingencies\StoreFieldReport;
use App\Services\Contingencies\UpdateFieldReport;
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

    public function update(
        UpdateFieldReportRequest $request,
        Contingency $contingency,
        FieldReport $fieldReport,
        UpdateFieldReport $updateFieldReport,
    ): RedirectResponse {
        abort_unless($fieldReport->contingency_id === $contingency->id, 404);

        $updateFieldReport->execute(
            $contingency,
            $fieldReport,
            $request->user(),
            $request->validated(),
        );

        return back()->with('success', 'Antecedente de terreno actualizado y registrado en la bitácora.');
    }

    public function destroy(
        DeleteFieldReportRequest $request,
        Contingency $contingency,
        FieldReport $fieldReport,
        DeleteFieldReport $deleteFieldReport,
    ): RedirectResponse {
        abort_unless($fieldReport->contingency_id === $contingency->id, 404);

        $deleteFieldReport->execute($contingency, $fieldReport, $request->user());

        return back()->with('success', 'Antecedente de terreno eliminado y registrado en la bitácora.');
    }
}
