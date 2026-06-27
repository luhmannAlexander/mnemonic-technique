import Chart from 'chart.js/auto';

// Expose Chart for the retention charts (Alpine x-init in <x-retention-chart>).
// Bundled via Vite so the app stays fully offline / local-first (no CDN).
window.Chart = Chart;
