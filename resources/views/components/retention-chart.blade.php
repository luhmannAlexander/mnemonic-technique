@props([
    'labels' => [],
    'values' => [],
])

{{--
    Line chart for the retention trend (ImplementationPlan §4.3). Chart.js is
    bundled (window.Chart, see resources/js/app.js). `wire:ignore` keeps Livewire
    from re-rendering the canvas; Alpine's x-init re-runs after wire:navigate so
    the chart survives SPA navigation. Green line = progress (ContentGuidelines §2).
--}}
<div
    wire:ignore
    x-data="{
        chart: null,
        draw() {
            if (this.chart) {
                this.chart.destroy();
            }
            this.chart = new window.Chart(this.$refs.canvas, {
                type: 'line',
                data: {
                    labels: @js($labels),
                    datasets: [{
                        data: @js($values),
                        borderColor: '#34D399',
                        backgroundColor: 'transparent',
                        tension: 0.3,
                        spanGaps: true,
                        pointRadius: 2,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 500 },
                    scales: {
                        x: { ticks: { color: '#A6A6B3' }, grid: { color: '#2A2A38' } },
                        y: { min: 0, max: 100, ticks: { color: '#A6A6B3' }, grid: { color: '#2A2A38' } },
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: { backgroundColor: '#1E1E29' },
                    },
                },
            });
        },
    }"
    x-init="draw()"
    {{ $attributes->merge(['class' => 'h-64 w-full']) }}
>
    <canvas x-ref="canvas"></canvas>
</div>
