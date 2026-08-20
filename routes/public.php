<?php

use App\Http\Controllers\Public\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('buscar', [SearchController::class, 'index'])->name('public.search');

Route::prefix('api/public')->name('api.public.')->middleware('throttle:60,1')->group(function () {
    Route::get('search', [SearchController::class, 'search'])->name('search');
    Route::get('warehouses', [SearchController::class, 'warehouses'])->name('warehouses');
    Route::post('contact-unlock', [SearchController::class, 'contactUnlock'])->name('contact-unlock');
});
