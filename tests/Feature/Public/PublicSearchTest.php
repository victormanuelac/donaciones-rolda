<?php

use App\Models\Category;
use App\Models\GeographicZone;
use App\Models\MasterItem;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Http;

function publicWarehouse(array $overrides = []): Warehouse
{
    return Warehouse::create([...[
        'name' => 'Bodega Pública',
        'address' => 'Dirección de prueba',
        'contact_person_name' => 'Coordinador',
        'contact_phone' => '3121234567',
        'latitude' => 4.4144,
        'longitude' => -76.1536,
    ], ...$overrides]);
}

function publicItem(array $overrides = []): MasterItem
{
    $category = Category::create(['name' => 'Categoría '.uniqid()]);

    return MasterItem::create([...[
        'category_id' => $category->id,
        'name' => 'Suero Oral',
        'unit_of_measure' => 'sachets',
        'status' => 'approved',
    ], ...$overrides]);
}

test('la pagina de busqueda publica carga sin autenticacion en la home', function () {
    $this->get('/')->assertOk();
});

test('el buscador es lo primero que se ve en la home', function () {
    $this->get('/')->assertSeeInOrder(['¿Qué insumo necesitas?', 'Contactar', 'centros de acopio activos']);
});

test('/buscar redirige a la home', function () {
    $this->get('/buscar')->assertRedirect('/');
});

test('un ciudadano no autenticado puede buscar insumos disponibles', function () {
    $warehouse = publicWarehouse();
    $item = publicItem();
    $operator = User::factory()->create();

    StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 30,
    ]);

    $response = $this->getJson('/api/public/search?q=suero');

    $response->assertOk();
    $response->assertJsonCount(1, 'results');
    $response->assertJsonPath('results.0.item_name', 'Suero Oral');
    $response->assertJsonPath('results.0.availability_level', 'high');
    $response->assertJsonPath('results.0.warehouse_name', 'Bodega Pública');
});

test('la busqueda nunca expone el telefono o correo de contacto de la bodega', function () {
    $warehouse = publicWarehouse();
    $item = publicItem();
    $operator = User::factory()->create();

    StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 10,
    ]);

    $searchResponse = $this->getJson('/api/public/search');
    $searchResponse->assertOk();
    $searchResponse->assertJsonMissingPath('results.0.contact_phone');
    expect($searchResponse->getContent())->not->toContain('3121234567');

    $warehousesResponse = $this->getJson('/api/public/warehouses');
    $warehousesResponse->assertOk();
    expect($warehousesResponse->getContent())->not->toContain('3121234567')
        ->and($warehousesResponse->getContent())->not->toContain('Coordinador');
});

test('el semaforo cambia segun la cantidad disponible', function ($quantity, $expectedLevel) {
    $warehouse = publicWarehouse();
    $item = publicItem();
    $operator = User::factory()->create();

    StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => $quantity,
    ]);

    $response = $this->getJson('/api/public/search');

    $response->assertJsonPath('results.0.availability_level', $expectedLevel);
})->with([
    'alta (>20)' => [25, 'high'],
    'media (6-20)' => [10, 'medium'],
    'baja (1-5)' => [3, 'low'],
]);

test('un item totalmente agotado no aparece en los resultados', function () {
    $warehouse = publicWarehouse();
    $item = publicItem();
    $operator = User::factory()->create();

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 5,
    ]);

    StockExit::create([
        'stock_entry_id' => $entry->id,
        'warehouse_id' => $warehouse->id,
        'released_by_user_id' => $operator->id,
        'exit_reason' => 'donation',
        'quantity_released' => 5,
    ]);

    $response = $this->getJson('/api/public/search');

    $response->assertJsonCount(0, 'results');
});

test('un item en revision o de una bodega inactiva no aparece en los resultados', function () {
    $activeWarehouse = publicWarehouse();
    $inactiveWarehouse = publicWarehouse(['name' => 'Bodega Inactiva', 'is_active' => false]);
    $pendingItem = publicItem(['status' => 'under_review']);
    $approvedItem = publicItem();
    $operator = User::factory()->create();

    StockEntry::create([
        'master_item_id' => $pendingItem->id,
        'warehouse_id' => $activeWarehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 10,
    ]);

    StockEntry::create([
        'master_item_id' => $approvedItem->id,
        'warehouse_id' => $inactiveWarehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 10,
    ]);

    $response = $this->getJson('/api/public/search');

    $response->assertJsonCount(0, 'results');
});

test('filtra por categoria y por zona', function () {
    $zone = GeographicZone::create(['zone_type' => 'barrio', 'name' => 'Barrio Test']);
    $warehouseInZone = publicWarehouse(['geographic_zone_id' => $zone->id]);
    $warehouseOutsideZone = publicWarehouse(['name' => 'Otra bodega']);
    $item = publicItem();
    $otherCategoryItem = publicItem();
    $operator = User::factory()->create();

    StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouseInZone->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 10,
    ]);

    StockEntry::create([
        'master_item_id' => $otherCategoryItem->id,
        'warehouse_id' => $warehouseOutsideZone->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 10,
    ]);

    $byZone = $this->getJson('/api/public/search?zone_id='.$zone->id);
    $byZone->assertJsonCount(1, 'results');
    $byZone->assertJsonPath('results.0.warehouse_name', 'Bodega Pública');

    $byCategory = $this->getJson('/api/public/search?category_id='.$item->category_id);
    $byCategory->assertJsonCount(1, 'results');
});

test('ordena por distancia cuando se envia la ubicacion del ciudadano', function () {
    $near = publicWarehouse(['name' => 'Cercana', 'latitude' => 4.4144, 'longitude' => -76.1536]);
    $far = publicWarehouse(['name' => 'Lejana', 'latitude' => 3.4516, 'longitude' => -76.5320]);
    $item = publicItem();
    $operator = User::factory()->create();

    StockEntry::create(['master_item_id' => $item->id, 'warehouse_id' => $near->id, 'registered_by_user_id' => $operator->id, 'quantity' => 10]);
    StockEntry::create(['master_item_id' => $item->id, 'warehouse_id' => $far->id, 'registered_by_user_id' => $operator->id, 'quantity' => 10]);

    $response = $this->getJson('/api/public/search?lat=4.4144&lng=-76.1536');

    $response->assertJsonPath('results.0.warehouse_name', 'Cercana');
    $response->assertJsonPath('results.1.warehouse_name', 'Lejana');
});

test('el listado publico de bodegas no incluye datos de contacto', function () {
    publicWarehouse();

    $response = $this->getJson('/api/public/warehouses');

    $response->assertOk();
    $response->assertJsonStructure(['warehouses' => [['id', 'name', 'zone_name', 'latitude', 'longitude']]]);
});

test('desbloquear contacto falla si turnstile rechaza el token', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

    $warehouse = publicWarehouse();

    $response = $this->postJson('/api/public/contact-unlock', [
        'warehouse_id' => $warehouse->id,
        'turnstile_token' => 'token-invalido',
    ]);

    $response->assertUnprocessable();
});

test('desbloquear contacto funciona cuando turnstile valida el token', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    $warehouse = publicWarehouse();

    $response = $this->postJson('/api/public/contact-unlock', [
        'warehouse_id' => $warehouse->id,
        'turnstile_token' => 'token-valido',
    ]);

    $response->assertOk();
    $response->assertJsonPath('contact_phone', '3121234567');
    $response->assertJsonPath('contact_person_name', 'Coordinador');
    expect($response->json('whatsapp_url'))->toContain('573121234567');
});
