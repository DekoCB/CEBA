<?php

use App\Modules\Landing\Services\SolicitudContactoService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.landing')] class extends Component
{
    private const NOMBRE_INSTITUCION = 'CEBA Peruano Británico';

    private const WHATSAPP_NUMERO = '51978351141';

    private const EMAIL_CONTACTO = 'colegiocebaperuanobritanico@gmail.com';

    private const DIRECCION = 'Sede UGEL 05 - San Juan de Lurigancho, Lima, Perú';

    public string $nombre = '';

    public string $email = '';

    public string $telefono = '';

    public string $programaInteres = '';

    public string $mensaje = '';

    public bool $enviado = false;

    public string $filtroNoticias = 'todos';

    public function enviarMensaje(SolicitudContactoService $service): void
    {
        $validado = $this->validate([
            'nombre' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'telefono' => 'required|string|max:30',
            'programaInteres' => 'nullable|string|max:100',
            'mensaje' => 'required|string|max:2000',
        ]);

        $service->registrar(
            $validado['nombre'],
            $validado['email'],
            $validado['telefono'],
            $validado['programaInteres'] ?: null,
            $validado['mensaje'],
        );

        $this->reset(['nombre', 'email', 'telefono', 'programaInteres', 'mensaje']);
        $this->enviado = true;
    }

    public function with(): array
    {
        return [
            'nombreInstitucion' => self::NOMBRE_INSTITUCION,
            'whatsappNumero' => self::WHATSAPP_NUMERO,
            'whatsappNumeroVisible' => '+51 978 351 141',
            'emailContacto' => self::EMAIL_CONTACTO,
            'direccion' => self::DIRECCION,
            'whatsappHref' => fn (string $mensaje) => 'https://wa.me/'.self::WHATSAPP_NUMERO.'?text='.rawurlencode($mensaje),
        ];
    }
}; ?>

<div class="min-h-screen">
    @include('livewire.landing.partials._header')
    @include('livewire.landing.partials._hero')
    @include('livewire.landing.partials._nosotros')
    @include('livewire.landing.partials._programas')
    @include('livewire.landing.partials._admision')
    @include('livewire.landing.partials._beneficios')
    @include('livewire.landing.partials._docentes')
    @include('livewire.landing.partials._noticias')
    @include('livewire.landing.partials._contacto')
    @include('livewire.landing.partials._footer')

    <x-landing.whatsapp-float-button :href="$whatsappHref('Hola, quiero información sobre la matrícula en '.$nombreInstitucion.'.')" />
</div>
