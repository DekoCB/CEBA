<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Enums;

/**
 * Un recibo se emite en dos series (como una libreta de recibos físicos
 * con original y copia): la 001 se entrega al apoderado/estudiante, la 002
 * queda como copia de la institución. Cada serie lleva su propio
 * correlativo independiente (Recibo::$numero_recibo) y corresponde además
 * a un centro de costo distinto del colegio -- 001 es PB (Peruano
 * Británico), 002 es CA (Ciro Alegría) -- por eso cada una imprime su
 * propio logo e iniciales en el recibo.
 */
enum SerieReciboEnum: string
{
    case ORIGINAL = '001';
    case COPIA = '002';

    public function titulo(): string
    {
        return match ($this) {
            self::ORIGINAL => 'Recibo de pago',
            self::COPIA => 'Recibo',
        };
    }

    public function numeroCompleto(string $correlativo): string
    {
        return "{$this->value}-{$correlativo}";
    }

    /**
     * Iniciales del centro de costo (sede) al que pertenece esta serie.
     */
    public function centroCostoIniciales(): string
    {
        return match ($this) {
            self::ORIGINAL => 'PB',
            self::COPIA => 'CA',
        };
    }

    /**
     * Logo institucional de la sede que emite esta serie, impreso en el
     * encabezado del recibo.
     */
    public function logoPath(): string
    {
        return match ($this) {
            self::ORIGINAL => public_path('images/Logo.png'),
            self::COPIA => public_path('images/logo-ca.png'),
        };
    }
}
