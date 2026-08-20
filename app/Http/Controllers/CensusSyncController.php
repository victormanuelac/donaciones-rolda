<?php

namespace App\Http\Controllers;

use App\Actions\Census\SubmitCensusEntryAction;
use App\Http\Requests\Census\StoreCensusBatchRequest;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Recibe el lote de capturas del censo Fase 1 desde el wizard: tanto el envío en línea
 * (un solo elemento) como el vaciado de la cola offline (IndexedDB/Dexie) al recuperar
 * conexión llegan al mismo endpoint, para que el cliente use una única ruta de código.
 */
class CensusSyncController extends Controller
{
    public function store(StoreCensusBatchRequest $request, SubmitCensusEntryAction $action): JsonResponse
    {
        $results = [];

        foreach ($request->validated('entries') as $entry) {
            try {
                $censusEntry = $action->handle($entry, $request->user());

                $results[] = [
                    'client_uuid' => $entry['client_uuid'],
                    'status' => 'ok',
                    'census_entry_id' => $censusEntry->id,
                    'form_code' => $censusEntry->form_code,
                    'priority_level' => $censusEntry->priority_level->value,
                ];
            } catch (Throwable $e) {
                report($e);

                $results[] = [
                    'client_uuid' => $entry['client_uuid'],
                    'status' => 'error',
                    'message' => 'No se pudo guardar esta captura. Se reintentará en la próxima sincronización.',
                ];
            }
        }

        return response()->json(['results' => $results]);
    }
}
