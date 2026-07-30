<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth'])->prefix('matricula')->name('matricula.')->group(function () {
    Volt::route('/', 'matricula.index')
        ->middleware('can:matricula.ver')
        ->name('index');

    Volt::route('nueva', 'matricula.wizard')
        ->middleware('can:matricula.crear')
        ->name('wizard');

    Volt::route('{estudiante}', 'matricula.show')
        ->middleware('can:matricula.ver')
        ->name('show');
});
