<?php

use App\Modules\Dashboard\Providers\DashboardServiceProvider;
use App\Modules\Identidad\Providers\IdentidadServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\VoltServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    VoltServiceProvider::class,
    IdentidadServiceProvider::class,
    DashboardServiceProvider::class,
];
