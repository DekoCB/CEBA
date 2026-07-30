{{--
    Aplica el tema guardado antes del primer paint, evitando parpadeo.
    wire:navigate no recarga el documento -- solo reemplaza el <body> -- así
    que este script debe volver a correr en cada navegación (evento
    livewire:navigated), o el <html> nuevo que trae el servidor no sabría
    que el usuario había elegido modo oscuro y la app volvería a modo claro.
--}}
<script>
    function aplicarTemaCeba() {
        var stored = localStorage.getItem('ceba-theme');
        var isDark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.classList.toggle('dark', isDark);
    }

    aplicarTemaCeba();
    document.addEventListener('livewire:navigated', aplicarTemaCeba);
</script>
