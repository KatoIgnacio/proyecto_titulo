<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContingencyImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->canImportContingencies() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source' => ['required', Rule::in(['synthetic_csv_v1'])],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'checksum' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'source.in' => 'La fuente de importación seleccionada no está disponible.',
            'file.required' => 'Seleccione un archivo validado.',
            'file.mimes' => 'El archivo debe utilizar extensión CSV o TXT.',
            'file.max' => 'El archivo no puede superar 2 MB.',
            'checksum.required' => 'Valide nuevamente el archivo antes de importarlo.',
            'checksum.regex' => 'La validación previa del archivo no es válida.',
        ];
    }
}
