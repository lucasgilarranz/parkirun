import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Chart = Chart;
window.Alpine = Alpine;

const targetLinePlugin = {
    id: 'targetLine',
    afterDraw(chart, args, options) {
        if (!options || options.value === undefined || options.value === null) {
            return;
        }

        const { ctx, chartArea, scales } = chart;
        const x = scales.x.getPixelForValue(options.value);

        ctx.save();
        ctx.strokeStyle = options.color ?? '#facc15';
        ctx.lineWidth = options.width ?? 2;
        ctx.beginPath();
        ctx.moveTo(x, chartArea.top);
        ctx.lineTo(x, chartArea.bottom);
        ctx.stroke();
        ctx.restore();
    },
};

Alpine.data('chart', (config) => ({
    chart: null,

    init() {
        const { type, data, options } = config;
        const targetLine = config.targetLine ?? data?.targetLine;

        if (!this.$refs.canvas) {
            return;
        }

        this.chart = new Chart(this.$refs.canvas.getContext('2d'), {
            type,
            data,
            options: {
                ...options,
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    targetLine,
                    ...(options?.plugins ?? {}),
                },
            },
            plugins: [targetLinePlugin],
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
