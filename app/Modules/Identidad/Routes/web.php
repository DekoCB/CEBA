<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('usuarios', 'usuarios.index')
        ->middleware('can:usuarios.ver')
        ->name('usuarios.index');

    Volt::route('usuarios/{usuario}', 'usuarios.show')
        ->middleware('can:usuarios.ver')
        ->name('usuarios.show');

    Volt::route('roles', 'roles.index')
        ->middleware('can:roles.gestionar')
        ->name('roles.index');

    Volt::route('auditoria', 'auditoria.index')
        ->middleware('can:auditoria.ver')
        ->name('auditoria.index');
});
