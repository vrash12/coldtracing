{{--resources/views/admin/reports/index.blade.php--}}
@extends('layouts.app')

@section('title', 'Reports')

@section('content')

<div class="reports-page">

    <div class="reports-header">
        <div>
            <span class="eyebrow">Administrator Reports</span>
            <h1>ColdTrace Reports</h1>
            <p>
                Review orders, trips, products, drivers, telemetry, temperature breaches,
                and cold-chain alerts for the selected date range.
            </p>
        </div>

        <div class="header-actions">
            <a href="{{ route('reports.export', ['type' => 'orders', 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}" class="secondary-button">
                Export Orders
            </a>

            <a href="{{ route('reports.export', ['type' => 'telemetry', 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}" class="primary-button">
                Export Telemetry
            </a>
        </div>
    </div>

    <section class="filter-panel">
        <form method="GET" action="{{ route('reports.index') }}" class="filter-form">
            <div class="form-group">
                <label for="from">From</label>
                <input type="date" id="from" name="from" value="{{ $from->format('Y-m-d') }}">
            </div>

            <div class="form-group">
                <label for="to">To</label>
                <input type="date" id="to" name="to" value="{{ $to->format('Y-m-d') }}">
            </div>

            <button type="submit" class="primary-button">
                Apply Filter
            </button>

            <a href="{{ route('reports.index') }}" class="secondary-button">
                Reset
            </a>
        </form>
    </section>

    <section class="summary-grid">
        <div class="metric-card">
            <span>Total Orders</span>
            <strong>{{ $summary['total_orders'] }}</strong>
            <small>{{ $summary['delivered_orders'] }} delivered</small>
        </div>

        <div class="metric-card blue">
            <span>Active Orders</span>
            <strong>{{ $summary['active_orders'] }}</strong>
            <small>Assigned or in transit</small>
        </div>

        <div class="metric-card green">
            <span>Trips</span>
            <strong>{{ $summary['total_trips'] }}</strong>
            <small>{{ $summary['active_trips'] }} active trips</small>
        </div>

        <div class="metric-card danger">
            <span>Critical Alerts</span>
            <strong>{{ $summary['critical_alerts'] }}</strong>
            <small>{{ $summary['total_alerts'] }} total alerts</small>
        </div>

        <div class="metric-card amber">
            <span>Temp Breaches</span>
            <strong>{{ $summary['temperature_breaches'] }}</strong>
            <small>Outside product safe range</small>
        </div>

        <div class="metric-card cyan">
            <span>Avg Temp</span>
            <strong>{{ $summary['avg_temperature'] ?: 'N/A' }}°C</strong>
            <small>{{ $summary['active_devices'] }} active devices</small>
        </div>
    </section>

    <section class="charts-grid">
        <div class="chart-card wide">
            <div class="chart-header">
                <div>
                    <h2>Daily Orders</h2>
                    <p>Total orders created per day in the selected date range.</p>
                </div>
            </div>

            <div class="chart-box">
                <canvas id="dailyOrdersChart"></canvas>
            </div>
        </div>

        <div class="chart-card wide">
            <div class="chart-header">
                <div>
                    <h2>Average Temperature</h2>
                    <p>Average recorded cargo temperature per day.</p>
                </div>
            </div>

            <div class="chart-box">
                <canvas id="dailyTemperatureChart"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-header">
                <div>
                    <h2>Orders by Status</h2>
                    <p>Order distribution by current status.</p>
                </div>
            </div>

            <div class="chart-box">
                <canvas id="ordersStatusChart"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-header">
                <div>
                    <h2>Trips by Status</h2>
                    <p>Trip distribution by current status.</p>
                </div>
            </div>

            <div class="chart-box">
                <canvas id="tripsStatusChart"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-header">
                <div>
                    <h2>Alerts by Severity</h2>
                    <p>Cold-chain alert severity breakdown.</p>
                </div>
            </div>

            <div class="chart-box">
                <canvas id="alertsSeverityChart"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-header">
                <div>
                    <h2>Top Products</h2>
                    <p>Most ordered products in this period.</p>
                </div>
            </div>

            <div class="chart-box">
                <canvas id="topProductsChart"></canvas>
            </div>
        </div>

        <div class="chart-card wide">
            <div class="chart-header">
                <div>
                    <h2>Driver Performance</h2>
                    <p>Total assigned orders versus delivered orders.</p>
                </div>
            </div>

            <div class="chart-box">
                <canvas id="driverPerformanceChart"></canvas>
            </div>
        </div>
    </section>

    <div class="reports-grid">
        <section class="report-card">
            <div class="card-header">
                <h2>Orders by Status</h2>
                <a href="{{ route('reports.export', ['type' => 'orders', 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}">
                    Export CSV
                </a>
            </div>

            <div class="status-list">
                @forelse ($ordersByStatus as $row)
                    <div class="status-row">
                        <span class="status-badge status-{{ $row->status }}">
                            {{ ucfirst(str_replace('_', ' ', $row->status)) }}
                        </span>
                        <strong>{{ $row->total }}</strong>
                    </div>
                @empty
                    <div class="empty-box">No orders found for this date range.</div>
                @endforelse
            </div>
        </section>

        <section class="report-card">
            <div class="card-header">
                <h2>Trips by Status</h2>
                <a href="{{ route('reports.export', ['type' => 'trips', 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}">
                    Export CSV
                </a>
            </div>

            <div class="status-list">
                @forelse ($tripsByStatus as $row)
                    <div class="status-row">
                        <span class="status-badge status-{{ $row->status }}">
                            {{ ucfirst(str_replace('_', ' ', $row->status)) }}
                        </span>
                        <strong>{{ $row->total }}</strong>
                    </div>
                @empty
                    <div class="empty-box">No trips found for this date range.</div>
                @endforelse
            </div>
        </section>

        <section class="report-card">
            <div class="card-header">
                <h2>Alerts by Severity</h2>
                <a href="{{ route('reports.export', ['type' => 'alerts', 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}">
                    Export CSV
                </a>
            </div>

            <div class="status-list">
                @forelse ($alertsBySeverity as $row)
                    <div class="status-row">
                        <span class="severity-badge severity-{{ $row->severity }}">
                            {{ ucfirst($row->severity) }}
                        </span>
                        <strong>{{ $row->total }}</strong>
                    </div>
                @empty
                    <div class="empty-box">No alerts found for this date range.</div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="report-card full">
        <div class="card-header">
            <h2>Top Products</h2>
        </div>

        <div class="table-wrapper">
            <table class="reports-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Orders</th>
                        <th>Total Quantity</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($topProducts as $product)
                        <tr>
                            <td>{{ $product->name }}</td>
                            <td>{{ $product->orders_count }}</td>
                            <td>{{ number_format((float) $product->total_quantity, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">No product data found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="report-card full">
        <div class="card-header">
            <h2>Driver Performance</h2>
        </div>

        <div class="table-wrapper">
            <table class="reports-table">
                <thead>
                    <tr>
                        <th>Driver</th>
                        <th>Email</th>
                        <th>Total Orders</th>
                        <th>In Transit</th>
                        <th>Delivered</th>
                        <th>Cancelled</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($driverPerformance as $driver)
                        <tr>
                            <td>{{ $driver->name }}</td>
                            <td>{{ $driver->email }}</td>
                            <td>{{ $driver->total_orders ?? 0 }}</td>
                            <td>{{ $driver->in_transit_orders ?? 0 }}</td>
                            <td>{{ $driver->delivered_orders ?? 0 }}</td>
                            <td>{{ $driver->cancelled_orders ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">No driver data found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="report-card full">
        <div class="card-header">
            <h2>Recent Orders</h2>
        </div>

        <div class="table-wrapper">
            <table class="reports-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Receiver</th>
                        <th>Driver</th>
                        <th>Products</th>
                        <th>Expected</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($recentOrders as $order)
                        <tr>
                            <td>{{ $order->order_code }}</td>
                            <td>{{ $order->receiver?->name ?? 'No receiver' }}</td>
                            <td>{{ $order->driver?->name ?? 'No driver' }}</td>
                            <td>
                                @forelse ($order->orderItems as $item)
                                    <div>
                                        {{ $item->product?->name ?? 'N/A' }}
                                        -
                                        {{ $item->quantity }} {{ $item->unit }}
                                    </div>
                                @empty
                                    N/A
                                @endforelse
                            </td>
                            <td>{{ $order->expected_delivery_at?->format('M d, Y h:i A') ?? 'Not set' }}</td>
                            <td>
                                <span class="status-badge status-{{ $order->status }}">
                                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">No recent orders found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="reports-grid two-columns">
        <section class="report-card">
            <div class="card-header">
                <h2>Recent Alerts</h2>
            </div>

            <div class="alert-list">
                @forelse ($recentAlerts as $alert)
                    <div class="alert-item alert-{{ $alert->severity }}">
                        <strong>{{ ucfirst(str_replace('_', ' ', $alert->type)) }}</strong>
                        <span>{{ $alert->message }}</span>
                        <small>
                            {{ $alert->trip?->truck?->plate_number ?? 'No truck' }}
                            ·
                            {{ $alert->created_at?->format('M d, Y h:i A') }}
                        </small>
                    </div>
                @empty
                    <div class="empty-box">No recent alerts.</div>
                @endforelse
            </div>
        </section>

        <section class="report-card">
            <div class="card-header">
                <h2>Latest Telemetry</h2>
                <a href="{{ route('reports.export', ['type' => 'telemetry', 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}">
                    Export CSV
                </a>
            </div>

            <div class="telemetry-list">
                @forelse ($latestTelemetry as $log)
                    <div class="telemetry-item">
                        <strong>
                            {{ $log->device?->device_code ?? 'No device' }}
                        </strong>

                        <span>
                            {{ $log->temperature !== null ? $log->temperature . '°C' : 'No temp' }}
                            ·
                            {{ $log->humidity !== null ? $log->humidity . '% humidity' : 'No humidity' }}
                        </span>

                        <small>
                            GPS:
                            {{ $log->latitude ?? 'N/A' }},
                            {{ $log->longitude ?? 'N/A' }}
                            ·
                            {{ $log->recorded_at?->format('M d, Y h:i A') }}
                        </small>
                    </div>
                @empty
                    <div class="empty-box">No telemetry found.</div>
                @endforelse
            </div>
        </section>
    </div>

</div>

@endsection

@push('styles')
<style>
    .reports-page {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .reports-header,
    .filter-panel,
    .report-card,
    .metric-card,
    .chart-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.07);
    }

    .reports-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 24px;
        padding: 24px;
        border-radius: 24px;
        background:
            radial-gradient(circle at top left, rgba(34, 211, 238, 0.18), transparent 35%),
            linear-gradient(135deg, #ffffff, #f8fafc);
    }

    .eyebrow {
        display: inline-flex;
        width: fit-content;
        background: #ecfeff;
        color: #0891b2;
        border: 1px solid #cffafe;
        border-radius: 999px;
        padding: 7px 12px;
        font-size: 12px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 12px;
    }

    .reports-header h1 {
        margin: 0;
        color: #0f172a;
        font-size: 34px;
        font-weight: 950;
        letter-spacing: -0.05em;
    }

    .reports-header p {
        margin: 8px 0 0;
        color: #64748b;
        line-height: 1.6;
        max-width: 760px;
    }

    .header-actions,
    .filter-form,
    .card-header,
    .status-row {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .header-actions {
        justify-content: flex-end;
        flex-wrap: wrap;
    }

    .primary-button,
    .secondary-button,
    .card-header a {
        min-height: 44px;
        border-radius: 999px;
        padding: 0 18px;
        border: 0;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        white-space: nowrap;
    }

    .primary-button {
        background: linear-gradient(135deg, #2563eb, #06b6d4);
        color: #ffffff;
        box-shadow: 0 12px 22px rgba(37, 99, 235, 0.22);
    }

    .secondary-button,
    .card-header a {
        background: #f1f5f9;
        color: #0f172a;
        border: 1px solid #e2e8f0;
    }

    .filter-panel {
        border-radius: 22px;
        padding: 18px;
    }

    .filter-form {
        flex-wrap: wrap;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .form-group label {
        color: #64748b;
        font-size: 12px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .form-group input {
        height: 44px;
        border: 1px solid #cbd5e1;
        border-radius: 14px;
        padding: 0 12px;
        outline: none;
        color: #0f172a;
        font-weight: 800;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 14px;
    }

    .metric-card {
        border-radius: 20px;
        padding: 18px;
    }

    .metric-card span {
        display: block;
        color: #64748b;
        font-size: 11px;
        font-weight: 950;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 8px;
    }

    .metric-card strong {
        display: block;
        color: #0f172a;
        font-size: 28px;
        font-weight: 950;
        line-height: 1;
    }

    .metric-card small {
        display: block;
        margin-top: 8px;
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
    }

    .metric-card.blue strong { color: #2563eb; }
    .metric-card.green strong { color: #16a34a; }
    .metric-card.danger strong { color: #dc2626; }
    .metric-card.amber strong { color: #d97706; }
    .metric-card.cyan strong { color: #0891b2; }

    .charts-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }

    .chart-card {
        min-width: 0;
        border-radius: 24px;
        padding: 20px;
    }

    .chart-card.wide {
        grid-column: span 2;
    }

    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 16px;
    }

    .chart-header h2 {
        margin: 0;
        color: #0f172a;
        font-size: 20px;
        font-weight: 950;
        letter-spacing: -0.03em;
    }

    .chart-header p {
        margin: 6px 0 0;
        color: #64748b;
        font-size: 13px;
        font-weight: 700;
        line-height: 1.5;
    }

    .chart-box {
        position: relative;
        width: 100%;
        height: 330px;
    }

    .chart-empty {
        height: 100%;
        border-radius: 20px;
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        color: #64748b;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
        text-align: center;
        padding: 20px;
    }

    .chart-empty strong {
        color: #0f172a;
        font-size: 16px;
        font-weight: 950;
    }

    .chart-empty span {
        font-size: 13px;
        font-weight: 800;
        line-height: 1.5;
    }

    .reports-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }

    .reports-grid.two-columns {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .report-card {
        border-radius: 24px;
        padding: 20px;
        min-width: 0;
    }

    .report-card.full {
        width: 100%;
    }

    .card-header {
        justify-content: space-between;
        margin-bottom: 16px;
    }

    .card-header h2 {
        margin: 0;
        color: #0f172a;
        font-size: 20px;
        font-weight: 950;
        letter-spacing: -0.03em;
    }

    .card-header a {
        min-height: 34px;
        padding: 0 12px;
        font-size: 12px;
    }

    .status-list,
    .alert-list,
    .telemetry-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .status-row {
        justify-content: space-between;
        padding: 12px;
        border-radius: 16px;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
    }

    .status-row strong {
        color: #0f172a;
        font-size: 18px;
        font-weight: 950;
    }

    .status-badge,
    .severity-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 30px;
        border-radius: 999px;
        padding: 7px 11px;
        font-size: 12px;
        font-weight: 900;
        white-space: nowrap;
    }

    .status-pending { background: #fffbeb; color: #d97706; }
    .status-approved { background: #dbeafe; color: #2563eb; }
    .status-assigned { background: #ecfeff; color: #0891b2; }
    .status-in_transit { background: #f5f3ff; color: #7c3aed; }
    .status-delivered,
    .status-completed { background: #f0fdf4; color: #16a34a; }
    .status-cancelled { background: #fef2f2; color: #dc2626; }
    .status-in_progress { background: #ecfeff; color: #0891b2; }

    .severity-info { background: #eff6ff; color: #2563eb; }
    .severity-warning { background: #fffbeb; color: #d97706; }
    .severity-critical { background: #fef2f2; color: #dc2626; }

    .table-wrapper {
        width: 100%;
        overflow-x: auto;
    }

    .reports-table {
        width: 100%;
        border-collapse: collapse;
    }

    .reports-table th,
    .reports-table td {
        padding: 14px;
        border-bottom: 1px solid #e5e7eb;
        text-align: left;
        vertical-align: top;
    }

    .reports-table th {
        background: #f8fafc;
        color: #64748b;
        font-size: 12px;
        font-weight: 950;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .reports-table td {
        color: #334155;
        font-size: 14px;
        font-weight: 700;
    }

    .alert-item,
    .telemetry-item,
    .empty-box {
        padding: 14px;
        border-radius: 16px;
        border: 1px solid #e5e7eb;
        background: #f8fafc;
    }

    .alert-item {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .alert-item strong,
    .telemetry-item strong {
        color: #0f172a;
        font-weight: 950;
    }

    .alert-item span,
    .telemetry-item span {
        color: #334155;
        font-size: 13px;
        line-height: 1.45;
    }

    .alert-item small,
    .telemetry-item small,
    .empty-box {
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        line-height: 1.45;
    }

    .alert-critical {
        background: #fef2f2;
        border-color: #fecaca;
    }

    .alert-warning {
        background: #fffbeb;
        border-color: #fde68a;
    }

    .telemetry-item {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    @media (max-width: 1200px) {
        .summary-grid,
        .charts-grid,
        .reports-grid,
        .reports-grid.two-columns {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .chart-card.wide {
            grid-column: span 2;
        }
    }

    @media (max-width: 760px) {
        .reports-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .summary-grid,
        .charts-grid,
        .reports-grid,
        .reports-grid.two-columns {
            grid-template-columns: 1fr;
        }

        .chart-card.wide {
            grid-column: span 1;
        }

        .chart-box {
            height: 290px;
        }

        .header-actions {
            width: 100%;
            justify-content: flex-start;
        }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    const reportChartData = @json($chartData);

    function chartHasValues(values) {
        return Array.isArray(values) && values.some(value => Number(value) > 0);
    }

    function getCanvas(id) {
        return document.getElementById(id);
    }

    function createNoDataMessage(canvasId, message = 'No data available for this chart.') {
        const canvas = getCanvas(canvasId);

        if (!canvas) {
            return;
        }

        const box = canvas.closest('.chart-box');

        if (!box) {
            return;
        }

        box.innerHTML = `
            <div class="chart-empty">
                <strong>No data</strong>
                <span>${message}</span>
            </div>
        `;
    }

    function createLineChart(canvasId, labels, values, label, suffix = '') {
        if (!chartHasValues(values)) {
            createNoDataMessage(canvasId);
            return;
        }

        new Chart(getCanvas(canvasId), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: label,
                        data: values,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.12)',
                        borderWidth: 3,
                        tension: 0.35,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8,
                            font: {
                                weight: 'bold'
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return `${context.dataset.label}: ${context.parsed.y}${suffix}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                weight: 'bold'
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return `${value}${suffix}`;
                            },
                            font: {
                                weight: 'bold'
                            }
                        }
                    }
                }
            }
        });
    }

    function createBarChart(canvasId, labels, values, label) {
        if (!chartHasValues(values)) {
            createNoDataMessage(canvasId);
            return;
        }

        new Chart(getCanvas(canvasId), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: label,
                        data: values,
                        backgroundColor: 'rgba(37, 99, 235, 0.75)',
                        borderColor: '#2563eb',
                        borderWidth: 1,
                        borderRadius: 10,
                        maxBarThickness: 54
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                weight: 'bold'
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: {
                                weight: 'bold'
                            }
                        }
                    }
                }
            }
        });
    }

    function createDoughnutChart(canvasId, labels, values, label) {
        if (!chartHasValues(values)) {
            createNoDataMessage(canvasId);
            return;
        }

        new Chart(getCanvas(canvasId), {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: label,
                        data: values,
                        backgroundColor: [
                            '#2563eb',
                            '#06b6d4',
                            '#16a34a',
                            '#f59e0b',
                            '#7c3aed',
                            '#dc2626',
                            '#64748b'
                        ],
                        borderColor: '#ffffff',
                        borderWidth: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8,
                            font: {
                                weight: 'bold'
                            }
                        }
                    }
                }
            }
        });
    }

    function createDriverPerformanceChart() {
        const data = reportChartData.driverPerformance;

        if (!chartHasValues(data.totalOrders)) {
            createNoDataMessage('driverPerformanceChart');
            return;
        }

        new Chart(getCanvas('driverPerformanceChart'), {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Total Orders',
                        data: data.totalOrders,
                        backgroundColor: 'rgba(37, 99, 235, 0.75)',
                        borderColor: '#2563eb',
                        borderWidth: 1,
                        borderRadius: 10,
                        maxBarThickness: 54
                    },
                    {
                        label: 'Delivered Orders',
                        data: data.deliveredOrders,
                        backgroundColor: 'rgba(22, 163, 74, 0.75)',
                        borderColor: '#16a34a',
                        borderWidth: 1,
                        borderRadius: 10,
                        maxBarThickness: 54
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8,
                            font: {
                                weight: 'bold'
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                weight: 'bold'
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: {
                                weight: 'bold'
                            }
                        }
                    }
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') {
            document.querySelectorAll('.chart-box').forEach(function (box) {
                box.innerHTML = `
                    <div class="chart-empty">
                        <strong>Chart library not loaded</strong>
                        <span>Please check your internet connection or install Chart.js locally.</span>
                    </div>
                `;
            });

            return;
        }

        createLineChart(
            'dailyOrdersChart',
            reportChartData.dailyOrders.labels,
            reportChartData.dailyOrders.values,
            'Orders'
        );

        createLineChart(
            'dailyTemperatureChart',
            reportChartData.dailyTemperature.labels,
            reportChartData.dailyTemperature.values,
            'Average Temperature',
            '°C'
        );

        createDoughnutChart(
            'ordersStatusChart',
            reportChartData.ordersByStatus.labels,
            reportChartData.ordersByStatus.values,
            'Orders'
        );

        createDoughnutChart(
            'tripsStatusChart',
            reportChartData.tripsByStatus.labels,
            reportChartData.tripsByStatus.values,
            'Trips'
        );

        createDoughnutChart(
            'alertsSeverityChart',
            reportChartData.alertsBySeverity.labels,
            reportChartData.alertsBySeverity.values,
            'Alerts'
        );

        createBarChart(
            'topProductsChart',
            reportChartData.topProducts.labels,
            reportChartData.topProducts.values,
            'Orders'
        );

        createDriverPerformanceChart();
    });
</script>
@endpush