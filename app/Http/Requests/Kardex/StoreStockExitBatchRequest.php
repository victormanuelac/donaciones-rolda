<?php

namespace App\Http\Requests\Kardex;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el lote de salidas de stock enviado desde el formulario del operador
 * (envío en línea de un solo elemento, o vaciado de la cola offline).
 */
class StoreStockExitBatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.client_uuid' => ['required', 'uuid'],
            'entries.*.stock_entry_id' => ['required', 'integer', 'exists:stock_entries,id'],
            'entries.*.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'entries.*.quantity_released' => ['required', 'integer', 'min:1'],
            'entries.*.exit_reason' => ['required', 'in:donation,subsidized_sale,emergency_assistance,other'],
            'entries.*.received_by_name' => ['nullable', 'string', 'max:150'],
            'entries.*.destination_zone_id' => ['nullable', 'integer', 'exists:geographic_zones,id'],
            'entries.*.destination_description' => ['nullable', 'string', 'max:500'],
            'entries.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
