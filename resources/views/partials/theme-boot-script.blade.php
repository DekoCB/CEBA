{{--
    Aplica el tema y el estado del sidebar (colapsado o no) guardados antes
    del primer paint, evitando parpadeo. wire:navigate no recarga el
    documento -- solo reemplaza el <body> -- así que esto debe volver a
    correr en cada navegación (evento livewire:navigated), o el <html>
    nuevo que trae el servidor no sabría que el usuario había elegido modo
    oscuro / sidebar colapsado y la app volvería a su estado por defecto.
--}}
<script>
    function aplicarTemaCeba() {
        var stored = localStorage.getItem('ceba-theme');
        var isDark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.classList.toggle('dark', isDark);
    }

    function aplicarSidebarCeba() {
        document.documentElement.classList.toggle('sidebar-collapsed', localStorage.getItem('ceba-sidebar-collapsed') === '1');
    }

    aplicarTemaCeba();
    aplicarSidebarCeba();
    document.addEventListener('livewire:navigated', aplicarTemaCeba);
    document.addEventListener('livewire:navigated', aplicarSidebarCeba);
</script>
