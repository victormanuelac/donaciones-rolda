<?php

use App\Http\Controllers\CensusSyncController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:operator,coordinator,admin'])->prefix('censo')->name('census.')->group(function () {
    Route::livewire('nuevo', 'pages::census.create')->name('create');
    Route::post('sync', [CensusSyncController::class, 'store'])->name('sync');
});
