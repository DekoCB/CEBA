<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth'])->prefix('certificados')->name('certificados.')->group(function () {
    Volt::route('/', 'certificados.index')->name('index');

    Volt::route('mis-certificados', 'certificados.mis-certificados')->name('mis-certificados');
});

// Verificación pública: sin autenticación, para que terceros (empleadores,
// otras instituciones) validen un certificado a partir de su código impreso.
Volt::route('verificar-certificado', 'certificados.verificar')->name('certificados.verificar');
