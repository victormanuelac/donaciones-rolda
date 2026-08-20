<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('usuarios-pendientes', 'pages::admin.users-pending')->name('users.pending');
    Route::livewire('bodegas', 'pages::admin.warehouses')->name('warehouses.index');
});
