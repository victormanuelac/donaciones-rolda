<?php

namespace App\Http\Controllers;

use App\Actions\Kardex\RegisterStockEntryAction;
use App\Actions\Kardex\RegisterStockExitAction;
use App\Exceptions\ExpiredStockException;
use App\Exceptions\InsufficientStockException;
use App\Http\Requests\Kardex\StoreStockEntryBatchRequest;
use App\Http\Requests\Kardex\StoreStockExitBatchRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Recibe entradas y salidas de stock del formulario del operador: tanto el envío en
 * línea (un solo elemento) como el vaciado de la cola offline (IndexedDB/Dexie) al
 * recuperar conexión llegan al mismo endpoint por tipo, igual que el censo.
 */
class KardexSyncController extends Controller
{
    public function entries(StoreStockEntryBatchRequest $request, RegisterStockEntryAction $action): JsonResponse
    {
        $results = [];

        foreach ($request->validated('entries') as $entry) {
            try {
                $stockEntry = $action->handle($entry, $request->user());

                $results[] = [
                    'client_uuid' => $entry['client_uuid'],
                    'status' => 'ok',
                    'stock_entry_id' => $stockEntry->id,
                ];
            } catch (AuthorizationException $e) {
                $results[] = ['client_uuid' => $entry['client_uuid'], 'status' => 'error', 'message' => $e->getMessage()];
            } catch (Throwable $e) {
                report($e);

                $results[] = [
                    'client_uuid' => $entry['client_uuid'],
                    'status' => 'error',
                    'message' => 'No se pudo guardar esta entrada. Se reintentará en la próxima sincronización.',
                ];
            }
        }

        return response()->json(['results' => $results]);
    }

    public function exits(StoreStockExitBatchRequest $request, RegisterStockExitAction $action): JsonResponse
    {
        $results = [];

        foreach ($request->validated('entries') as $entry) {
            try {
                $stockExit = $action->handle($entry, $request->user());

                $results[] = [
                    'client_uuid' => $entry['client_uuid'],
                    'status' => 'ok',
                    'stock_exit_id' => $stockExit->id,
                ];
            } catch (AuthorizationException|InsufficientStockException|ExpiredStockException $e) {
                $results[] = ['client_uuid' => $entry['client_uuid'], 'status' => 'error', 'message' => $e->getMessage()];
            } catch (Throwable $e) {
                report($e);

                $results[] = [
                    'client_uuid' => $entry['client_uuid'],
                    'status' => 'error',
                    'message' => 'No se pudo guardar esta salida. Se reintentará en la próxima sincronización.',
                ];
            }
        }

        return response()->json(['results' => $results]);
    }
}
