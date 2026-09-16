<?php

namespace App\Http\Requests;

use App\Enums\FieldReportProgress;
use App\Models\Contingency;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFieldReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->canRegisterFieldReports() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'progress_status' => ['required', Rule::enum(FieldReportProgress::class)],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'observed_at' => ['required', 'date', 'before_or_equal:now'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => [
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'mimetypes:image/jpeg,image/png,application/pdf',
                'max:5120',
            ],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('observed_at')) {
                return;
            }

            $contingency = $this->route('contingency');
            if (! $contingency instanceof Contingency || $contingency->started_at === null) {
                return;
            }

            $observedAt = CarbonImmutable::parse((string) $this->input('observed_at'));
            if ($observedAt->isBefore($contingency->started_at)) {
                $validator->errors()->add(
                    'observed_at',
                    'La fecha del antecedente no puede ser anterior al inicio de la contingencia.',
                );
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'description.min' => 'Describa el antecedente de terreno con al menos 10 caracteres.',
            'description.max' => 'La descripción no puede superar los 2000 caracteres.',
            'observed_at.before_or_equal' => 'La fecha del antecedente no puede estar en el futuro.',
            'latitude.required_with' => 'Ingrese la latitud y la longitud en conjunto.',
            'longitude.required_with' => 'Ingrese la latitud y la longitud en conjunto.',
            'attachments.max' => 'Puede adjuntar como máximo tres evidencias.',
            'attachments.*.mimes' => 'Las evidencias deben ser imágenes JPG/PNG o documentos PDF.',
            'attachments.*.mimetypes' => 'El contenido del archivo no corresponde a un formato permitido.',
            'attachments.*.max' => 'Cada evidencia puede pesar como máximo 5 MB.',
        ];
    }
}
