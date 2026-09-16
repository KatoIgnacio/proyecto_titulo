<?php

namespace App\Http\Requests;

use App\Support\ContingencyPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContingencyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...ContingencyPeriod::validationRules(),
            'commune' => ['nullable', 'integer', 'exists:communes,id'],
            'feeder' => ['nullable', 'integer', 'exists:feeders,id'],
            'priority' => ['nullable', Rule::in(['critical', 'high', 'medium', 'low'])],
            'status' => ['nullable', Rule::in(['reported', 'assigned', 'in_progress', 'restored', 'closed'])],
            'search' => ['nullable', 'string', 'max:80'],
            'report_type' => ['nullable', Rule::in(['executive', 'development', 'complete'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{range: string, date_day: ?string, date_month: ?string, date_year: ?string, date_from: ?string, date_to: ?string, commune: ?int, feeder: ?int, priority: ?string, status: ?string, search: string}
     */
    public function reportFilters(): array
    {
        $validated = $this->validated();
        $period = ContingencyPeriod::normalize($validated);

        return [
            ...$period,
            'commune' => isset($validated['commune']) ? (int) $validated['commune'] : null,
            'feeder' => isset($validated['feeder']) ? (int) $validated['feeder'] : null,
            'priority' => $validated['priority'] ?? null,
            'status' => $validated['status'] ?? null,
            'search' => trim($validated['search'] ?? ''),
        ];
    }

    public function reportType(): string
    {
        return $this->validated('report_type') ?? 'executive';
    }
}
