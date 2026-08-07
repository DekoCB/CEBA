<?php

use App\Modules\AulaVirtual\Enums\TipoMaterialEnum;
use App\Modules\AulaVirtual\Enums\TipoPublicacionEnum;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\Foro;
use App\Modules\AulaVirtual\Models\Publicacion;
use App\Modules\AulaVirtual\Services\ComentarioService;
use App\Modules\AulaVirtual\Services\ForoService;
use App\Modules\AulaVirtual\Services\MaterialService;
use App\Modules\AulaVirtual\Services\PublicacionService;
use App\Modules\AulaVirtual\Services\TareaService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public CursoVirtual $curso;

    public string $tab = 'materiales';

    // Nuevo material
    public bool $mostrarFormMaterial = false;

    public string $materialTipo = '';

    public string $materialTitulo = '';

    public string $materialUrl = '';

    public $materialArchivo = null;

    // Nueva tarea
    public bool $mostrarFormTarea = false;

    public string $tareaTitulo = '';

    public string $tareaDescripcion = '';

    public string $tareaFechaLimite = '';

    public string $tareaPuntajeMax = '20';

    // Nueva publicación
    public bool $mostrarFormPublicacion = false;

    public string $publicacionTipo = '';

    public string $publicacionContenido = '';

    /** @var array<int, string> */
    public array $nuevoComentario = [];

    // Nuevo foro
    public bool $mostrarFormForo = false;

    public string $foroTitulo = '';

    public string $foroDescripcion = '';

    /** @var array<int, string> */
    public array $nuevaRespuestaForo = [];

    public function mount(CursoVirtual $curso): void
    {
        Gate::authorize('view', $curso);

        $this->curso = $curso;
    }

    public function crearMaterial(MaterialService $service): void
    {
        Gate::authorize('manage', $this->curso);

        $this->validate([
            'materialTipo' => 'required|string|in:'.implode(',', array_column(TipoMaterialEnum::cases(), 'value')),
            'materialTitulo' => 'required|string|max:150',
            'materialUrl' => 'nullable|url|max:500',
            'materialArchivo' => 'nullable|file|max:10240',
        ]);

        $service->crear(
            $this->curso,
            TipoMaterialEnum::from($this->materialTipo),
            $this->materialTitulo,
            $this->materialUrl ?: null,
            $this->materialArchivo,
        );

        $this->reset(['materialTipo', 'materialTitulo', 'materialUrl', 'materialArchivo', 'mostrarFormMaterial']);
    }

    public function eliminarMaterial(int $materialId, MaterialService $service): void
    {
        Gate::authorize('manage', $this->curso);

        $service->eliminar($this->curso->materiales()->findOrFail($materialId));
    }

    public function crearTarea(TareaService $service): void
    {
        Gate::authorize('manage', $this->curso);

        $this->validate([
            'tareaTitulo' => 'required|string|max:150',
            'tareaDescripcion' => 'nullable|string',
            'tareaFechaLimite' => 'required|date',
            'tareaPuntajeMax' => 'required|integer|min:1|max:20',
        ]);

        $service->crear($this->curso, [
            'titulo' => $this->tareaTitulo,
            'descripcion' => $this->tareaDescripcion ?: null,
            'fecha_limite' => $this->tareaFechaLimite,
            'puntaje_max' => (int) $this->tareaPuntajeMax,
        ]);

        $this->reset(['tareaTitulo', 'tareaDescripcion', 'tareaFechaLimite', 'tareaPuntajeMax', 'mostrarFormTarea']);
    }

    public function crearPublicacion(PublicacionService $service): void
    {
        Gate::authorize('manage', $this->curso);

        $this->validate([
            'publicacionTipo' => 'required|string|in:'.implode(',', array_column(TipoPublicacionEnum::cases(), 'value')),
            'publicacionContenido' => 'required|string',
        ]);

        $service->crear($this->curso, auth()->id(), TipoPublicacionEnum::from($this->publicacionTipo), $this->publicacionContenido);

        $this->reset(['publicacionTipo', 'publicacionContenido', 'mostrarFormPublicacion']);
    }

    public function comentar(int $publicacionId, ComentarioService $service): void
    {
        Gate::authorize('view', $this->curso);

        $contenido = trim($this->nuevoComentario[$publicacionId] ?? '');

        if ($contenido === '') {
            return;
        }

        $publicacion = Publicacion::query()->where('curso_virtual_id', $this->curso->id)->findOrFail($publicacionId);

        $service->comentar($publicacion, auth()->id(), $contenido);

        unset($this->nuevoComentario[$publicacionId]);
    }

    public function crearForo(ForoService $service): void
    {
        Gate::authorize('manage', $this->curso);

        $this->validate([
            'foroTitulo' => 'required|string|max:150',
            'foroDescripcion' => 'nullable|string',
        ]);

        $service->crear($this->curso, auth()->id(), $this->foroTitulo, $this->foroDescripcion ?: null);

        $this->reset(['foroTitulo', 'foroDescripcion', 'mostrarFormForo']);
    }

    public function responderForo(int $foroId, ForoService $service): void
    {
        Gate::authorize('view', $this->curso);

        $contenido = trim($this->nuevaRespuestaForo[$foroId] ?? '');

        if ($contenido === '') {
            return;
        }

        $foro = Foro::query()->where('curso_virtual_id', $this->curso->id)->findOrFail($foroId);

        $service->responder($foro, auth()->id(), $contenido);

        unset($this->nuevaRespuestaForo[$foroId]);
    }

    public function with(): array
    {
        return [
            'puedeGestionar' => Gate::allows('manage', $this->curso),
            'materiales' => $this->curso->materiales,
            'tareas' => $this->curso->tareas()->latest('fecha_limite')->get(),
            'publicaciones' => $this->curso->publicaciones()->with(['autor', 'comentarios.autor'])->latest()->get(),
            'foros' => $this->curso->foros()->with(['autor', 'respuestas.autor'])->latest()->get(),
            'tiposMaterial' => TipoMaterialEnum::cases(),
            'tiposPublicacion' => TipoPublicacionEnum::cases(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <a href="{{ route('aula-virtual.index') }}" wire:navigate class="text-sm text-ink-faint hover:text-ink">← Aula Virtual</a>
        <h1 class="mt-1 font-display text-2xl text-ink">{{ $curso->horario->curso->nombre }}</h1>
        <p class="mt-1 text-sm text-ink-dim">{{ $curso->horario->grado->nombre }} · {{ $curso->horario->ciclo->nombre }} · {{ $curso->horario->docente->name }}</p>
    </x-slot>

    <div class="mb-6 flex gap-1 border-b border-border">
        @foreach (['materiales' => 'Materiales', 'tareas' => 'Tareas', 'publicaciones' => 'Publicaciones', 'foros' => 'Foros'] as $valor => $etiqueta)
            <button
                wire:click="$set('tab', '{{ $valor }}')"
                @class([
                    'border-b-2 px-4 py-2 text-sm font-medium transition',
                    'border-accent text-accent' => $tab === $valor,
                    'border-transparent text-ink-faint hover:text-ink' => $tab !== $valor,
                ])
            >
                {{ $etiqueta }}
            </button>
        @endforeach
    </div>

    {{-- Materiales --}}
    @if ($tab === 'materiales')
        <div class="space-y-4">
            @can('manage', $curso)
                <div class="flex justify-end">
                    <x-secondary-button type="button" wire:click="$set('mostrarFormMaterial', true)">+ Nuevo material</x-secondary-button>
                </div>
            @endcan

            @if ($mostrarFormMaterial)
                <form wire:submit="crearMaterial" class="rounded-lg border border-border bg-surface p-4 space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-input-label for="materialTipo" value="Tipo" />
                            <x-select-input
                                wire:model.live="materialTipo"
                                id="materialTipo"
                                class="mt-1 block w-full"
                                :options="collect($tiposMaterial)->mapWithKeys(fn ($tipo) => [$tipo->value => $tipo->label()])"
                            />
                            <x-input-error :messages="$errors->get('materialTipo')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="materialTitulo" value="Título" />
                            <x-text-input wire:model="materialTitulo" id="materialTitulo" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('materialTitulo')" class="mt-1" />
                        </div>
                    </div>
                    @if (in_array($materialTipo, ['pdf', 'archivo']))
                        <div>
                            <x-input-label for="materialArchivo" value="Archivo" />
                            <input wire:model="materialArchivo" id="materialArchivo" type="file" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                            <x-input-error :messages="$errors->get('materialArchivo')" class="mt-1" />
                        </div>
                    @elseif ($materialTipo !== '')
                        <div>
                            <x-input-label for="materialUrl" value="URL" />
                            <x-text-input wire:model="materialUrl" id="materialUrl" class="mt-1 block w-full" placeholder="https://…" />
                            <x-input-error :messages="$errors->get('materialUrl')" class="mt-1" />
                        </div>
                    @endif
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="$set('mostrarFormMaterial', false)">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Guardar</x-primary-button>
                    </div>
                </form>
            @endif

            <div class="divide-y divide-border rounded-lg border border-border bg-surface">
                @forelse ($materiales as $material)
                    <div class="flex items-center justify-between px-4 py-3 text-sm">
                        <div class="flex items-center gap-3">
                            <span class="rounded-full bg-surface-2 px-2 py-0.5 text-xs font-mono text-ink-faint">{{ $material->tipo->label() }}</span>
                            <span class="text-ink">{{ $material->titulo }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            @if ($material->tipo->requiereArchivo() && $material->getFirstMedia('archivo'))
                                <a href="{{ $material->getFirstMediaUrl('archivo') }}" target="_blank" class="text-xs font-medium text-accent hover:underline">Descargar</a>
                            @elseif ($material->url)
                                <a href="{{ $material->url }}" target="_blank" class="text-xs font-medium text-accent hover:underline">Abrir enlace</a>
                            @endif
                            @can('manage', $curso)
                                <button wire:click="eliminarMaterial({{ $material->id }})" wire:confirm="¿Eliminar este material?" class="text-xs font-medium text-danger hover:underline">Eliminar</button>
                            @endcan
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-ink-faint">Todavía no hay materiales.</p>
                @endforelse
            </div>
        </div>
    @endif

    {{-- Tareas --}}
    @if ($tab === 'tareas')
        <div class="space-y-4">
            @can('manage', $curso)
                <div class="flex justify-end">
                    <x-secondary-button type="button" wire:click="$set('mostrarFormTarea', true)">+ Nueva tarea</x-secondary-button>
                </div>
            @endcan

            @if ($mostrarFormTarea)
                <form wire:submit="crearTarea" class="rounded-lg border border-border bg-surface p-4 space-y-3">
                    <div>
                        <x-input-label for="tareaTitulo" value="Título" />
                        <x-text-input wire:model="tareaTitulo" id="tareaTitulo" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('tareaTitulo')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="tareaDescripcion" value="Descripción" />
                        <textarea wire:model="tareaDescripcion" id="tareaDescripcion" rows="2" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-input-label for="tareaFechaLimite" value="Fecha límite" />
                            <x-text-input wire:model="tareaFechaLimite" id="tareaFechaLimite" type="datetime-local" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('tareaFechaLimite')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="tareaPuntajeMax" value="Puntaje máximo" />
                            <x-text-input wire:model="tareaPuntajeMax" id="tareaPuntajeMax" type="number" min="1" max="20" class="mt-1 block w-full" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="$set('mostrarFormTarea', false)">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Guardar</x-primary-button>
                    </div>
                </form>
            @endif

            <div class="divide-y divide-border rounded-lg border border-border bg-surface">
                @forelse ($tareas as $tarea)
                    <a href="{{ route('aula-virtual.tarea', [$curso, $tarea]) }}" wire:navigate class="flex items-center justify-between px-4 py-3 text-sm hover:bg-surface-2">
                        <div>
                            <p class="text-ink">{{ $tarea->titulo }}</p>
                            <p class="text-xs text-ink-faint">Vence {{ $tarea->fecha_limite->format('d/m/Y H:i') }} · {{ $tarea->puntaje_max }} pts</p>
                        </div>
                        @if ($tarea->estaVencida())
                            <span class="rounded-full bg-danger/10 px-2 py-0.5 text-xs text-danger">Vencida</span>
                        @endif
                    </a>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-ink-faint">Todavía no hay tareas.</p>
                @endforelse
            </div>
        </div>
    @endif

    {{-- Publicaciones --}}
    @if ($tab === 'publicaciones')
        <div class="space-y-4">
            @can('manage', $curso)
                <div class="flex justify-end">
                    <x-secondary-button type="button" wire:click="$set('mostrarFormPublicacion', true)">+ Nueva publicación</x-secondary-button>
                </div>
            @endcan

            @if ($mostrarFormPublicacion)
                <form wire:submit="crearPublicacion" class="rounded-lg border border-border bg-surface p-4 space-y-3">
                    <div>
                        <x-input-label for="publicacionTipo" value="Tipo" />
                        <x-select-input
                            wire:model="publicacionTipo"
                            id="publicacionTipo"
                            class="mt-1 block w-full"
                            :options="collect($tiposPublicacion)->mapWithKeys(fn ($tipo) => [$tipo->value => $tipo->label()])"
                        />
                        <x-input-error :messages="$errors->get('publicacionTipo')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="publicacionContenido" value="Contenido" />
                        <textarea wire:model="publicacionContenido" id="publicacionContenido" rows="3" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                        <x-input-error :messages="$errors->get('publicacionContenido')" class="mt-1" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="$set('mostrarFormPublicacion', false)">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Publicar</x-primary-button>
                    </div>
                </form>
            @endif

            <div class="space-y-4">
                @forelse ($publicaciones as $publicacion)
                    <div class="rounded-lg border border-border bg-surface p-4">
                        <div class="flex items-center justify-between">
                            <span class="rounded-full bg-accent-soft px-2 py-0.5 text-xs text-accent">{{ $publicacion->tipo->label() }}</span>
                            <span class="text-xs text-ink-faint">{{ $publicacion->autor->name }} · {{ $publicacion->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="mt-3 text-sm text-ink">{{ $publicacion->contenido }}</p>

                        <div class="mt-4 space-y-2 border-t border-border pt-3">
                            @foreach ($publicacion->comentarios as $comentario)
                                <div class="text-xs">
                                    <span class="font-medium text-ink">{{ $comentario->autor->name }}</span>
                                    <span class="text-ink-dim">{{ $comentario->contenido }}</span>
                                </div>
                            @endforeach

                            <form wire:submit="comentar({{ $publicacion->id }})" class="flex gap-2 pt-1">
                                <input
                                    type="text"
                                    wire:model="nuevoComentario.{{ $publicacion->id }}"
                                    placeholder="Escribe un comentario…"
                                    class="flex-1 rounded-md border-border bg-surface text-xs text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent"
                                >
                                <button type="submit" class="text-xs font-medium text-accent hover:underline">Comentar</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-ink-faint">Todavía no hay publicaciones.</p>
                @endforelse
            </div>
        </div>
    @endif

    {{-- Foros --}}
    @if ($tab === 'foros')
        <div class="space-y-4">
            @can('manage', $curso)
                <div class="flex justify-end">
                    <x-secondary-button type="button" wire:click="$set('mostrarFormForo', true)">+ Nuevo foro</x-secondary-button>
                </div>
            @endcan

            @if ($mostrarFormForo)
                <form wire:submit="crearForo" class="rounded-lg border border-border bg-surface p-4 space-y-3">
                    <div>
                        <x-input-label for="foroTitulo" value="Título" />
                        <x-text-input wire:model="foroTitulo" id="foroTitulo" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('foroTitulo')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="foroDescripcion" value="Descripción" />
                        <textarea wire:model="foroDescripcion" id="foroDescripcion" rows="2" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="$set('mostrarFormForo', false)">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Crear foro</x-primary-button>
                    </div>
                </form>
            @endif

            <div class="space-y-4">
                @forelse ($foros as $foro)
                    <div class="rounded-lg border border-border bg-surface p-4">
                        <p class="text-ink">{{ $foro->titulo }}</p>
                        <p class="text-xs text-ink-faint">
                            {{ $foro->autor->name }} · {{ $foro->respuestas->count() }} {{ Str::plural('respuesta', $foro->respuestas->count()) }}
                        </p>
                        @if ($foro->descripcion)
                            <p class="mt-2 text-sm text-ink-dim">{{ $foro->descripcion }}</p>
                        @endif

                        <div class="mt-4 space-y-2 border-t border-border pt-3">
                            @foreach ($foro->respuestas as $respuesta)
                                <div class="text-xs">
                                    <span class="font-medium text-ink">{{ $respuesta->autor->name }}</span>
                                    <span class="text-ink-dim">{{ $respuesta->contenido }}</span>
                                </div>
                            @endforeach

                            <form wire:submit="responderForo({{ $foro->id }})" class="flex gap-2 pt-1">
                                <input
                                    type="text"
                                    wire:model="nuevaRespuestaForo.{{ $foro->id }}"
                                    placeholder="Escribe una respuesta…"
                                    class="flex-1 rounded-md border-border bg-surface text-xs text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent"
                                >
                                <button type="submit" class="text-xs font-medium text-accent hover:underline">Responder</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-ink-faint">Todavía no hay foros.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
