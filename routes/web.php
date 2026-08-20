<?php

use App\Http\Controllers\Public\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SearchController::class, 'index'])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware(['auth'])->group(function () {
    Route::view('account/pending', 'pages.account.pending')->name('account.pending');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/census.php';
require __DIR__.'/kardex.php';
require __DIR__.'/deliveries.php';
require __DIR__.'/public.php';
