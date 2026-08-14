<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth'])->prefix('aula-virtual')->name('aula-virtual.')->group(function () {
    Volt::route('/', 'aula-virtual.index')->name('index');

    Volt::route('mis-tareas', 'aula-virtual.mis-tareas')->name('mis-tareas');

    Volt::route('{curso}', 'aula-virtual.show')->name('show');

    Volt::route('{curso}/tareas/{tarea}', 'aula-virtual.tarea')->name('tarea');
});
