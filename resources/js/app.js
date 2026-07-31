import './bootstrap';
import Chart from 'chart.js/auto';

/**
 * Los tokens de color de la app viven como custom properties "R G B"
 * (ver resources/css/app.css), pensadas para Tailwind (`rgb(var(--x) / <alpha>)`).
 * Chart.js no lee CSS de Tailwind, así que se las pasamos explícitamente aquí;
 * de lo contrario usa sus grises por defecto, invisibles sobre el tema oscuro.
 */
function colorToken(name, alpha = 1) {
    const rgb = getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    return `rgb(${rgb} / ${alpha})`;
}

function aplicarColoresDeTema(config) {
    const esOscuro = document.documentElement.classList.contains('dark');

    const textoTenue = colorToken('--color-text-dim');
    // En tema oscuro, --color-border ya resalta contra un fondo casi negro:
    // se atenúa con transparencia para que la grilla no compita con los datos.
    const grilla = colorToken('--color-border', esOscuro ? 0.35 : 1);

    config.options ??= {};
    config.options.color ??= textoTenue;

    config.options.scales ??= {};
    for (const eje of Object.values(config.options.scales)) {
        eje.ticks ??= {};
        eje.ticks.color ??= textoTenue;
        eje.grid ??= {};
        eje.grid.color ??= grilla;
    }

    return config;
}

document.addEventListener('alpine:init', () => {
    Alpine.data('chartCanvas', (config) => ({
        chart: null,
        init() {
            this.chart = new Chart(this.$el, aplicarColoresDeTema(config));
        },
        destroy() {
            this.chart?.destroy();
        },
    }));
});
