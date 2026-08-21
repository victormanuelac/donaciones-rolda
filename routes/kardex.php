<?php

use App\Http\Controllers\KardexSyncController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:operator,coordinator,admin'])->prefix('kardex')->name('kardex.')->group(function () {
    Route::livewire('/', 'pages::kardex.index')->name('index');
    Route::livewire('entrada', 'pages::kardex.entry-form')->name('entry');
    Route::livewire('salida', 'pages::kardex.exit-form')->name('exit');
    Route::livewire('traslado', 'pages::kardex.transfer-form')->name('transfer');
    Route::livewire('conteo', 'pages::kardex.count')->name('count');
    Route::livewire('ficha', 'pages::kardex.ledger')->name('ledger');
    Route::livewire('vencimientos', 'pages::kardex.expiry-alerts')->name('expiry-alerts');

    Route::post('entradas/sync', [KardexSyncController::class, 'entries'])->name('entries.sync');
    Route::post('salidas/sync', [KardexSyncController::class, 'exits'])->name('exits.sync');
    Route::post('traslados/sync', [KardexSyncController::class, 'transfers'])->name('transfers.sync');
});
