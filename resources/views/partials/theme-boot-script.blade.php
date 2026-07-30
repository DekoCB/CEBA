{{-- Aplica el tema guardado antes del primer paint, evitando parpadeo. --}}
<script>
    (function () {
        var stored = localStorage.getItem('ceba-theme');
        var isDark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.classList.toggle('dark', isDark);
    })();
</script>
