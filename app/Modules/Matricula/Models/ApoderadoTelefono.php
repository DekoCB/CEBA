<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Models;

use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un celular de respaldo de un apoderado, aparte de Apoderado::celular
 * (el principal, que no se toca).
 *
 * @property int $id
 * @property int $apoderado_id
 * @property string $numero
 * @property string|null $parentesco
 */
class ApoderadoTelefono extends Model
{
    use Auditable;

    protected $fillable = [
        'apoderado_id',
        'numero',
        'parentesco',
    ];

    public function apoderado(): BelongsTo
    {
        return $this->belongsTo(Apoderado::class);
    }
}
