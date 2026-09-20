<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteFieldReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->canManageFieldReports() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'confirmation.accepted' => 'Confirme la eliminación del antecedente antes de continuar.',
        ];
    }
}
