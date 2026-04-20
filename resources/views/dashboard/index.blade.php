@extends('layouts.app')

@section('content')
{{-- Chart.js CDN --}}
<script src="{{ asset('assets/js/chart.umd.min.js') }}"></script>
<style>
    @media (min-width: 768px) {
        #kpi-grid { grid-template-columns: repeat(4, 1fr) !important; }
    }
</style>

<div class="pt-16 pb-24 lg:pb-8 min-h-screen bg-slate-50 dark:bg-slate-950">
    <div class="max-w-[1600px] mx-auto px-4 sm:px-8 lg:px-10 py-8">

        {{-- ── Page Header ────────────────────────────────────────────── --}}
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-8 gap-4">
            <div>
                <h2 class="text-3xl lg:text-4xl font-extrabold tracking-tight text-slate-900 dark:text-white leading-tight">
                    Operational Overview
                </h2>
                <p class="text-slate-500 font-medium mt-1 text-sm">
                    Live status · <span class="font-bold text-blue-600 dark:text-blue-400">Warehouse Sparepart</span>
                    · <span id="live-clock" class="font-mono text-xs bg-slate-100 px-2 py-0.5 rounded-lg"></span>
                </p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('stock-in') }}"
                   class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-700 font-bold text-sm hover:bg-slate-50 transition-colors shadow-sm">
                    <span class="material-symbols-outlined text-base">input</span> Stock In
                </a>
                <a href="{{ route('scan') }}"
                   class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-700 text-white font-bold text-sm hover:bg-blue-800 transition-colors shadow-lg shadow-blue-600/20">
                    <span class="material-symbols-outlined text-base">barcode_scanner</span> Scan Out
                </a>
            </div>
        </div>

        {{-- ── KPI Cards ──────────────────────────────────────────────── --}}
        <div class="grid gap-3 mb-6" style="grid-template-columns: repeat(2, 1fr);" id="kpi-grid">

            {{-- Total Items --}}
            <div class="bg-white dark:bg-slate-900 rounded-xl py-3 px-4 border-l-4 border-blue-600 shadow-sm hover:shadow-md transition-all duration-200 flex items-center gap-4">
                <div class="p-2 bg-blue-50 dark:bg-blue-900/30 rounded-xl flex-shrink-0">
                    <span class="material-symbols-outlined text-blue-600 dark:text-blue-400">inventory_2</span>
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest truncate">Total Item</p>
                    <p class="text-2xl font-black tracking-tighter text-slate-900 dark:text-white leading-tight">{{ number_format($totalItems) }}</p>
                    <p class="text-[10px] font-semibold text-blue-600 mt-0.5">Varian SKU</p>
                </div>
            </div>

            {{-- Today's Transactions --}}
            <div class="bg-white dark:bg-slate-900 rounded-xl py-3 px-4 border-l-4 border-emerald-500 shadow-sm hover:shadow-md transition-all duration-200 flex items-center gap-4">
                <div class="p-2 bg-emerald-50 dark:bg-emerald-900/30 rounded-xl flex-shrink-0">
                    <span class="material-symbols-outlined text-emerald-600 dark:text-emerald-400">trending_up</span>
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest truncate">Transaksi Hari Ini</p>
                    <p class="text-2xl font-black tracking-tighter text-slate-900 dark:text-white leading-tight">{{ number_format($todayTx) }}</p>
                    <p class="text-[10px] font-semibold text-slate-400 mt-0.5">
                        In: <span class="text-emerald-600 font-bold">+{{ number_format($todayStockIn) }}</span>
                        &nbsp;Out: <span class="text-blue-600 font-bold">{{ number_format($todayStockOut) }}</span>
                    </p>
                </div>
            </div>

            {{-- Low Stock --}}
            <div class="bg-white dark:bg-slate-900 rounded-xl py-3 px-4 border-l-4 border-amber-500 shadow-sm hover:shadow-md transition-all duration-200 flex items-center gap-4">
                <div class="p-2 bg-amber-50 dark:bg-amber-900/30 rounded-xl flex-shrink-0">
                    <span class="material-symbols-outlined text-amber-600 dark:text-amber-400">priority_high</span>
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest truncate">Stok Rendah</p>
                    <p class="text-2xl font-black tracking-tighter text-slate-900 dark:text-white leading-tight">{{ number_format($lowStockCount) }}</p>
                    @if($lowStockCount > 0)
                        <p class="text-[10px] font-bold text-amber-600 mt-0.5">Perlu Tindakan</p>
                    @else
                        <p class="text-[10px] font-bold text-emerald-600 mt-0.5">Semua Aman</p>
                    @endif
                </div>
            </div>

            {{-- Out of Stock --}}
            <div class="bg-white dark:bg-slate-900 rounded-xl py-3 px-4 border-l-4 border-red-500 shadow-sm hover:shadow-md transition-all duration-200 flex items-center gap-4">
                <div class="p-2 bg-red-50 dark:bg-red-900/30 rounded-xl flex-shrink-0">
                    <span class="material-symbols-outlined text-red-600 dark:text-red-400">block</span>
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest truncate">Stok Habis</p>
                    <p class="text-2xl font-black tracking-tighter text-slate-900 dark:text-white leading-tight">{{ number_format($outOfStockCount) }}</p>
                    @if($outOfStockCount > 0)
                        <p class="text-[10px] font-bold text-red-600 mt-0.5">Kritis</p>
                    @else
                        <p class="text-[10px] font-bold text-emerald-600 mt-0.5">Aman</p>
                    @endif
                </div>
            </div>

        </div>

        {{-- ── Main Grid: Chart + Alerts ──────────────────────────────── --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">

            {{-- LEFT: Trend Chart (8 col) --}}
            <div class="lg:col-span-8 space-y-6">

                {{-- Movement Trend Chart --}}
                <section class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Tren Pergerakan Stok</h3>
                            <p class="text-xs text-slate-400 mt-0.5">Stock In vs Stock Out per hari</p>
                        </div>
                        <div class="flex gap-1 bg-slate-100 dark:bg-slate-800 rounded-xl p-1">
                            <button onclick="setChartRange('7d')"
                                    id="btn-7d"
                                    class="chart-range-btn px-3 py-1.5 rounded-lg text-xs font-bold bg-white dark:bg-slate-700 shadow-sm text-blue-600 transition-all">7D</button>
                            <button onclick="setChartRange('30d')"
                                    id="btn-30d"
                                    class="chart-range-btn px-3 py-1.5 rounded-lg text-xs font-bold text-slate-500 hover:text-slate-700 transition-all">30D</button>
                        </div>
                    </div>

                    {{-- Chart Legend --}}
                    <div class="flex items-center gap-6 mb-4">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-emerald-500"></div>
                            <span class="text-xs font-medium text-slate-500">Stock In</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                            <span class="text-xs font-medium text-slate-500">Stock Out</span>
                        </div>
                    </div>

                    <div class="relative" style="height: 240px;">
                        <canvas id="movementChart"></canvas>
                    </div>
                </section>

                {{-- Recent Transactions Table --}}
                <section class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm overflow-hidden">
                    <div class="px-6 py-5 flex justify-between items-center border-b border-slate-100 dark:border-slate-800">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Transaksi Terbaru</h3>
                        <a href="{{ route('scan') }}" class="text-sm font-bold text-blue-600 hover:underline">Lihat Semua →</a>
                    </div>
                    <div class="overflow-x-auto">
                        @if($recentTransactions->isEmpty())
                            <div class="px-6 py-12 text-center">
                                <span class="material-symbols-outlined text-4xl text-slate-300 block mb-3">receipt_long</span>
                                <p class="text-sm text-slate-400 font-medium">Belum ada transaksi tercatat.</p>
                            </div>
                        @else
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-slate-800/50">
                                        <th class="px-6 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Kode</th>
                                        <th class="px-4 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Tipe</th>
                                        <th class="px-4 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Departemen</th>
                                        <th class="px-4 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">PIC</th>
                                        <th class="px-4 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                                        <th class="px-6 py-3 text-right text-[10px] font-black text-slate-400 uppercase tracking-widest">Waktu</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50 dark:divide-slate-800">
                                    @foreach($recentTransactions as $tx)
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                        <td class="px-6 py-4">
                                            <span class="font-bold text-slate-800 dark:text-slate-200 font-mono text-xs">{{ $tx->code }}</span>
                                        </td>
                                        <td class="px-4 py-4">
                                            @if($tx->type === 'OUT')
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 text-[10px] font-black rounded-lg uppercase tracking-wider">
                                                    <span class="material-symbols-outlined text-xs leading-none">output</span> OUT
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-[10px] font-black rounded-lg uppercase tracking-wider">
                                                    <span class="material-symbols-outlined text-xs leading-none">input</span> IN
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-slate-600 dark:text-slate-400 font-medium">
                                            {{ $tx->department?->name ?? '-' }}
                                        </td>
                                        <td class="px-4 py-4 text-slate-600 dark:text-slate-400 font-medium">
                                            {{ $tx->user?->name ?? '-' }}
                                        </td>
                                        <td class="px-4 py-4">
                                            @php
                                                $statusMap = [
                                                    'CONFIRMED' => ['bg-emerald-100 text-emerald-700', 'Confirmed'],
                                                    'DRAFT'     => ['bg-slate-100 text-slate-600', 'Draft'],
                                                    'CANCELLED' => ['bg-red-100 text-red-700', 'Cancelled'],
                                                ];
                                                [$cls, $label] = $statusMap[$tx->status] ?? ['bg-slate-100 text-slate-600', $tx->status];
                                            @endphp
                                            <span class="px-2.5 py-1 text-[10px] font-black rounded-lg uppercase tracking-wider {{ $cls }}">
                                                {{ $label }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right text-xs text-slate-400 font-medium">
                                            {{ $tx->created_at->diffForHumans() }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </section>

            </div>

            {{-- RIGHT: Alerts + Donut (4 col) --}}
            <div class="lg:col-span-4 space-y-6">

                {{-- Critical Alerts Panel --}}
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white mb-4 flex items-center justify-between">
                        <span class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-red-500 text-lg">warning</span>
                            Stock Alert
                        </span>
                        @php $totalAlerts = $criticalAlerts->count() + $lowStockAlerts->count(); @endphp
                        @if($totalAlerts > 0)
                            <span class="text-[10px] font-black bg-red-600 text-white px-2 py-1 rounded-lg">
                                {{ $totalAlerts }} ITEM
                            </span>
                        @endif
                    </h3>

                    <div class="space-y-3">
                        {{-- Out of Stock --}}
                        @forelse($criticalAlerts as $bin)
                            <div class="bg-red-50 dark:bg-red-900/20 p-4 rounded-xl border-l-4 border-red-500">
                                <p class="text-[10px] font-black text-red-600 uppercase tracking-widest mb-1">⛔ Habis</p>
                                <p class="font-bold text-slate-800 dark:text-slate-200 text-sm leading-tight">
                                    {{ $bin->itemVariant?->item?->name ?? 'Unknown Item' }}
                                </p>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    SKU: {{ $bin->itemVariant?->sku ?? '-' }}
                                    &nbsp;·&nbsp; Bin: {{ $bin->code }}
                                </p>
                            </div>
                        @empty
                        @endforelse

                        {{-- Low Stock --}}
                        @forelse($lowStockAlerts as $bin)
                            <div class="bg-amber-50 dark:bg-amber-900/20 p-4 rounded-xl border-l-4 border-amber-500">
                                <p class="text-[10px] font-black text-amber-600 uppercase tracking-widest mb-1">⚠ Stok Rendah</p>
                                <p class="font-bold text-slate-800 dark:text-slate-200 text-sm leading-tight">
                                    {{ $bin->itemVariant?->item?->name ?? 'Unknown Item' }}
                                </p>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    Sisa: <b class="text-amber-600">{{ $bin->current_qty }}</b>
                                    / Min: {{ $bin->min_qty }}
                                    &nbsp;·&nbsp; Bin: {{ $bin->code }}
                                </p>
                            </div>
                        @empty
                        @endforelse

                        @if($criticalAlerts->isEmpty() && $lowStockAlerts->isEmpty())
                            <div class="text-center py-6">
                                <span class="material-symbols-outlined text-4xl text-emerald-400 block mb-2" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                <p class="text-sm font-bold text-emerald-600">Semua stok aman!</p>
                                <p class="text-xs text-slate-400 mt-1">Tidak ada item yang perlu diperhatikan.</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Donut Chart - Stock by Brand --}}
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white mb-1">Distribusi Stok</h3>
                    <p class="text-xs text-slate-400 mb-4">Berdasarkan brand item</p>

                    @if(!empty($donutData) && array_sum($donutData) > 0)
                        <div class="relative" style="height: 200px;">
                            <canvas id="donutChart"></canvas>
                        </div>
                        <div class="mt-4 space-y-2" id="donut-legend"></div>
                    @else
                        <div class="text-center py-8">
                            <span class="material-symbols-outlined text-4xl text-slate-300 block mb-2">donut_large</span>
                            <p class="text-xs text-slate-400">Belum ada data stok.</p>
                        </div>
                    @endif
                </div>

                {{-- Warehouse Health Card --}}
                <div class="relative overflow-hidden rounded-2xl p-5 text-white group"
                     style="background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 100%);">
                    <div class="relative z-10">
                        <p class="text-xs font-bold text-blue-300 uppercase tracking-widest mb-2">Facility Health</p>
                        @php
                            $healthPct = $totalItems > 0
                                ? round((($totalItems - $outOfStockCount) / max($totalItems, 1)) * 100, 1)
                                : 100;
                        @endphp
                        <h4 class="text-2xl font-black mb-2">{{ $healthPct }}% Optimal</h4>
                        <p class="text-sm text-blue-200 leading-relaxed mb-4">
                            @if($outOfStockCount === 0 && $lowStockCount === 0)
                                Semua item dalam kondisi baik. Tidak ada bottleneck terdeteksi.
                            @else
                                {{ $outOfStockCount }} item habis & {{ $lowStockCount }} item stok rendah memerlukan perhatian.
                            @endif
                        </p>
                        <a href="{{ route('items') }}"
                           class="inline-block bg-white text-blue-700 px-4 py-2 rounded-lg text-xs font-black hover:bg-blue-50 active:scale-95 transition-transform">
                            Cek Inventori →
                        </a>
                    </div>
                    <div class="absolute -right-6 -bottom-6 opacity-10 group-hover:scale-110 transition-transform duration-500">
                        <span class="material-symbols-outlined text-[100px]" style="font-variation-settings: 'FILL' 1;">precision_manufacturing</span>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

{{-- ────────────────────────────────────────────────────────────────── --}}
{{-- JavaScript: Clock + Charts                                          --}}
{{-- ────────────────────────────────────────────────────────────────── --}}
<script>
(function() {
    // ── Pre-flight Check ───────────────────────────────────────────
    function initDashboard() {
        if (typeof Chart === 'undefined') {
            console.error('Chart.js library not loaded. Check path: {{ asset("assets/js/chart.umd.min.js") }}');
            return;
        }

        // Live Clock
        function updateClock() {
            var el = document.getElementById('live-clock');
            if (el) {
                var now = new Date();
                el.textContent = now.toLocaleTimeString('id-ID', {
                    hour: '2-digit', minute: '2-digit', second: '2-digit'
                });
            }
        }
        updateClock();
        setInterval(updateClock, 1000);

        // ── Chart Data from PHP ────────────────────────────────────────────
        var data7d = {
            labels: @json($chartLabels),
            stockIn: @json($chartStockIn),
            stockOut: @json($chartStockOut)
        };
        var data30d = {
            labels: @json($chartLabels30),
            stockIn: @json($chartIn30),
            stockOut: @json($chartOut30)
        };

        // ── Movement Trend Chart ───────────────────────────────────────────
        var canvasMovement = document.getElementById('movementChart');
        if (canvasMovement) {
            var ctxMovement = canvasMovement.getContext('2d');
            window.movementChart = new Chart(ctxMovement, {
                type: 'line',
                data: {
                    labels: data7d.labels,
                    datasets: [
                        {
                            label: 'Stock In',
                            data: data7d.stockIn,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.08)',
                            borderWidth: 2.5,
                            pointBackgroundColor: '#10b981',
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Stock Out',
                            data: data7d.stockOut,
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.06)',
                            borderWidth: 2.5,
                            pointBackgroundColor: '#3b82f6',
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            titleColor: '#94a3b8',
                            bodyColor: '#f8fafc',
                            padding: 12,
                            cornerRadius: 10,
                            callbacks: {
                                label: function(ctx) {
                                    return ' ' + ctx.dataset.label + ': ' + ctx.raw + ' unit';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                font: { size: 11, weight: '600' },
                                color: '#94a3b8'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(148, 163, 184, 0.1)' },
                            ticks: {
                                font: { size: 11 },
                                color: '#94a3b8',
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // ── Chart Range Switcher ───────────────────────────────────────────
        window.setChartRange = function(range) {
            var d = range === '7d' ? data7d : data30d;
            if (window.movementChart) {
                window.movementChart.data.labels = d.labels;
                window.movementChart.data.datasets[0].data = d.stockIn;
                window.movementChart.data.datasets[1].data = d.stockOut;
                window.movementChart.update();
            }

            var btns = document.querySelectorAll('.chart-range-btn');
            for (var i = 0; i < btns.length; i++) {
                btns[i].classList.remove('bg-white', 'dark:bg-slate-700', 'shadow-sm', 'text-blue-600');
                btns[i].classList.add('text-slate-500');
            }
            var active = document.getElementById('btn-' + range);
            if (active) {
                active.classList.add('bg-white', 'shadow-sm', 'text-blue-600');
                active.classList.remove('text-slate-500');
            }
        };

        // ── Donut Chart ────────────────────────────────────────────────────
        @if(!empty($donutData) && array_sum($donutData) > 0)
        var donutLabels = @json($donutLabels);
        var donutData   = @json($donutData);
        var donutColors = [
            '#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316','#84cc16'
        ];

        var canvasDonut = document.getElementById('donutChart');
        if (canvasDonut) {
            var ctxDonut = canvasDonut.getContext('2d');
            new Chart(ctxDonut, {
                type: 'doughnut',
                data: {
                    labels: donutLabels,
                    datasets: [{
                        data: donutData,
                        backgroundColor: donutColors.slice(0, donutLabels.length),
                        borderWidth: 0,
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            titleColor: '#94a3b8',
                            bodyColor: '#f8fafc',
                            padding: 12,
                            cornerRadius: 10,
                            callbacks: {
                                label: function(ctx) {
                                    return ' ' + ctx.label + ': ' + ctx.raw + ' unit';
                                }
                            }
                        }
                    }
                }
            });

            // Build custom legend
            var legendContainer = document.getElementById('donut-legend');
            if (legendContainer) {
                var totalQty = 0;
                for (var j = 0; j < donutData.length; j++) { totalQty += donutData[j]; }
                
                var html = '';
                for (var k = 0; k < donutLabels.length; k++) {
                    var pct = totalQty > 0 ? ((donutData[k] / totalQty) * 100).toFixed(1) : 0;
                    html += '<div class="flex items-center justify-between">' +
                            '<div class="flex items-center gap-2">' +
                            '<div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:' + donutColors[k] + '"></div>' +
                            '<span class="text-xs text-slate-600 dark:text-slate-400 font-medium truncate max-w-[120px]">' + donutLabels[k] + '</span>' +
                            '</div>' +
                            '<span class="text-xs font-bold text-slate-700 dark:text-slate-300">' + pct + '%</span>' +
                            '</div>';
                }
                legendContainer.innerHTML = html;
            }
        }
        @endif
    }

    // Ensure DOM is ready
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        initDashboard();
    } else {
        document.addEventListener('DOMContentLoaded', initDashboard);
    }
})();
</script>
@endsection
