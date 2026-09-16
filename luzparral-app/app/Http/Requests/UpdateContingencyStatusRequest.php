<?php

namespace App\Http\Requests;

use App\Enums\ContingencyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContingencyStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->canUpdateContingencies() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_status' => ['required', Rule::enum(ContingencyStatus::class)],
            'status' => ['required', Rule::enum(ContingencyStatus::class)],
            'note' => ['required', 'string', 'min:5', 'max:300'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_status.required' => 'No fue posible identificar el estado de origen.',
            'current_status.enum' => 'El estado de origen no es válido.',
            'status.required' => 'Seleccione el nuevo estado.',
            'status.enum' => 'El estado seleccionado no es válido.',
            'note.required' => 'Ingrese el antecedente que respalda el cambio.',
            'note.min' => 'El antecedente debe contener al menos 5 caracteres.',
            'note.max' => 'El antecedente no puede superar los 300 caracteres.',
        ];
    }
}
