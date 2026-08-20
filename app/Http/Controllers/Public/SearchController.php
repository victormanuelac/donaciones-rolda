<?php

namespace App\Http\Controllers\Public;

use App\Enums\AvailabilityLevel;
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
            'zones' => GeographicZone::where('is_active', true)->orderBy('name')->get(['id', 'name']),
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
            ->where('status', 'available')
            ->whereHas('masterItem', function ($query) use ($keyword, $categoryId) {
                $query->where('status', 'approved');

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
            ->groupBy(fn (StockEntry $entry) => "{$entry->master_item_id}-{$entry->warehouse_id}")
            ->map(function ($group) use ($lat, $lng) {
                $first = $group->first();
                $availableQuantity = (int) $group->sum(fn (StockEntry $entry) => $entry->availableQuantity());

                if ($availableQuantity <= 0) {
                    return null;
                }

                $level = AvailabilityLevel::fromQuantity($availableQuantity);
                $earliestExpiry = $group->pluck('expiry_date')->filter()->sort()->first();

                $distanceKm = ($lat !== null && $lng !== null && $first->warehouse->latitude !== null)
                    ? round(HaversineDistance::kilometers($lat, $lng, (float) $first->warehouse->latitude, (float) $first->warehouse->longitude), 1)
                    : null;

                return [
                    'master_item_id' => $first->master_item_id,
                    'item_name' => $first->masterItem->name,
                    'unit_of_measure' => $first->masterItem->unit_of_measure,
                    'category_name' => $first->masterItem->category->name,
                    'warehouse_id' => $first->warehouse_id,
                    'warehouse_name' => $first->warehouse->name,
                    'zone_name' => $first->warehouse->zone?->name,
                    'latitude' => $first->warehouse->latitude !== null ? (float) $first->warehouse->latitude : null,
                    'longitude' => $first->warehouse->longitude !== null ? (float) $first->warehouse->longitude : null,
                    'available_quantity' => $availableQuantity,
                    'availability_level' => $level->value,
                    'availability_label' => $level->label(),
                    'availability_emoji' => $level->emoji(),
                    'expiry_date' => $earliestExpiry?->toDateString(),
                    'distance_km' => $distanceKm,
                ];
            })
            ->filter()
            ->values();

        $results = $lat !== null && $lng !== null
            ? $results->sortBy('distance_km')->values()
            : $results->sortByDesc('available_quantity')->values();

        return response()->json(['results' => $results]);
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
