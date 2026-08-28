@extends('layouts.app')

@section('title', __('Dashboard'))
@section('subtitle', __('Overview of your warehouse inventory'))

@section('content')

    {{-- Primary stat cards — all values from DashboardController --}}
    <div class="stat-grid section">

        <div class="stat-card">
            <div class="stat-card-top">
                <div class="stat-icon blue"><i class="fa-solid fa-box"></i></div>
            </div>
            <div class="stat-value">{{ number_format($totalProducts) }}</div>
            <div class="stat-label">{{ __('Total Products') }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <div class="stat-icon green"><i class="fa-solid fa-boxes-stacked"></i></div>
            </div>
            <div class="stat-value">{{ number_format($totalStock) }}</div>
            <div class="stat-label">{{ __('Total Stock') }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <div class="stat-icon amber"><i class="fa-solid fa-truck-field"></i></div>
            </div>
            <div class="stat-value">{{ number_format($totalSuppliers) }}</div>
            <div class="stat-label">{{ __('Suppliers') }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <div class="stat-icon gray"><i class="fa-solid fa-truck-fast"></i></div>
            </div>
            <div class="stat-value">{{ number_format($totalDistributors) }}</div>
            <div class="stat-label">{{ __('Distributors') }}</div>
        </div>

    </div>

    {{-- Secondary stat cards --}}
    <div class="stat-grid section">

        <div class="stat-card">
            <div class="stat-card-top">
                <div class="stat-icon green"><i class="fa-solid fa-inbox"></i></div>
            </div>
            <div class="stat-value">{{ number_format($stockInToday) }}</div>
            <div class="stat-label">{{ __('Stock In Today') }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <div class="stat-icon blue"><i class="fa-solid fa-arrow-up-from-bracket"></i></div>
            </div>
            <div class="stat-value">{{ number_format($stockOutToday) }}</div>
            <div class="stat-label">{{ __('Stock Out Today') }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <div class="stat-icon red"><i class="fa-solid fa-triangle-exclamation"></i></div>
            </div>
            <div class="stat-value">{{ number_format($lowStockCount) }}</div>
            <div class="stat-label">{{ __('Low Stock Items') }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <div class="stat-icon amber"><i class="fa-solid fa-chart-column"></i></div>
            </div>
            <div class="stat-value">{{ number_format($stockInLast30 - $stockOutLast30) }}</div>
            <div class="stat-label">{{ __('Stock Overview') }}</div>
        </div>

    </div>

    {{-- Stock overview chart --}}
    <div class="card section">
        <div class="card-header">
            <div>
                <h2>{{ __('Stock Overview') }}</h2>
                <p>{{ __('Stock in vs. stock out over the last 30 days') }}</p>
            </div>
        </div>
        <div class="card-body">
            <div class="stock-chart-wrap">
                <div class="stock-chart-legend" aria-hidden="true">
                    <span><i class="legend-dot legend-in"></i>{{ __('IN') }}</span>
                    <span><i class="legend-dot legend-out"></i>{{ __('OUT') }}</span>
                </div>
                <canvas
                    id="stockOverviewChart"
                    class="stock-chart"
                    height="260"
                    aria-label="{{ __('Stock in vs. stock out over the last 30 days') }}"
                    role="img"></canvas>
                <div id="stockChartTooltip" class="stock-chart-tooltip" hidden></div>
            </div>
        </div>
    </div>

    {{-- Recent stock movements from the database (Full Width) --}}
    <div class="card section">
        <div class="card-header">
            <div>
                <h2>{{ __('Recent Stock Movements') }}</h2>
                <p>{{ __('Latest activity across all warehouses') }}</p>
            </div>
            <a href="{{ route('stock-movements.index') }}" class="btn btn-secondary btn-sm">{{ __('View all') }}</a>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('Warehouse') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Quantity') }}</th>
                        <th>{{ __('Reference') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentMovements as $m)
                        <tr>
                            <td class="cell-muted">{{ $m->created_at->translatedFormat('d M Y') }}</td>
                            <td class="cell-primary">{{ $m->product->name }}</td>
                            <td class="cell-muted">{{ $m->warehouse->name }}</td>
                            <td>
                                @if ($m->type === 'IN')
                                    <span class="badge badge-green">{{ __('IN') }}</span>
                                @else
                                    <span class="badge badge-red">{{ __('OUT') }}</span>
                                @endif
                            </td>
                            <td class="cell-mono">
                                @if ($m->type === 'IN')
                                    +{{ $m->quantity }}
                                @else
                                    -{{ $m->quantity }}
                                @endif
                            </td>
                            <td class="cell-mono">
                                @if ($m->reference_type && $m->reference_id)
                                    {{ Str::of($m->reference_type)->replace('_', ' ')->title() }} #{{ $m->reference_id }}
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center; padding:30px; color:#888;">
                                {{ __('No movements yet. Create a Stock In to get started.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Low stock products from the database (Full Width) --}}
    <div class="card section">
        <div class="card-header">
            <div>
                <h2>{{ __('Low Stock Products') }}</h2>
                <p>{{ __('Items at or below their minimum threshold') }}</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('SKU') }}</th>
                        <th>{{ __('Warehouse') }}</th>
                        <th>{{ __('Current Stock') }}</th>
                        <th>{{ __('Minimum Stock') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lowStockProducts as $item)
                        <tr>
                            <td class="cell-primary">{{ $item->product_name }}</td>
                            <td class="cell-mono">{{ $item->product_sku }}</td>
                            <td class="cell-muted">{{ $item->warehouse_name }}</td>
                            <td class="cell-mono">{{ $item->current_stock }}</td>
                            <td class="cell-mono">{{ $item->minimum_stock }}</td>
                            <td><span class="badge badge-red">{{ __('Low Stock') }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center; padding:30px; color:#888;">
                                {{ __('All products are adequately stocked.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const canvas = document.getElementById('stockOverviewChart');
            if (!canvas) {
                return;
            }

            const chartData = @json($stockOverviewChart);
            const ctx = canvas.getContext('2d');
            const css = getComputedStyle(document.documentElement);
            const tooltip = document.getElementById('stockChartTooltip');
            let points = [];
            let activePoint = null;

            const colors = {
                text: css.getPropertyValue('--color-text-muted').trim() || '#6B7686',
                border: css.getPropertyValue('--color-border').trim() || '#E4E8EE',
                stockIn: css.getPropertyValue('--color-success').trim() || '#0F9D58',
                stockOut: css.getPropertyValue('--color-danger').trim() || '#D93025',
            };

            function drawChart() {
                const rect = canvas.getBoundingClientRect();
                const ratio = window.devicePixelRatio || 1;
                canvas.width = Math.max(1, Math.floor(rect.width * ratio));
                canvas.height = Math.max(1, Math.floor(260 * ratio));
                ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                ctx.clearRect(0, 0, rect.width, 260);
                points = [];

                const padding = { top: 18, right: 18, bottom: 34, left: 44 };
                const width = rect.width - padding.left - padding.right;
                const height = 260 - padding.top - padding.bottom;
                const maxValue = Math.max(10, ...chartData.stockIn, ...chartData.stockOut);
                const stepX = width / Math.max(1, chartData.labels.length - 1);

                ctx.font = '12px Inter, sans-serif';
                ctx.lineWidth = 1;
                ctx.strokeStyle = colors.border;
                ctx.fillStyle = colors.text;

                for (let i = 0; i <= 4; i++) {
                    const y = padding.top + (height / 4) * i;
                    const value = Math.round(maxValue - (maxValue / 4) * i);

                    ctx.beginPath();
                    ctx.moveTo(padding.left, y);
                    ctx.lineTo(padding.left + width, y);
                    ctx.stroke();
                    ctx.fillText(value.toLocaleString(), 8, y + 4);
                }

                function yFor(value) {
                    return padding.top + height - (value / maxValue) * height;
                }

                function drawLine(values, color, type) {
                    ctx.strokeStyle = color;
                    ctx.lineWidth = 2.5;
                    ctx.beginPath();

                    values.forEach((value, index) => {
                        const x = padding.left + stepX * index;
                        const y = yFor(value);

                        if (index === 0) {
                            ctx.moveTo(x, y);
                        } else {
                            ctx.lineTo(x, y);
                        }
                    });

                    ctx.stroke();

                    ctx.fillStyle = color;
                    values.forEach((value, index) => {
                        const x = padding.left + stepX * index;
                        const y = yFor(value);
                        ctx.beginPath();
                        ctx.arc(x, y, activePoint && activePoint.type === type && activePoint.index === index ? 5 : 3, 0, Math.PI * 2);
                        ctx.fill();

                        points.push({
                            x,
                            y,
                            type,
                            index,
                            value,
                            label: chartData.labels[index],
                            transactions: chartData.transactions[type][index] || [],
                        });
                    });
                }

                drawLine(chartData.stockIn, colors.stockIn, 'IN');
                drawLine(chartData.stockOut, colors.stockOut, 'OUT');

                ctx.fillStyle = colors.text;
                ctx.textAlign = 'center';
                const labelIndexes = [0, 6, 13, 20, 29].filter((index) => index < chartData.labels.length);

                labelIndexes.forEach((index) => {
                    const x = padding.left + stepX * index;
                    ctx.fillText(chartData.labels[index], x, 246);
                });

                ctx.textAlign = 'start';
            }

            function nearestPoint(event) {
                const rect = canvas.getBoundingClientRect();
                const x = event.clientX - rect.left;
                const y = event.clientY - rect.top;

                return points.reduce((nearest, point) => {
                    const distance = Math.hypot(point.x - x, point.y - y);

                    if (distance > 14 || (nearest && nearest.distance <= distance)) {
                        return nearest;
                    }

                    return { ...point, distance };
                }, null);
            }

            function showTooltip(point) {
                if (!tooltip || !point) {
                    return;
                }

                const transactions = point.transactions;
                const rows = transactions.length
                    ? transactions.map((transaction) => `
                        <div class="stock-chart-tooltip-row">
                            <span>${transaction.label}</span>
                            <strong>${transaction.quantity.toLocaleString()}</strong>
                        </div>
                    `).join('')
                    : `<div class="stock-chart-tooltip-empty">{{ __('No stock document for this point') }}</div>`;

                tooltip.innerHTML = `
                    <div class="stock-chart-tooltip-head">
                        <span>${point.type} &middot; ${point.label}</span>
                        <strong>${point.value.toLocaleString()}</strong>
                    </div>
                    ${rows}
                    ${transactions.length ? `<div class="stock-chart-tooltip-hint">{{ __('Click to open the transaction') }}</div>` : ''}
                `;

                tooltip.hidden = false;

                const left = Math.min(Math.max(point.x - 120, 8), canvas.clientWidth - 248);
                const top = Math.max(point.y - tooltip.offsetHeight - 18, 8);

                tooltip.style.left = `${left}px`;
                tooltip.style.top = `${top}px`;
            }

            function hideTooltip() {
                if (tooltip) {
                    tooltip.hidden = true;
                }
            }

            drawChart();
            window.addEventListener('resize', drawChart);

            canvas.addEventListener('mousemove', (event) => {
                const point = nearestPoint(event);

                if (!point) {
                    activePoint = null;
                    canvas.style.cursor = 'default';
                    hideTooltip();
                    drawChart();
                    return;
                }

                const changed = !activePoint || activePoint.type !== point.type || activePoint.index !== point.index;
                activePoint = point;
                canvas.style.cursor = point.transactions.length ? 'pointer' : 'default';
                showTooltip(point);

                if (changed) {
                    drawChart();
                }
            });

            canvas.addEventListener('mouseleave', () => {
                activePoint = null;
                canvas.style.cursor = 'default';
                hideTooltip();
                drawChart();
            });

            canvas.addEventListener('click', (event) => {
                const point = nearestPoint(event);
                const firstTransaction = point?.transactions?.[0];

                if (firstTransaction?.url) {
                    window.location.href = firstTransaction.url;
                }
            });
        });
    </script>
@endpush
