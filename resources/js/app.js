import './bootstrap';
import Chart from 'chart.js/auto';

document.addEventListener('alpine:init', () => {
    Alpine.data('chartCanvas', (config) => ({
        chart: null,
        init() {
            this.chart = new Chart(this.$el, config);
        },
        destroy() {
            this.chart?.destroy();
        },
    }));
});
