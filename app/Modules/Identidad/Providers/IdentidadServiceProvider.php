<?php

declare(strict_types=1);

namespace App\Modules\Identidad\Providers;

use App\Modules\Identidad\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Modules\Identidad\Repositories\Contracts\UserRepositoryInterface;
use App\Modules\Identidad\Repositories\Eloquent\EloquentAuditLogRepository;
use App\Modules\Identidad\Repositories\Eloquent\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

class IdentidadServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuditLogRepositoryInterface::class, EloquentAuditLogRepository::class);
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
    }
}
