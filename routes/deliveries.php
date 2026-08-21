<?php

use Illuminate\Support\Facades\Route;

// A diferencia del Kardex de campo, el registro de entregas requiere buscar
// el hogar beneficiario (PII) contra la base de datos, así que esta pantalla
// no es offline-first: necesita conexión, algo razonable porque las entregas
// se hacen en la bodega/centro de acopio, no puerta a puerta como el censo.
Route::middleware(['auth', 'role:operator,coordinator,admin'])->prefix('entregas')->name('deliveries.')->group(function () {
    Route::livewire('/', 'pages::deliveries.index')->name('index');
    Route::livewire('registrar', 'pages::deliveries.register')->name('register');
});
