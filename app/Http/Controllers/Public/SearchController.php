<?php

namespace App\Http\Controllers\Public;

use App\Enums\AvailabilityLevel;
use App\Enums\MasterItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ContactUnlockRequest;
use App\Models\Category;
use App\Models\GeographicZone;
use App\Models\StockEntry;
use App\Models\Warehouse;
use App\Services\Geo\HaversineDistance;
use App\Services\Turnstile\TurnstileVerifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Portal público de búsqueda (Módulo 1): ciudadanos buscan insumos disponibles
 * sin autenticarse. Nunca expone contact_phone/contact_email de las bodegas
 * directamente — eso solo se revela vía contactUnlock() tras validar Turnstile.
 */
class SearchController extends Controller
{
    public function index(): View
    {
        return view('pages.public.search', [
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'zones' => GeographicZone::where('is_active', true)->orderBy('name')->get(['id', 'name', 'latitude', 'longitude']),
            'activeWarehousesCount' => Warehouse::where('is_active', true)->count(),
            'availableItemsCount' => StockEntry::where('status', 'available')->distinct('master_item_id')->count('master_item_id'),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('q', ''));
        $categoryId = $request->integer('category_id') ?: null;
        $zoneId = $request->integer('zone_id') ?: null;
        $lat = $request->float('lat') ?: null;
        $lng = $request->float('lng') ?: null;

        $entries = StockEntry::query()
            ->with(['masterItem.category', 'warehouse.zone'])
            // Sin esto, cada lote del resultado dispara su propia consulta al
            // calcular lo disponible — y esta es la ruta anónima de más tráfico.
            ->withAvailableQuantity()
            ->where('status', 'available')
            ->whereHas('masterItem', function ($query) use ($keyword, $categoryId) {
                $query->where('status', MasterItemStatus::Approved);

                if ($keyword !== '') {
                    $query->where('name', 'like', '%'.$keyword.'%');
                }

                if ($categoryId) {
                    $query->where('category_id', $categoryId);
                }
            })
            ->whereHas('warehouse', function ($query) use ($zoneId) {
                $query->where('is_active', true);

                if ($zoneId) {
                    $query->where('geographic_zone_id', $zoneId);
                }
            })
            ->get();

        $results = $entries
            ->groupBy('master_item_id')
            ->map(fn (Collection $itemGroup) => $this->buildItemResult($itemGroup, $lat, $lng))
            ->filter()
            ->values();

        $results = $lat !== null && $lng !== null
            ? $results->sortBy('closest_distance_km')->values()
            : $results->sortByDesc('total_available_quantity')->values();

        return response()->json(['results' => $results]);
    }

    /**
     * @param  Collection<int, StockEntry>  $itemGroup
     * @return array{master_item_id: int, item_name: string, unit_of_measure: string, category_name: string, total_available_quantity: int, closest_distance_km: float|null, locations: list<array{warehouse_id: int, warehouse_name: string, zone_name: string|null, latitude: float|null, longitude: float|null, available_quantity: int, availability_level: string, availability_label: string, availability_emoji: string, expiry_date: string|null, distance_km: float|null}>}|null
     */
    private function buildItemResult(Collection $itemGroup, ?float $lat, ?float $lng): ?array
    {
        $first = $itemGroup->first();

        $locations = $itemGroup
            ->groupBy('warehouse_id')
            ->map(fn (Collection $warehouseGroup) => $this->buildLocationResult($warehouseGroup, $lat, $lng))
            ->filter()
            ->values();

        if ($locations->isEmpty()) {
            return null;
        }

        $locations = array_values(($lat !== null && $lng !== null
            ? $locations->sortBy('distance_km')
            : $locations->sortByDesc('available_quantity'))
            ->all());

        return [
            'master_item_id' => $first->master_item_id,
            'item_name' => $first->masterItem->name,
            'unit_of_measure' => $first->masterItem->unit_of_measure,
            'category_name' => $first->masterItem->category->name,
            'total_available_quantity' => (int) array_sum(array_column($locations, 'available_quantity')),
            'closest_distance_km' => $locations[0]['distance_km'] ?? null,
            'locations' => $locations,
        ];
    }

    /**
     * @param  Collection<int, StockEntry>  $warehouseGroup
     * @return array{warehouse_id: int, warehouse_name: string, zone_name: string|null, latitude: float|null, longitude: float|null, available_quantity: int, availability_level: string, availability_label: string, availability_emoji: string, expiry_date: string|null, distance_km: float|null}|null
     */
    private function buildLocationResult(Collection $warehouseGroup, ?float $lat, ?float $lng): ?array
    {
        $entry = $warehouseGroup->first();
        $availableQuantity = (int) $warehouseGroup->sum(fn (StockEntry $e) => $e->availableQuantity());

        if ($availableQuantity <= 0) {
            return null;
        }

        $level = AvailabilityLevel::fromQuantity($availableQuantity);
        $earliestExpiry = $warehouseGroup->pluck('expiry_date')->filter()->sort()->first();

        $distanceKm = ($lat !== null && $lng !== null && $entry->warehouse->latitude !== null)
            ? round(HaversineDistance::kilometers($lat, $lng, (float) $entry->warehouse->latitude, (float) $entry->warehouse->longitude), 1)
            : null;

        return [
            'warehouse_id' => $entry->warehouse_id,
            'warehouse_name' => $entry->warehouse->name,
            'zone_name' => $entry->warehouse->zone?->name,
            'latitude' => $entry->warehouse->latitude !== null ? (float) $entry->warehouse->latitude : null,
            'longitude' => $entry->warehouse->longitude !== null ? (float) $entry->warehouse->longitude : null,
            'available_quantity' => $availableQuantity,
            'availability_level' => $level->value,
            'availability_label' => $level->label(),
            'availability_emoji' => $level->emoji(),
            'expiry_date' => $earliestExpiry?->toDateString(),
            'distance_km' => $distanceKm,
        ];
    }

    public function warehouses(): JsonResponse
    {
        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('zone')
            ->get()
            ->map(fn (Warehouse $warehouse) => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'zone_name' => $warehouse->zone?->name,
                'latitude' => (float) $warehouse->latitude,
                'longitude' => (float) $warehouse->longitude,
            ]);

        return response()->json(['warehouses' => $warehouses]);
    }

    public function contactUnlock(ContactUnlockRequest $request, TurnstileVerifier $verifier): JsonResponse
    {
        $validated = $request->validated();

        if (! $verifier->verify($validated['turnstile_token'], $request->ip())) {
            return response()->json([
                'message' => 'No pudimos verificar que eres una persona. Intenta de nuevo.',
            ], 422);
        }

        $warehouse = Warehouse::findOrFail((int) $validated['warehouse_id']);

        $digitsOnly = preg_replace('/\D/', '', $warehouse->contact_phone) ?? '';
        $message = rawurlencode("Hola, vi en Donaciones Rolda que tienen insumos disponibles en {$warehouse->name}. ¿Podrían darme más información?");

        return response()->json([
            'contact_person_name' => $warehouse->contact_person_name,
            'contact_phone' => $warehouse->contact_phone,
            'whatsapp_url' => "https://wa.me/57{$digitsOnly}?text={$message}",
        ]);
    }
}
