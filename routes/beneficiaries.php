<?php

use Illuminate\Support\Facades\Route;

// Fase 2 del Módulo 7: submódulo aparte del censo Fase 1 (/censo/nuevo), no
// una extensión del mismo formulario — ver CLAUDE.md para el porqué. Roles
// más restringidos que canSurveyCensus(): esto es trabajo clínico/social
// sobre hogares ya triados, no captura de campo puerta a puerta.
Route::middleware(['auth', 'role:coordinator,admin,doctor'])->prefix('beneficiarios')->name('beneficiaries.')->group(function () {
    Route::livewire('/', 'pages::beneficiaries.index')->name('index');
    Route::livewire('{family}', 'pages::beneficiaries.show')->name('show');
    Route::livewire('{beneficiary}/perfil', 'pages::beneficiaries.profile')->name('profile');
});
