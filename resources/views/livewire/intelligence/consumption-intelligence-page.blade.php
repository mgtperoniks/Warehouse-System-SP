<div class="pt-20 px-3.5 pb-6 lg:px-6 min-h-screen flex flex-col bg-slate-50/50 dark:bg-slate-950 text-slate-900 dark:text-white space-y-3">

    {{-- Notification Toast / Flash --}}
    @if (session()->has('success'))
        <div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 dark:bg-emerald-950/60 dark:border-emerald-800 dark:text-emerald-300 text-xs font-bold flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-emerald-600 dark:text-emerald-400 text-base">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-700/60 hover:text-emerald-800">
                <span class="material-symbols-outlined text-sm">close</span>
            </button>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 dark:bg-rose-950/60 dark:border-rose-800 dark:text-rose-300 text-xs font-bold flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-rose-600 dark:text-rose-400 text-base">error</span>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-rose-700/60 hover:text-rose-800">
                <span class="material-symbols-outlined text-sm">close</span>
            </button>
        </div>
    @endif

    {{-- Header Section: Compact Toolbar / Header --}}
    <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-3.5 sm:p-4 rounded-xl shadow-xs">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-slate-900 dark:bg-slate-800 text-emerald-400 rounded-lg flex items-center justify-center shadow-xs shrink-0">
                <span class="material-symbols-outlined text-xl">trending_up</span>
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-lg sm:text-xl font-black tracking-tight text-slate-900 dark:text-white">Consumption Intelligence</h1>
                    <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest bg-emerald-100 text-emerald-800 border border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800">
                        V1.1 Engine
                    </span>
                </div>
                <p class="text-[10px] sm:text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    Inventory demand movement & early warning • <span class="text-emerald-600 dark:text-emerald-400 font-black">What changed & why?</span>
                </p>
            </div>
        </div>

        {{-- Period Info & Refresh Action --}}
        <div class="flex flex-wrap items-center gap-2.5 self-stretch xl:self-auto justify-between xl:justify-end">
            @if ($latestPeriod)
                <div class="flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-2.5 py-1.5 rounded-lg text-[10px] sm:text-[11px] font-bold text-slate-700 dark:text-slate-300">
                    <span class="material-symbols-outlined text-xs text-emerald-600">date_range</span>
                    <span>Period: <strong>{{ \Carbon\Carbon::parse($latestPeriod->period_start)->format('d M Y') }}</strong> – <strong>{{ \Carbon\Carbon::parse($latestPeriod->period_end)->format('d M Y') }}</strong> (8 Valid Weeks)</span>
                </div>
            @endif

            <button 
                type="button"
                wire:click="recalculate"
                wire:loading.attr="disabled"
                class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 dark:bg-emerald-600 dark:hover:bg-emerald-500 active:scale-95 text-white text-xs font-black rounded-lg shadow-xs transition-all flex items-center gap-1.5 disabled:opacity-50 shrink-0">
                <span wire:loading.remove wire:target="recalculate" class="material-symbols-outlined text-sm">sync</span>
                <span wire:loading wire:target="recalculate" class="material-symbols-outlined text-sm animate-spin">refresh</span>
                <span>Recalculate Snapshot</span>
            </button>
        </div>
    </div>

    {{-- Executive Summary Cards (Compact 5 in 1 row on Wide Desktop) --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2.5">
        {{-- Card 1: Strong Rising --}}
        <button 
            type="button"
            wire:click="setTab('TOP_RISING'); setClassificationFilter('STRONG_RISING')"
            class="p-3 rounded-xl border text-left transition-all duration-150 shadow-xs {{ $filterClassification === 'STRONG_RISING' ? 'bg-rose-50 border-rose-500 ring-2 ring-rose-400/50 dark:bg-rose-950/60 dark:border-rose-500' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-rose-300 hover:bg-rose-50/30' }}">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-rose-700 dark:text-rose-400">Strong Rising</span>
                <span class="w-6 h-6 rounded bg-rose-100 text-rose-700 dark:bg-rose-900/60 dark:text-rose-300 flex items-center justify-center">
                    <span class="material-symbols-outlined text-sm">rocket_launch</span>
                </span>
            </div>
            <div class="mt-1 text-xl sm:text-2xl font-black font-mono text-slate-900 dark:text-white">{{ $kpis['strong_rising'] }}</div>
            <p class="text-[10px] text-rose-600 dark:text-rose-400 mt-0.5 font-bold">Priority: HIGH</p>
        </button>

        {{-- Card 2: Rising --}}
        <button 
            type="button"
            wire:click="setTab('TOP_RISING'); setClassificationFilter('RISING')"
            class="p-3 rounded-xl border text-left transition-all duration-150 shadow-xs {{ $filterClassification === 'RISING' ? 'bg-amber-50 border-amber-500 ring-2 ring-amber-400/50 dark:bg-amber-950/60 dark:border-amber-500' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-amber-300 hover:bg-amber-50/30' }}">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-amber-700 dark:text-amber-400">Rising Demand</span>
                <span class="w-6 h-6 rounded bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300 flex items-center justify-center">
                    <span class="material-symbols-outlined text-sm">trending_up</span>
                </span>
            </div>
            <div class="mt-1 text-xl sm:text-2xl font-black font-mono text-slate-900 dark:text-white">{{ $kpis['rising'] }}</div>
            <p class="text-[10px] text-amber-600 dark:text-amber-400 mt-0.5 font-bold">Priority: MEDIUM</p>
        </button>

        {{-- Card 3: Watchlist --}}
        <button 
            type="button"
            wire:click="setTab('TOP_RISING'); setClassificationFilter('WATCH')"
            class="p-3 rounded-xl border text-left transition-all duration-150 shadow-xs {{ $filterClassification === 'WATCH' ? 'bg-blue-50 border-blue-500 ring-2 ring-blue-400/50 dark:bg-blue-950/60 dark:border-blue-500' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-blue-300 hover:bg-blue-50/30' }}">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-blue-700 dark:text-blue-400">Watchlist</span>
                <span class="w-6 h-6 rounded bg-blue-100 text-blue-700 dark:bg-blue-900/60 dark:text-blue-300 flex items-center justify-center">
                    <span class="material-symbols-outlined text-sm">visibility</span>
                </span>
            </div>
            <div class="mt-1 text-xl sm:text-2xl font-black font-mono text-slate-900 dark:text-white">{{ $kpis['watch'] }}</div>
            <p class="text-[10px] text-blue-600 dark:text-blue-400 mt-0.5 font-bold">Emerging / Low-Vol</p>
        </button>

        {{-- Card 4: Volatile --}}
        <button 
            type="button"
            wire:click="setTab('TOP_VOLATILE'); setClassificationFilter('')"
            class="p-3 rounded-xl border text-left transition-all duration-150 shadow-xs {{ $rankingTab === 'TOP_VOLATILE' ? 'bg-purple-50 border-purple-500 ring-2 ring-purple-400/50 dark:bg-purple-950/60 dark:border-purple-500' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-purple-300 hover:bg-purple-50/30' }}">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-purple-700 dark:text-purple-400">High Volatility</span>
                <span class="w-6 h-6 rounded bg-purple-100 text-purple-700 dark:bg-purple-900/60 dark:text-purple-300 flex items-center justify-center">
                    <span class="material-symbols-outlined text-sm">waves</span>
                </span>
            </div>
            <div class="mt-1 text-xl sm:text-2xl font-black font-mono text-slate-900 dark:text-white">{{ $kpis['volatile'] }}</div>
            <p class="text-[10px] text-purple-600 dark:text-purple-400 mt-0.5 font-bold">Erratic Demand</p>
        </button>

        {{-- Card 5: Active Items / Catalogue Context --}}
        <button 
            type="button"
            wire:click="setTab('ALL_ACTIVE'); setClassificationFilter('')"
            class="p-3 rounded-xl border text-left transition-all duration-150 shadow-xs {{ $rankingTab === 'ALL_ACTIVE' ? 'bg-emerald-50 border-emerald-500 ring-2 ring-emerald-400/50 dark:bg-emerald-950/60 dark:border-emerald-500' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-emerald-300 hover:bg-emerald-50/30' }} col-span-2 md:col-span-1">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Active Moving</span>
                <span class="w-6 h-6 rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300 flex items-center justify-center">
                    <span class="material-symbols-outlined text-sm">check_circle</span>
                </span>
            </div>
            <div class="mt-1 text-xl sm:text-2xl font-black font-mono text-slate-900 dark:text-white">{{ $kpis['active_total'] }} <span class="text-xs font-normal text-slate-400 font-sans">/ {{ number_format($kpis['total_master']) }}</span></div>
            <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5 font-medium">{{ number_format($kpis['dormant']) }} Dormant (92%)</p>
        </button>
    </div>

    {{-- Trend Distribution Compact Bar (Context Overview) --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 shadow-xs space-y-2">
        <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
            <span class="font-black text-slate-700 dark:text-slate-300 uppercase tracking-wider text-[10px] sm:text-[11px] flex items-center gap-1.5">
                <span class="material-symbols-outlined text-sm text-blue-600">pie_chart</span>
                <span>Catalogue Activity & Trend Breakdown (6,921 SKUs)</span>
            </span>
            <span class="text-[10px] sm:text-[11px] text-slate-500">
                <strong class="text-emerald-700 dark:text-emerald-400">{{ $kpis['active_total'] }}</strong> Active (7.9%) • <strong class="text-slate-600 dark:text-slate-400">{{ number_format($kpis['dormant']) }}</strong> Dormant (92.1%)
            </span>
        </div>

        {{-- Multi-segment Progress Bar --}}
        <div class="w-full h-2.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden flex shadow-inner">
            <div title="Strong Rising: {{ $kpis['strong_rising'] }}" style="width: {{ max(1.5, ($kpis['strong_rising']/6921)*100*8) }}%" class="bg-rose-500 h-full"></div>
            <div title="Rising: {{ $kpis['rising'] }}" style="width: {{ max(1.2, ($kpis['rising']/6921)*100*8) }}%" class="bg-amber-500 h-full"></div>
            <div title="Watchlist: {{ $kpis['watch'] }}" style="width: {{ max(1.5, ($kpis['watch']/6921)*100*8) }}%" class="bg-blue-500 h-full"></div>
            <div title="Volatile: {{ $kpis['volatile'] }}" style="width: {{ max(1.2, ($kpis['volatile']/6921)*100*8) }}%" class="bg-purple-500 h-full"></div>
            <div title="Stable Active: {{ $kpis['stable'] }}" style="width: {{ max(3, ($kpis['stable']/6921)*100*8) }}%" class="bg-emerald-500 h-full"></div>
            <div title="Sporadic Single-Spike: {{ $kpis['sporadic'] }}" style="width: {{ max(4, ($kpis['sporadic']/6921)*100*8) }}%" class="bg-slate-400 h-full"></div>
            <div title="Dormant: {{ $kpis['dormant'] }}" class="bg-slate-200 dark:bg-slate-700 h-full flex-1"></div>
        </div>

        <div class="flex flex-wrap items-center gap-x-3.5 gap-y-1 text-[9px] sm:text-[10px] font-bold text-slate-500 dark:text-slate-400">
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-rose-500"></span> Strong Rising ({{ $kpis['strong_rising'] }})</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-500"></span> Rising ({{ $kpis['rising'] }})</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-500"></span> Watch ({{ $kpis['watch'] }})</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-purple-500"></span> Volatile ({{ $kpis['volatile'] }})</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Stable ({{ $kpis['stable'] }})</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-slate-400"></span> Sporadic ({{ $kpis['sporadic'] }})</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-slate-300 dark:bg-slate-600"></span> Dormant ({{ number_format($kpis['dormant']) }})</span>
        </div>
    </div>

    {{-- Top 10 Visual Horizontal Bar Chart Section (Compact Executive Overview) --}}
    @if ($topVisualItems->isNotEmpty() && in_array($rankingTab, ['TOP_RISING', 'TOP_ACCELERATING', 'TOP_VOLATILE', 'TOP_CONSUMPTION']))
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 sm:p-3.5 shadow-xs space-y-2.5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 border-b border-slate-100 dark:border-slate-800 pb-2">
                <div>
                    <h2 class="text-xs sm:text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-emerald-600 text-base">bar_chart</span>
                        <span>
                            @switch($rankingTab)
                                @case('TOP_ACCELERATING') Top Accelerating Growth Chart @break
                                @case('TOP_VOLATILE') Top Volatile Fluctuations Chart @break
                                @case('TOP_CONSUMPTION') Top Consumption Volume Chart @break
                                @default Top 10 Rising Items • Trend Strength Index
                            @endswitch
                        </span>
                    </h2>
                    <p class="text-[10px] sm:text-[11px] text-slate-500">
                        @switch($rankingTab)
                            @case('TOP_ACCELERATING') Ranked by recent 4-week growth rate % with volume evidence gate @break
                            @case('TOP_VOLATILE') Ranked by non-zero coefficient of variation (erratic demand) @break
                            @case('TOP_CONSUMPTION') Ranked by total 8-week physical consumption units @break
                            @default Items with the strongest sustained positive consumption signal
                        @endswitch
                    </p>
                </div>
                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 self-start sm:self-center">
                    Top 10 Executive View
                </span>
            </div>

            <div class="space-y-1.5">
                @foreach ($topVisualItems as $idx => $tItem)
                    @php
                        $tVar = $tItem->variant;
                        $tMetricVal = match($rankingTab) {
                            'TOP_ACCELERATING' => $tItem->recent_growth_pct,
                            'TOP_VOLATILE'     => $tItem->volatility_cv * 50,
                            'TOP_CONSUMPTION'  => min(100, ($tItem->total_consumption_qty / 1500) * 100),
                            default            => $tItem->trend_index
                        };
                        $barWidth = max(6, min(100, $tMetricVal));
                        $barColor = match($rankingTab) {
                            'TOP_ACCELERATING' => 'bg-amber-500',
                            'TOP_VOLATILE'     => 'bg-purple-500',
                            'TOP_CONSUMPTION'  => 'bg-blue-500',
                            default            => ($tItem->trend_index >= 75 ? 'bg-rose-500' : ($tItem->trend_index >= 60 ? 'bg-amber-500' : 'bg-emerald-500'))
                        };
                    @endphp
                    <div class="flex items-center gap-2 text-xs group cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60 p-1 rounded-lg transition-colors" wire:click="openDetail({{ $tItem->item_variant_id }})">
                        <span class="w-5 text-center font-black text-slate-400 font-mono text-[10px]">#{{ $idx + 1 }}</span>
                        <div class="w-44 sm:w-56 truncate font-bold text-slate-800 dark:text-slate-200 text-[11px]" title="{{ $tVar?->item?->name ?? 'Unknown / Unmapped Item' }}">
                            {{ $tVar?->item?->name ?? 'Unknown / Unmapped Item' }}
                            <span class="text-[9px] font-mono text-slate-400 block truncate">{{ $tVar?->erp_code ?? '—' }}</span>
                        </div>
                        <div class="flex-1 h-2.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden flex items-center">
                            <div class="h-full rounded-full {{ $barColor }} transition-all duration-300" style="width: {{ $barWidth }}%"></div>
                        </div>
                        <div class="w-20 sm:w-24 text-right font-mono font-bold text-[11px] shrink-0">
                            @if ($rankingTab === 'TOP_ACCELERATING')
                                <span class="text-rose-600 dark:text-rose-400 font-black">+{{ number_format($tItem->recent_growth_pct, 1) }}%</span>
                            @elseif ($rankingTab === 'TOP_VOLATILE')
                                <span class="text-purple-600 dark:text-purple-400 font-black">CV {{ number_format($tItem->volatility_cv, 2) }}</span>
                            @elseif ($rankingTab === 'TOP_CONSUMPTION')
                                <span class="text-blue-600 dark:text-blue-400 font-black">{{ number_format($tItem->total_consumption_qty) }} {{ $tVar?->unit ?? '' }}</span>
                            @else
                                <span class="text-slate-900 dark:text-white font-black">{{ number_format($tItem->trend_index, 1) }}</span>
                                <span class="text-[9px] text-slate-400 font-normal">/100</span>
                            @endif
                        </div>
                        <button type="button" class="text-emerald-700 dark:text-emerald-400 hover:underline text-[10px] font-black shrink-0 hidden sm:inline px-1.5 py-0.5">
                            Why →
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Main Container: Ranking Tabs, Filters, and Interactive Table --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-xs overflow-hidden">
        
        {{-- Navigation Tabs --}}
        <div class="flex flex-wrap items-center justify-between border-b border-slate-200 dark:border-slate-800 px-3 pt-2.5 pb-2 gap-2 bg-slate-50/80 dark:bg-slate-900/80">
            <div class="flex flex-wrap items-center gap-1.5">
                <button 
                    type="button" 
                    wire:click="setTab('TOP_RISING')" 
                    class="px-3 py-1.5 text-xs font-black rounded-lg transition-all flex items-center gap-1.5 {{ $rankingTab === 'TOP_RISING' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-200/60 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700' }}">
                    <span class="material-symbols-outlined text-sm">trending_up</span>
                    <span>Top Rising Items</span>
                </button>
                <button 
                    type="button" 
                    wire:click="setTab('TOP_ACCELERATING')" 
                    class="px-3 py-1.5 text-xs font-black rounded-lg transition-all flex items-center gap-1.5 {{ $rankingTab === 'TOP_ACCELERATING' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-200/60 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700' }}">
                    <span class="material-symbols-outlined text-sm">bolt</span>
                    <span>Top Accelerating</span>
                </button>
                <button 
                    type="button" 
                    wire:click="setTab('TOP_VOLATILE')" 
                    class="px-3 py-1.5 text-xs font-black rounded-lg transition-all flex items-center gap-1.5 {{ $rankingTab === 'TOP_VOLATILE' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-200/60 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700' }}">
                    <span class="material-symbols-outlined text-sm">waves</span>
                    <span>Top Volatile</span>
                </button>
                <button 
                    type="button" 
                    wire:click="setTab('TOP_CONSUMPTION')" 
                    class="px-3 py-1.5 text-xs font-black rounded-lg transition-all flex items-center gap-1.5 {{ $rankingTab === 'TOP_CONSUMPTION' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-200/60 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700' }}">
                    <span class="material-symbols-outlined text-sm">inventory</span>
                    <span>Top Volume</span>
                </button>
                <button 
                    type="button" 
                    wire:click="setTab('ALL_ACTIVE')" 
                    class="px-3 py-1.5 text-xs font-black rounded-lg transition-all flex items-center gap-1.5 {{ $rankingTab === 'ALL_ACTIVE' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-200/60 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700' }}">
                    <span class="material-symbols-outlined text-sm">list_alt</span>
                    <span>All Active SKUs</span>
                </button>
            </div>

            {{-- Page Size / Top 10 / 20 Limiter --}}
            <div class="flex items-center gap-1.5 text-xs">
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Show:</span>
                <button 
                    type="button" 
                    wire:click="$set('perPage', 10)" 
                    class="px-2.5 py-1 rounded-md text-[11px] font-black transition-all {{ (int)$perPage === 10 ? 'bg-slate-900 text-white shadow-xs dark:bg-emerald-600' : 'bg-slate-200/80 text-slate-700 hover:bg-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700' }}">
                    Top 10
                </button>
                <button 
                    type="button" 
                    wire:click="$set('perPage', 20)" 
                    class="px-2.5 py-1 rounded-md text-[11px] font-black transition-all {{ (int)$perPage === 20 ? 'bg-slate-900 text-white shadow-xs dark:bg-emerald-600' : 'bg-slate-200/80 text-slate-700 hover:bg-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700' }}">
                    Top 20
                </button>
                <button 
                    type="button" 
                    wire:click="$set('perPage', 50)" 
                    class="px-2.5 py-1 rounded-md text-[11px] font-black transition-all {{ (int)$perPage === 50 ? 'bg-slate-900 text-white shadow-xs dark:bg-emerald-600' : 'bg-slate-200/80 text-slate-700 hover:bg-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700' }}">
                    50
                </button>
            </div>
        </div>

        {{-- Filter Bar --}}
        <div class="p-2.5 border-b border-slate-200 dark:border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-2.5 bg-white dark:bg-slate-900">
            <div class="flex-1 flex flex-col sm:flex-row items-center gap-2.5">
                {{-- Search Box --}}
                <div class="relative w-full sm:w-64">
                    <span class="material-symbols-outlined absolute left-2.5 top-2 text-slate-400 text-sm">search</span>
                    <input 
                        type="text" 
                        wire:model.live.debounce.300ms="search" 
                        placeholder="Search SKU, ERP code, item name..."
                        class="w-full pl-8 pr-2.5 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all placeholder:text-slate-400"
                    />
                </div>

                {{-- Classification Filter --}}
                <select 
                    wire:model.live="filterClassification"
                    class="w-full sm:w-44 py-1.5 px-2.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all font-semibold">
                    <option value="">All Classifications</option>
                    <option value="STRONG_RISING">Strong Rising</option>
                    <option value="RISING">Rising Demand</option>
                    <option value="WATCH">Watchlist</option>
                    <option value="HIGH_VOLATILITY">High Volatility</option>
                    <option value="STABLE">Stable</option>
                    <option value="SPORADIC_SPIKE">Sporadic Spike</option>
                </select>

                {{-- Department Driver Filter --}}
                <select 
                    wire:model.live="filterDepartment"
                    class="w-full sm:w-52 py-1.5 px-2.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all font-semibold">
                    <option value="">All Primary Dept Drivers</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept }}">{{ $dept }}</option>
                    @endforeach
                </select>
            </div>

            <div class="text-[10px] sm:text-[11px] text-slate-500 dark:text-slate-400 font-bold shrink-0">
                Showing <strong class="text-slate-800 dark:text-slate-200">{{ $snapshots->total() }}</strong> matching items
            </div>
        </div>

        {{-- Top Rising Table Section (Compact Row Spacing & High Visual Contrast) --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                <thead class="bg-slate-50 dark:bg-slate-800/80 text-[9px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="py-2.5 px-3 w-10 text-center">Rank</th>
                        <th class="py-2.5 px-3 min-w-[200px]">Item</th>
                        <th class="py-2.5 px-2.5 text-center">Meeting Priority</th>
                        <th class="py-2.5 px-2.5 text-center">Status</th>
                        <th class="py-2.5 px-3 text-center">Trend Index</th>
                        <th class="py-2.5 px-3 text-right">Recent vs Prev (4W)</th>
                        <th class="py-2.5 px-3 text-center">Change</th>
                        <th class="py-2.5 px-2 text-center">Active Wks</th>
                        <th class="py-2.5 px-2 text-center">Trx</th>
                        <th class="py-2.5 px-3 min-w-[140px]">Primary Driver</th>
                        <th class="py-2.5 px-3 text-center">Why</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($snapshots as $index => $item)
                        @php
                            $rankNum = ($snapshots->currentPage() - 1) * $snapshots->perPage() + $index + 1;
                            $var = $item->variant;
                        @endphp
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors">
                            {{-- Rank --}}
                            <td class="py-2 px-3 text-center font-black text-slate-400">
                                @if ($rankNum === 1)
                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-500 text-white font-black text-[11px] shadow-xs">1</span>
                                @elseif ($rankNum === 2)
                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-400 text-white font-black text-[11px]">2</span>
                                @elseif ($rankNum === 3)
                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-700 text-white font-black text-[11px]">3</span>
                                @else
                                    <span class="font-mono text-xs">{{ $rankNum }}</span>
                                @endif
                            </td>

                            {{-- Item Name & SKU --}}
                            <td class="py-2 px-3">
                                <div class="font-black text-slate-900 dark:text-white leading-tight text-xs">
                                    {{ $var?->item?->name ?? 'Unknown / Unmapped Item' }}
                                </div>
                                <div class="flex items-center gap-1 mt-0.5 text-[9px] sm:text-[10px] text-slate-500 font-medium">
                                    <span class="font-mono font-bold text-emerald-700 dark:text-emerald-400">{{ $var?->erp_code ?? '—' }}</span>
                                    @if ($var?->brand)
                                        <span>• {{ $var->brand }}</span>
                                    @endif
                                    @if ($var?->unit)
                                        <span>• {{ $var->unit }}</span>
                                    @endif
                                </div>
                            </td>

                            {{-- Meeting Priority Badge (Explicit Semantic Backgrounds) --}}
                            <td class="py-2 px-2.5 text-center">
                                @if ($item->classification === 'STRONG_RISING')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-rose-600 text-white shadow-xs">
                                        HIGH
                                    </span>
                                @elseif ($item->classification === 'RISING')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-500 text-white shadow-xs">
                                        MEDIUM
                                    </span>
                                @elseif ($item->classification === 'WATCH')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-blue-600 text-white shadow-xs">
                                        MONITOR
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700">
                                        ROUTINE
                                    </span>
                                @endif
                            </td>

                            {{-- Classification Status Badge (Explicit Static Styles) --}}
                            <td class="py-2 px-2.5 text-center">
                                @switch($item->classification)
                                    @case('STRONG_RISING')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[9px] font-black bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800">
                                            <span class="material-symbols-outlined text-[11px]">rocket_launch</span>
                                            STRONG RISING
                                        </span>
                                        @break
                                    @case('RISING')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[9px] font-black bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800">
                                            <span class="material-symbols-outlined text-[11px]">trending_up</span>
                                            RISING
                                        </span>
                                        @break
                                    @case('WATCH')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[9px] font-black bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800">
                                            <span class="material-symbols-outlined text-[11px]">visibility</span>
                                            WATCH
                                        </span>
                                        @break
                                    @case('HIGH_VOLATILITY')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[9px] font-black bg-purple-50 text-purple-700 border border-purple-200 dark:bg-purple-950/60 dark:text-purple-300 dark:border-purple-800">
                                            <span class="material-symbols-outlined text-[11px]">waves</span>
                                            VOLATILE
                                        </span>
                                        @break
                                    @case('SPORADIC_SPIKE')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[9px] font-black bg-slate-100 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700">
                                            <span class="material-symbols-outlined text-[11px]">bolt</span>
                                            SPORADIC
                                        </span>
                                        @break
                                    @default
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[9px] font-black bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800">
                                            <span class="material-symbols-outlined text-[11px]">check_circle</span>
                                            STABLE
                                        </span>
                                @endswitch
                            </td>

                            {{-- Trend Index --}}
                            <td class="py-2 px-3 text-center">
                                <div class="inline-flex flex-col items-center">
                                    <span class="text-xs font-black font-mono {{ $item->trend_index >= 75 ? 'text-rose-600 dark:text-rose-400' : ($item->trend_index >= 60 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-800 dark:text-slate-200') }}">
                                        {{ number_format($item->trend_index, 1) }}
                                    </span>
                                    {{-- Mini Progress Bar --}}
                                    <div class="w-12 h-1 bg-slate-200 dark:bg-slate-700 rounded-full mt-0.5 overflow-hidden">
                                        <div class="h-full rounded-full {{ $item->trend_index >= 75 ? 'bg-rose-500' : ($item->trend_index >= 60 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ $item->trend_index }}%"></div>
                                    </div>
                                </div>
                            </td>

                            {{-- Recent vs Prev Qty (4W) --}}
                            <td class="py-2 px-3 text-right">
                                <div class="font-mono font-bold text-slate-900 dark:text-white text-xs">
                                    <span class="text-emerald-700 dark:text-emerald-400">{{ $item->recent_period_qty }}</span>
                                    <span class="text-slate-400 text-[10px]">vs</span>
                                    <span class="text-slate-500">{{ $item->prior_period_qty }}</span>
                                </div>
                                <div class="text-[9px] text-slate-400 font-medium">
                                    Total: {{ number_format($item->total_consumption_qty) }} {{ $var?->unit ?? '' }}
                                </div>
                            </td>

                            {{-- Change % --}}
                            <td class="py-2 px-3 text-center font-mono font-bold">
                                @if ($item->recent_growth_pct > 0)
                                    <span class="text-rose-600 dark:text-rose-400 flex items-center justify-center gap-0.5 font-black text-[11px]">
                                        <span class="material-symbols-outlined text-[11px]">arrow_upward</span>
                                        +{{ number_format($item->recent_growth_pct, 1) }}%
                                    </span>
                                @elseif ($item->recent_growth_pct < 0)
                                    <span class="text-blue-600 dark:text-blue-400 flex items-center justify-center gap-0.5 font-bold text-[11px]">
                                        <span class="material-symbols-outlined text-[11px]">arrow_downward</span>
                                        {{ number_format($item->recent_growth_pct, 1) }}%
                                    </span>
                                @else
                                    <span class="text-slate-400 text-[11px]">0.0%</span>
                                @endif
                            </td>

                            {{-- Active Weeks --}}
                            <td class="py-2 px-2 text-center font-bold">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-mono {{ $item->active_weeks_count >= 6 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300' : ($item->active_weeks_count >= 3 ? 'bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/60 dark:text-amber-300' : 'bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-800 dark:text-slate-400') }}">
                                    {{ $item->active_weeks_count }}/{{ $item->valid_weeks_count }}
                                </span>
                            </td>

                            {{-- Transactions Count --}}
                            <td class="py-2 px-2 text-center font-bold text-slate-700 dark:text-slate-300 font-mono text-xs">
                                {{ $item->total_transactions }}
                            </td>

                            {{-- Primary Department Driver --}}
                            <td class="py-2 px-3">
                                <div class="truncate max-w-[180px] text-slate-800 dark:text-slate-200 font-bold text-xs" title="{{ $item->primary_department_driver }}">
                                    {{ $item->primary_department_driver ?? 'Unassigned' }}
                                </div>
                                @if ($item->top_requester_driver)
                                    <div class="text-[9px] text-slate-400 truncate max-w-[180px]">
                                        By: {{ $item->top_requester_driver }}
                                    </div>
                                @endif
                            </td>

                            {{-- Action Button Why --}}
                            <td class="py-2 px-3 text-center">
                                <button 
                                    type="button" 
                                    wire:click="openDetail({{ $item->item_variant_id }})"
                                    class="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-emerald-600 hover:text-white dark:hover:bg-emerald-600 text-slate-700 dark:text-slate-300 font-black rounded-md text-[11px] transition-all flex items-center gap-0.5 mx-auto shadow-xs active:scale-95">
                                    <span>Why</span>
                                    <span class="material-symbols-outlined text-xs">arrow_forward</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="py-8 text-center text-slate-400">
                                <span class="material-symbols-outlined text-3xl block mb-1 text-slate-300">search_off</span>
                                <p class="text-xs font-bold">No consumption intelligence records found.</p>
                                <p class="text-[10px] text-slate-400 mt-0.5">Try resetting your filter or search query.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($snapshots->hasPages())
            <div class="p-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
                {{ $snapshots->links() }}
            </div>
        @endif
    </div>

    {{-- =========================================================================
         DRILL-DOWN MODAL: "WHY IS THIS SKU RISING?"
         ========================================================================= --}}
    @if ($showDetailModal && $selectedSnapshot)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 animate-fade-in">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl max-w-4xl w-full max-h-[92vh] overflow-y-auto shadow-2xl p-4 sm:p-5 space-y-4 relative text-slate-900 dark:text-white">
                
                {{-- Close Button --}}
                <button 
                    type="button" 
                    wire:click="closeDetail"
                    class="absolute top-4 right-4 p-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-slate-900 dark:hover:text-white transition-all">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>

                {{-- Header / SKU Title --}}
                <div class="space-y-1 pr-8 border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="px-2 py-0.5 rounded text-[9px] font-black bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 font-mono">
                            {{ $selectedSnapshot->variant?->erp_code ?? '—' }}
                        </span>
                        @if ($selectedSnapshot->variant?->brand)
                            <span class="text-[11px] text-slate-500">• {{ $selectedSnapshot->variant->brand }}</span>
                        @endif
                        @if ($selectedSnapshot->variant?->unit)
                            <span class="text-[11px] text-slate-500">• Unit: {{ $selectedSnapshot->variant->unit }}</span>
                        @endif

                        {{-- Meeting Priority Pill --}}
                        @if ($selectedSnapshot->classification === 'STRONG_RISING')
                            <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-rose-600 text-white">
                                MEETING PRIORITY: HIGH
                            </span>
                        @elseif ($selectedSnapshot->classification === 'RISING')
                            <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-amber-500 text-white">
                                MEETING PRIORITY: MEDIUM
                            </span>
                        @elseif ($selectedSnapshot->classification === 'WATCH')
                            <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-blue-600 text-white">
                                MEETING PRIORITY: MONITOR
                            </span>
                        @endif
                    </div>
                    <h2 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white">
                        {{ $selectedSnapshot->variant?->item?->name ?? 'Unknown / Unmapped Item' }}
                    </h2>
                    <p class="text-[11px] text-slate-500">
                        Detailed 8-week consumption audit and deterministic attribution decomposition.
                    </p>
                </div>

                {{-- Primary Metrics Grid --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
                        <div class="text-[9px] font-black text-slate-400 uppercase">Trend Index</div>
                        <div class="text-base font-black text-slate-900 dark:text-white mt-0.5 font-mono">
                            {{ number_format($selectedSnapshot->trend_index, 1) }}
                        </div>
                        <div class="text-[9px] text-slate-500">Signal: {{ number_format($selectedSnapshot->trend_signal, 1) }}</div>
                    </div>

                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
                        <div class="text-[9px] font-black text-slate-400 uppercase">Recent Growth</div>
                        <div class="text-base font-black font-mono {{ $selectedSnapshot->recent_growth_pct >= 0 ? 'text-rose-600 dark:text-rose-400' : 'text-blue-600 dark:text-blue-400' }} mt-0.5">
                            {{ $selectedSnapshot->recent_growth_pct >= 0 ? '+' : '' }}{{ number_format($selectedSnapshot->recent_growth_pct, 1) }}%
                        </div>
                        <div class="text-[9px] text-slate-500">{{ $selectedSnapshot->prior_period_qty }} &rarr; {{ $selectedSnapshot->recent_period_qty }}</div>
                    </div>

                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
                        <div class="text-[9px] font-black text-slate-400 uppercase">Total Qty (8W)</div>
                        <div class="text-base font-black text-slate-900 dark:text-white mt-0.5 font-mono">
                            {{ number_format($selectedSnapshot->total_consumption_qty) }}
                        </div>
                        <div class="text-[9px] text-slate-500">{{ $selectedSnapshot->variant->unit }}</div>
                    </div>

                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
                        <div class="text-[9px] font-black text-slate-400 uppercase">Active Weeks</div>
                        <div class="text-base font-black text-slate-900 dark:text-white mt-0.5 font-mono">
                            {{ $selectedSnapshot->active_weeks_count }} / {{ $selectedSnapshot->valid_weeks_count }}
                        </div>
                        <div class="text-[9px] text-slate-500">{{ round(($selectedSnapshot->active_weeks_count/$selectedSnapshot->valid_weeks_count)*100) }}% presence</div>
                    </div>

                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
                        <div class="text-[9px] font-black text-slate-400 uppercase">Transactions</div>
                        <div class="text-base font-black text-slate-900 dark:text-white mt-0.5 font-mono">
                            {{ $selectedSnapshot->total_transactions }}
                        </div>
                        <div class="text-[9px] text-slate-500">Requisitions</div>
                    </div>

                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
                        <div class="text-[9px] font-black text-slate-400 uppercase">Stability</div>
                        <div class="text-base font-black text-slate-900 dark:text-white mt-0.5 font-mono">
                            {{ number_format($selectedSnapshot->stability_score * 100, 1) }}%
                        </div>
                        <div class="text-[9px] text-slate-500">CV: {{ $selectedSnapshot->volatility_cv }}</div>
                    </div>
                </div>

                {{-- Meeting Talking Points Summary --}}
                <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-1.5">
                    <div class="flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wider text-slate-800 dark:text-slate-200">
                        <span class="material-symbols-outlined text-emerald-600 text-sm">forum</span>
                        <span>Management Meeting Discussion Summary</span>
                    </div>
                    <ul class="text-xs space-y-1 text-slate-700 dark:text-slate-300 list-disc list-inside">
                        <li>
                            <strong>Trend Index {{ number_format($selectedSnapshot->trend_index, 1) }} ({{ $selectedSnapshot->classification }}):</strong> 
                            Consumption shifted from <strong>{{ $selectedSnapshot->prior_period_qty }} units</strong> (prior 4 weeks) to <strong class="text-rose-600 dark:text-rose-400">{{ $selectedSnapshot->recent_period_qty }} units</strong> (recent 4 weeks), representing a growth of <strong class="text-rose-600 dark:text-rose-400">+{{ number_format($selectedSnapshot->recent_growth_pct, 1) }}%</strong>.
                        </li>
                        <li>
                            <strong>Requisition Frequency:</strong> Withdrawn across <strong>{{ $selectedSnapshot->total_transactions }} separate transactions</strong>, active in <strong>{{ $selectedSnapshot->active_weeks_count }} of {{ $selectedSnapshot->valid_weeks_count }} operational weeks</strong>.
                        </li>
                        @if ($selectedSnapshot->primary_department_driver)
                            <li>
                                <strong>Primary Driver:</strong> The surge is primarily concentrated in <strong class="text-emerald-700 dark:text-emerald-400">{{ $selectedSnapshot->primary_department_driver }}</strong>@if ($selectedSnapshot->top_requester_driver), with top requester <strong>{{ $selectedSnapshot->top_requester_driver }}</strong>@endif.
                            </li>
                        @endif
                    </ul>
                </div>

                {{-- 8-Week Consumption Trend Chart --}}
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <h3 class="text-[11px] font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-sm text-blue-600">bar_chart</span>
                            <span>8-Week Consumption Trend (Chronological W1 &rarr; W8)</span>
                        </h3>
                        <div class="flex items-center gap-3 text-[10px] text-slate-400 font-bold">
                            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-slate-400 dark:bg-slate-500"></span> Prior 4W</span>
                            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-emerald-500"></span> Recent 4W</span>
                        </div>
                    </div>

                    @php
                        $maxWeeklyQty = 1;
                        foreach ($drillDownSeries as $ws) {
                            if ($ws['qty'] > $maxWeeklyQty) {
                                $maxWeeklyQty = $ws['qty'];
                            }
                        }
                    @endphp

                    <div class="grid grid-cols-4 sm:grid-cols-8 gap-1.5 p-3 bg-slate-50 dark:bg-slate-800/40 rounded-lg border border-slate-200 dark:border-slate-800">
                        @foreach ($drillDownSeries as $idx => $ws)
                            @php
                                $heightPct = max(10, round(($ws['qty'] / $maxWeeklyQty) * 100));
                                $isRecent = $idx >= 4;
                            @endphp
                            <div class="flex flex-col items-center space-y-1">
                                <div class="text-[10px] font-bold font-mono {{ $ws['qty'] > 0 ? ($isRecent ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-700 dark:text-slate-300') : 'text-slate-400' }}">
                                    {{ $ws['qty'] }}
                                </div>
                                <div class="w-full h-20 bg-slate-200/60 dark:bg-slate-700/60 rounded-md flex items-end p-0.5">
                                    <div 
                                        class="w-full rounded transition-all {{ $isRecent ? 'bg-emerald-500 dark:bg-emerald-400 shadow-xs' : 'bg-slate-400 dark:bg-slate-500' }}" 
                                        style="height: {{ $heightPct }}%;">
                                    </div>
                                </div>
                                <div class="text-[9px] font-bold text-slate-500">{{ $ws['week_label'] }}</div>
                                <div class="text-[8px] text-slate-400 font-mono">{{ $ws['tx_count'] }} trx</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- "WHY IS THIS RISING?" Department Attribution Breakdown Table --}}
                <div class="space-y-2">
                    <h3 class="text-[11px] font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm text-emerald-600">account_tree</span>
                        <span>Why Is This Rising? • Department Demand Contribution</span>
                    </h3>

                    @if (!empty($drillDownAttribution['departments']))
                        <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-lg">
                            <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                                <thead class="bg-slate-50 dark:bg-slate-800/80 text-[9px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200 dark:border-slate-800">
                                    <tr>
                                        <th class="py-2 px-2.5">Department</th>
                                        <th class="py-2 px-2.5 text-right">Prior (4W)</th>
                                        <th class="py-2 px-2.5 text-right">Recent (4W)</th>
                                        <th class="py-2 px-2.5 text-right">Change (&Delta;Qty)</th>
                                        <th class="py-2 px-2.5 text-center">Contribution Share</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @foreach ($drillDownAttribution['departments'] as $dInfo)
                                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50">
                                            <td class="py-1.5 px-2.5 font-bold text-slate-800 dark:text-slate-200 text-xs">
                                                {{ $dInfo['department_name'] }}
                                            </td>
                                            <td class="py-1.5 px-2.5 text-right font-mono text-xs">{{ $dInfo['prior_qty'] }}</td>
                                            <td class="py-1.5 px-2.5 text-right font-mono font-bold text-slate-900 dark:text-white text-xs">{{ $dInfo['recent_qty'] }}</td>
                                            <td class="py-1.5 px-2.5 text-right font-mono font-bold text-xs">
                                                @if ($dInfo['delta_qty'] > 0)
                                                    <span class="text-rose-600 dark:text-rose-400">+{{ $dInfo['delta_qty'] }}</span>
                                                @elseif ($dInfo['delta_qty'] < 0)
                                                    <span class="text-blue-600 dark:text-blue-400">{{ $dInfo['delta_qty'] }}</span>
                                                @else
                                                    <span class="text-slate-400">0</span>
                                                @endif
                                            </td>
                                            <td class="py-1.5 px-2.5 text-center">
                                                @if (($dInfo['contribution_share_pct'] ?? 0) > 0)
                                                    <div class="flex items-center justify-center gap-2">
                                                        <div class="w-16 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                                                            <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $dInfo['contribution_share_pct'] }}%"></div>
                                                        </div>
                                                        <span class="font-mono font-bold text-[10px] text-emerald-700 dark:text-emerald-400">
                                                            {{ $dInfo['contribution_share_pct'] }}%
                                                        </span>
                                                    </div>
                                                @else
                                                    <span class="text-slate-400 text-[9px]">0.0%</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-xs text-slate-400 italic">No department transactions recorded in this period.</p>
                    @endif
                </div>

                {{-- Top Requesters Section --}}
                @if (!empty($drillDownAttribution['top_requesters']))
                    <div class="space-y-1.5">
                        <h4 class="text-[10px] font-black uppercase tracking-wider text-slate-500 flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs">person</span>
                            <span>Top Requesters in Recent 4-Week Window</span>
                        </h4>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($drillDownAttribution['top_requesters'] as $u)
                                <div class="px-2.5 py-1 rounded-md bg-slate-100 dark:bg-slate-800 text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                    <span>{{ $u['user_name'] }}</span>
                                    <span class="px-1.5 py-0.2 rounded bg-slate-200 dark:bg-slate-700 font-mono text-[10px] text-emerald-700 dark:text-emerald-400">
                                        {{ $u['qty'] }} {{ $selectedSnapshot->variant->unit }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Modal Footer --}}
                <div class="flex justify-end pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button 
                        type="button" 
                        wire:click="closeDetail"
                        class="px-4 py-1.5 bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 font-bold text-xs rounded-lg transition-all">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
