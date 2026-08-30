<div class="report-analytics section">
    <div class="report-metric-grid">
        @foreach ($analytics['metrics'] as $metric)
            <div class="report-metric">
                <div class="stat-icon {{ $metric['tone'] }}"><i class="fa-solid {{ $metric['icon'] }}"></i></div>
                <div>
                    <strong>{{ $metric['value'] }}</strong>
                    <span>{{ __($metric['label']) }}</span>
                </div>
            </div>
        @endforeach
    </div>

    <div class="report-analysis-grid">
        <div class="report-chart-panel">
            <div class="report-panel-head">
                <h3>{{ __($analytics['chart']['title']) }}</h3>
            </div>
            <canvas id="{{ $chartId }}" class="report-chart" height="240" role="img" aria-label="{{ __($analytics['chart']['title']) }}"></canvas>
        </div>

        <div class="report-insight-panel">
            <div class="report-panel-head">
                <h3>{{ __('Analysis') }}</h3>
            </div>
            <div class="report-insight-list">
                @forelse ($analytics['insights'] as $insight)
                    <div class="report-insight {{ $insight['tone'] }}">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>{{ __($insight['text']) }}</span>
                    </div>
                @empty
                    <div class="report-insight gray">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>{{ __('No analysis available for the current filters.') }}</span>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            window.InventoryReportCharts = window.InventoryReportCharts || {
                colors() {
                    const css = getComputedStyle(document.documentElement);

                    return {
                        blue: css.getPropertyValue('--color-info').trim() || '#2563EB',
                        green: css.getPropertyValue('--color-success').trim() || '#0F9D58',
                        amber: css.getPropertyValue('--color-warning').trim() || '#C9791A',
                        red: css.getPropertyValue('--color-danger').trim() || '#D93025',
                        gray: css.getPropertyValue('--color-neutral').trim() || '#5B6472',
                        text: css.getPropertyValue('--color-text-muted').trim() || '#6B7686',
                        border: css.getPropertyValue('--color-border').trim() || '#E4E8EE',
                    };
                },

                draw(id, data) {
                    const canvas = document.getElementById(id);
                    if (!canvas || !data) {
                        return;
                    }

                    const ctx = canvas.getContext('2d');
                    const palette = this.colors();

                    function render() {
                        const rect = canvas.getBoundingClientRect();
                        const ratio = window.devicePixelRatio || 1;
                        const height = 240;
                        const labels = data.labels || [];
                        const datasets = data.datasets || [];
                        const maxValue = Math.max(1, ...datasets.flatMap((dataset) => dataset.values || []));

                        canvas.width = Math.max(1, Math.floor(rect.width * ratio));
                        canvas.height = Math.max(1, Math.floor(height * ratio));
                        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                        ctx.clearRect(0, 0, rect.width, height);

                        const padding = { top: 18, right: 16, bottom: 50, left: 44 };
                        const width = rect.width - padding.left - padding.right;
                        const chartHeight = height - padding.top - padding.bottom;

                        ctx.font = '12px Inter, sans-serif';
                        ctx.lineWidth = 1;
                        ctx.strokeStyle = palette.border;
                        ctx.fillStyle = palette.text;

                        for (let i = 0; i <= 4; i++) {
                            const y = padding.top + (chartHeight / 4) * i;
                            const value = Math.round(maxValue - (maxValue / 4) * i);

                            ctx.beginPath();
                            ctx.moveTo(padding.left, y);
                            ctx.lineTo(padding.left + width, y);
                            ctx.stroke();
                            ctx.fillText(value.toLocaleString(), 8, y + 4);
                        }

                        if (!labels.length || !datasets.length) {
                            ctx.textAlign = 'center';
                            ctx.fillText('{{ __('No chart data for these filters') }}', rect.width / 2, height / 2);
                            ctx.textAlign = 'start';
                            return;
                        }

                        const groupWidth = width / labels.length;
                        const gap = Math.min(18, groupWidth * 0.25);
                        const barWidth = Math.max(2, Math.min(24, ((groupWidth - gap) / datasets.length) - 2));

                        datasets.forEach((dataset, datasetIndex) => {
                            ctx.fillStyle = palette[dataset.color] || palette.blue;

                            (dataset.values || []).forEach((value, index) => {
                                const barHeight = (value / maxValue) * chartHeight;
                                const setWidth = barWidth * datasets.length;
                                const x = padding.left + groupWidth * index + (groupWidth - setWidth) / 2 + barWidth * datasetIndex;
                                const y = padding.top + chartHeight - barHeight;

                                ctx.fillRect(x, y, Math.max(1, barWidth - 2), barHeight);
                            });
                        });

                        ctx.fillStyle = palette.text;
                        ctx.textAlign = 'center';
                        const labelStep = Math.max(1, Math.ceil(labels.length / Math.max(1, Math.floor(width / 82))));

                        labels.forEach((label, index) => {
                            if (index % labelStep !== 0 && index !== labels.length - 1) {
                                return;
                            }

                            const x = padding.left + groupWidth * index + groupWidth / 2;
                            const shortLabel = String(label).length > 14 ? String(label).slice(0, 13) + '...' : String(label);
                            ctx.fillText(shortLabel, x, height - 22);
                        });

                        ctx.textAlign = 'start';

                        if (datasets.length > 1) {
                            let legendX = padding.left;
                            datasets.forEach((dataset) => {
                                ctx.fillStyle = palette[dataset.color] || palette.blue;
                                ctx.fillRect(legendX, height - 10, 8, 8);
                                ctx.fillStyle = palette.text;
                                ctx.fillText(dataset.label, legendX + 13, height - 2);
                                legendX += ctx.measureText(dataset.label).width + 42;
                            });
                        }
                    }

                    render();
                    window.addEventListener('resize', render);
                },
            };
        </script>
    @endpush
@endonce

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            window.InventoryReportCharts.draw(@json($chartId), @json($analytics['chart']));
        });
    </script>
@endpush
