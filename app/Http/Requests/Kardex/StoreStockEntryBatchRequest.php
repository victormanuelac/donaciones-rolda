<?php

namespace App\Http\Requests\Kardex;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el lote de entradas de stock enviado desde el formulario del operador
 * (envío en línea de un solo elemento, o vaciado de la cola offline).
 */
class StoreStockEntryBatchRequest extends FormRequest
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
            'entries.*.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'entries.*.master_item_id' => ['required', 'integer', 'exists:master_items,id'],
            'entries.*.quantity' => ['required', 'integer', 'min:1'],
            'entries.*.lot_number' => ['nullable', 'string', 'max:50'],
            'entries.*.expiry_date' => ['nullable', 'date', 'after:today'],
            'entries.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
