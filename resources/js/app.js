import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Chart = Chart;
window.Alpine = Alpine;

Alpine.data('chart', (config) => ({
    chart: null,

    init() {
        const { type, data, options } = config;

        if (!this.$refs.canvas) {
            return;
        }

        this.chart = new Chart(this.$refs.canvas.getContext('2d'), {
            type,
            data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                },
                ...options,
            },
        });
    },

    destroy() {
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
    },
}));

Alpine.start();
