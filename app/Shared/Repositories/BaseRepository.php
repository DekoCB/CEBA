<?php

declare(strict_types=1);

namespace App\Shared\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Implementación Eloquent por defecto de {@see RepositoryInterface}.
 * Los repositorios de módulo extienden esta clase y añaden sus propios
 * métodos de consulta; nunca se expone el Model ni el Builder fuera del
 * repositorio hacia la capa de Servicio.
 *
 * @template TModel of Model
 *
 * @implements RepositoryInterface<TModel>
 */
abstract class BaseRepository implements RepositoryInterface
{
    /**
     * @param  class-string<TModel>  $modelClass
     */
    public function __construct(protected string $modelClass) {}

    /** @return TModel */
    protected function newQuery()
    {
        return $this->modelClass::query();
    }

    public function find(int $id): ?Model
    {
        return $this->newQuery()->find($id);
    }

    public function findOrFail(int $id): Model
    {
        return $this->newQuery()->findOrFail($id);
    }

    public function all(): Collection
    {
        return $this->newQuery()->get();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->newQuery()->paginate($perPage);
    }

    public function create(array $atributos): Model
    {
        return $this->modelClass::create($atributos);
    }

    public function update(Model $modelo, array $atributos): Model
    {
        $modelo->fill($atributos);
        $modelo->save();

        return $modelo;
    }

    public function delete(Model $modelo): bool
    {
        return (bool) $modelo->delete();
    }
}
