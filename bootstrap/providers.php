<?php

use App\Modules\Academico\Providers\AcademicoServiceProvider;
use App\Modules\Asistencia\Providers\AsistenciaServiceProvider;
use App\Modules\AulaVirtual\Providers\AulaVirtualServiceProvider;
use App\Modules\Dashboard\Providers\DashboardServiceProvider;
use App\Modules\Evaluaciones\Providers\EvaluacionesServiceProvider;
use App\Modules\Identidad\Providers\IdentidadServiceProvider;
use App\Modules\Matricula\Providers\MatriculaServiceProvider;
use App\Modules\Pagos\Providers\PagosServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\VoltServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    VoltServiceProvider::class,
    IdentidadServiceProvider::class,
    DashboardServiceProvider::class,
    AcademicoServiceProvider::class,
    MatriculaServiceProvider::class,
    AulaVirtualServiceProvider::class,
    AsistenciaServiceProvider::class,
    EvaluacionesServiceProvider::class,
    PagosServiceProvider::class,
];
