<?php

namespace App\Http\Requests\Coordinacion;

use App\Enums\ResultadoRevision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validación de forma de una revisión. La autorización (Policy + zona) la hace el controlador;
 * las reglas que dependen del estado real (archivo, revisión vigente) las aplica el servicio.
 * Coordinador, fecha y versión NO se aceptan del navegador: se fijan en el servidor.
 */
class RegistrarRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'observaciones' => is_string($this->observaciones) ? trim($this->observaciones) : $this->observaciones,
            'motivo_cambio' => is_string($this->motivo_cambio) ? trim($this->motivo_cambio) : $this->motivo_cambio,
        ]);
    }

    public function rules(): array
    {
        $min = (int) config('anfeca_coordinacion.revision_texto_minimo', 10);
        $max = (int) config('anfeca_coordinacion.revision_texto_maximo', 1000);

        return [
            'resultado' => ['required', 'string', Rule::in(ResultadoRevision::valores())],
            'observaciones' => ['nullable', 'string', "min:{$min}", "max:{$max}",
                Rule::requiredIf(fn () => in_array($this->input('resultado'), ['con_observaciones', 'no_valido'], true))],
            'motivo_cambio' => ['nullable', 'string', "min:{$min}", "max:{$max}"],
            'revision_vigente_id' => ['present', 'nullable', 'integer', 'min:1'],
            'version' => ['required', 'string', 'regex:/^[0-9a-f]{64}$/'],
            'clave_idempotencia' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'observaciones.required' => 'Las observaciones son obligatorias para «Con observaciones» y «No válido».',
            'observaciones.min' => 'Las observaciones deben tener al menos :min caracteres.',
            'observaciones.max' => 'Las observaciones no pueden exceder :max caracteres.',
            'motivo_cambio.min' => 'El motivo del cambio debe tener al menos :min caracteres.',
            'motivo_cambio.max' => 'El motivo del cambio no puede exceder :max caracteres.',
            'resultado.in' => 'Resultado no permitido. «Por revisar» y «No aplica» los calcula el sistema.',
        ];
    }
}
