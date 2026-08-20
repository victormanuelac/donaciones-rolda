<?php

namespace App\Http\Requests\Kardex;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el lote de traslados entre bodegas enviado desde el formulario del operador
 * (envío en línea de un solo elemento, o vaciado de la cola offline).
 */
class StoreStockTransferBatchRequest extends FormRequest
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
            'entries.*.source_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'entries.*.destination_warehouse_id' => ['required', 'integer', 'different:entries.*.source_warehouse_id', 'exists:warehouses,id'],
            'entries.*.quantity' => ['required', 'integer', 'min:1'],
            'entries.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
