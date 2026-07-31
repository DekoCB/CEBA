# Notas de despliegue a producción

Este documento cubre lo que **no** se puede terminar de configurar en el
entorno de desarrollo local (Windows + XAMPP) y que debe completarse al
desplegar en un servidor Linux real.

## Colas: Horizon

`laravel/horizon` requiere las extensiones PHP `pcntl` y `posix`, exclusivas
de Unix/Linux — no existen en Windows bajo ningún escenario, así que no está
instalado en este repositorio. En el servidor de producción (Linux):

```bash
composer require laravel/horizon
php artisan horizon:install
```

Luego:

1. Cambiar en `.env`: `QUEUE_CONNECTION=redis` (con Redis real instalado y
   corriendo — en local se usa `QUEUE_CONNECTION=database`, que funciona
   pero sin dashboard de monitoreo).
2. Las colas ya usadas por la app (`default`, `auditoria`) deben quedar
   declaradas en `config/horizon.php` bajo el/los supervisor(es).
3. Restringir el dashboard de Horizon (`/horizon`) a Dirección, con un Gate
   en `app/Providers/AppServiceProvider.php`:

   ```php
   use Illuminate\Support\Facades\Gate;

   Gate::define('viewHorizon', fn ($user) => $user->hasRole('direccion'));
   ```

4. Mantener el proceso `php artisan horizon` vivo con Supervisor (no con
   `queue:work` directo), con un `.conf` como:

   ```ini
   [program:ceba-horizon]
   process_name=%(program_name)s
   command=php /var/www/ceba/artisan horizon
   autostart=true
   autorestart=true
   user=www-data
   redirect_stderr=true
   stdout_logfile=/var/www/ceba/storage/logs/horizon.log
   stopwaitsecs=3600
   ```

## Backups

`spatie/laravel-backup` sí está instalado y configurado (`config/backup.php`,
programado a diario vía `routes/console.php`). En producción falta:

- Configurar un disco de backup remoto (S3 u otro) en vez de (o además de)
  `local` — editar `BACKUP_ARCHIVE_PASSWORD` y el disco en
  `config/backup.php` → `backup.destination.disks`.
- Configurar notificaciones de fallo por correo/Slack (ya soportado por el
  paquete, solo falta un canal de notificación real en `.env`).

## Monitoreo

El healthcheck extendido (`/up`) ya valida BD, caché y cola en cualquier
entorno. Si se quiere un APM externo (Sentry, Flare, etc.), instalar el
paquete correspondiente y configurar el DSN por `.env` — no está incluido
porque requiere una cuenta real que este proyecto no tiene.

## CI/CD

El workflow de GitHub Actions (`.github/workflows/ci.yml`) corre Pint,
Larastan y Pest en cada push/PR contra `main`. No hace despliegue
automático: el deploy sigue siendo manual hasta que se defina el servidor
de destino.
