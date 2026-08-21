<?php

use App\Actions\MasterItems\RequestNewMasterItemAction;
use App\Enums\MasterItemStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\MasterItem;
use App\Models\StockEntry;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

function pendingCategory(string $name = 'Medicinas'): Category
{
    return Category::create(['name' => $name.' '.uniqid()]);
}

function pendingItem(Category $category, User $requester, string $name = 'Ibuprofeno 400mg'): MasterItem
{
    return (new RequestNewMasterItemAction)->handle([
        'category_id' => $category->id,
        'name' => $name,
        'unit_of_measure' => 'cajas',
    ], $requester);
}

test('roles sin permiso no pueden ver la cola de items pendientes', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route('admin.items.pending'))->assertForbidden();
})->with([UserRole::Operator, UserRole::Coordinator, UserRole::Donor, UserRole::Doctor, UserRole::Municipal]);

test('un admin puede aprobar un item editando sus datos', function () {
    $admin = User::factory()->admin()->create();
    $category = pendingCategory();
    $otherCategory = pendingCategory('Alimentos');
    $requester = User::factory()->create(['role' => UserRole::Operator]);
    $item = pendingItem($category, $requester);

    Livewire::actingAs($admin)
        ->test('pages::admin.items-pending')
        ->set("name.{$item->id}", 'Ibuprofeno 400mg (caja x20)')
        ->set("categoryId.{$item->id}", $otherCategory->id)
        ->set("unitOfMeasure.{$item->id}", 'unidades')
        ->call('approve', $item->id)
        ->assertHasNoErrors();

    $item->refresh();

    expect($item->status)->toBe(MasterItemStatus::Approved)
        ->and($item->name)->toBe('Ibuprofeno 400mg (caja x20)')
        ->and($item->category_id)->toBe($otherCategory->id)
        ->and($item->unit_of_measure)->toBe('unidades');
});

test('rechazar un item exige un motivo', function () {
    $admin = User::factory()->admin()->create();
    $category = pendingCategory();
    $requester = User::factory()->create(['role' => UserRole::Operator]);
    $item = pendingItem($category, $requester);

    Livewire::actingAs($admin)
        ->test('pages::admin.items-pending')
        ->call('reject', $item->id)
        ->assertHasErrors(["rejectionReason.{$item->id}"]);

    expect($item->fresh()->status)->toBe(MasterItemStatus::UnderReview);

    Livewire::actingAs($admin)
        ->test('pages::admin.items-pending')
        ->set("rejectionReason.{$item->id}", 'Ya existe un ítem equivalente en el catálogo.')
        ->call('reject', $item->id)
        ->assertHasNoErrors();

    expect($item->fresh()->status)->toBe(MasterItemStatus::Rejected)
        ->and($item->fresh()->rejection_reason)->toBe('Ya existe un ítem equivalente en el catálogo.');
});

test('consolidar un item duplicado reasigna sus existencias al item destino', function () {
    $admin = User::factory()->admin()->create();
    $category = pendingCategory();
    $requester = User::factory()->create(['role' => UserRole::Operator]);
    $duplicate = pendingItem($category, $requester, 'Acetaminofen 500 MG');

    $target = MasterItem::create([
        'category_id' => $category->id,
        'name' => 'Acetaminofén 500mg',
        'unit_of_measure' => 'cajas',
    ]);

    $entry = StockEntry::create([
        'master_item_id' => $duplicate->id,
        'warehouse_id' => Warehouse::create([
            'name' => 'Bodega de prueba',
            'address' => 'Dirección',
            'contact_person_name' => 'Coordinador',
            'contact_phone' => '3000000000',
        ])->id,
        'registered_by_user_id' => $requester->id,
        'quantity' => 10,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.items-pending')
        ->set("consolidateInto.{$duplicate->id}", $target->id)
        ->call('consolidate', $duplicate->id)
        ->assertHasNoErrors();

    expect($duplicate->fresh()->status)->toBe(MasterItemStatus::Consolidated)
        ->and($duplicate->fresh()->consolidated_into_id)->toBe($target->id)
        ->and($entry->fresh()->master_item_id)->toBe($target->id);
});

test('la cola de pendientes solo muestra items en revision', function () {
    $admin = User::factory()->admin()->create();
    $category = pendingCategory();
    $requester = User::factory()->create(['role' => UserRole::Operator]);
    $pending = pendingItem($category, $requester, 'Vendas elásticas');

    $approved = MasterItem::create([
        'category_id' => $category->id,
        'name' => 'Ya aprobado',
        'unit_of_measure' => 'unidades',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.items-pending')
        ->assertSeeHtml("wire:key=\"item-{$pending->id}\"")
        ->assertDontSeeHtml("wire:key=\"item-{$approved->id}\"");
});
