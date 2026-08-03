<div class="pt-24 px-4 pb-6 lg:px-8 min-h-screen flex flex-col bg-slate-50/30">
    <!-- Header -->
    <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4 mb-6">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-slate-900 rounded-lg flex items-center justify-center text-purple-400 shadow-sm">
                <span class="material-symbols-outlined text-2xl">assignment</span>
            </div>
            <div>
                <h1 class="text-3xl font-black tracking-tight text-slate-900">Inventory Planning</h1>
                <p class="text-xs font-bold text-slate-500 mt-1 uppercase tracking-widest">Master Data & Lead Time Profiles</p>
            </div>
        </div>

        <!-- Interactive Planning Dashboard -->
        <div class="flex flex-wrap items-center gap-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg p-1.5 shadow-sm max-w-full overflow-x-auto">
            <!-- Total Items -->
            <button wire:click="toggleStatusFilter('')" 
                class="flex items-center gap-1.5 px-3 py-1.5 rounded transition-all duration-200 group border text-[11px] font-black shrink-0 {{ $statusFilter === '' ? 'bg-slate-900 text-white border-slate-900 shadow-sm dark:bg-slate-750 dark:border-slate-700' : 'bg-slate-50 dark:bg-slate-800 text-slate-665 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 hover:scale-105 active:scale-95' }}">
                <span class="material-symbols-outlined text-sm {{ $statusFilter === '' ? 'text-white' : 'text-slate-500' }}">inventory_2</span>
                <span>Total Items</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-black {{ $statusFilter === '' ? 'bg-slate-800 text-white dark:bg-slate-900' : 'bg-slate-200/60 text-slate-700 dark:bg-slate-700 dark:text-slate-300' }}">
                    {{ number_format($dashboardCounts['TOTAL']) }}
                </span>
            </button>

            <div class="w-px h-5 bg-slate-200 dark:bg-slate-800"></div>

            <!-- Critical -->
            <button wire:click="toggleStatusFilter('CRITICAL')" 
                class="flex items-center gap-1.5 px-3 py-1.5 rounded transition-all duration-200 group border text-[11px] font-black shrink-0 {{ $statusFilter === 'CRITICAL' ? 'bg-rose-600 text-white border-rose-600 shadow-sm shadow-rose-600/10' : 'bg-rose-55 dark:bg-rose-950/10 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-900/50 hover:bg-rose-50 dark:hover:bg-rose-950/20 hover:scale-105 active:scale-95' }}">
                <span class="material-symbols-outlined text-sm {{ $statusFilter === 'CRITICAL' ? 'text-white' : 'text-rose-600' }}">error</span>
                <span>Critical</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-black {{ $statusFilter === 'CRITICAL' ? 'bg-rose-700 text-white' : 'bg-rose-100 text-rose-805 dark:bg-rose-950/30' }}">
                    {{ number_format($dashboardCounts['CRITICAL']) }}
                </span>
            </button>

            <!-- Reorder -->
            <button wire:click="toggleStatusFilter('REORDER')" 
                class="flex items-center gap-1.5 px-3 py-1.5 rounded transition-all duration-200 group border text-[11px] font-black shrink-0 {{ $statusFilter === 'REORDER' ? 'bg-amber-500 text-white border-amber-500 shadow-sm shadow-amber-500/10 dark:bg-amber-600 dark:border-amber-600' : 'bg-amber-55 dark:bg-amber-950/10 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-900/50 hover:bg-amber-50 dark:hover:bg-amber-950/20 hover:scale-105 active:scale-95' }}">
                <span class="material-symbols-outlined text-sm {{ $statusFilter === 'REORDER' ? 'text-white' : 'text-amber-500' }}">schedule</span>
                <span>Reorder</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-black {{ $statusFilter === 'REORDER' ? 'bg-amber-600 text-white dark:bg-amber-700' : 'bg-amber-100 text-amber-805 dark:bg-amber-950/30' }}">
                    {{ number_format($dashboardCounts['REORDER']) }}
                </span>
            </button>

            <!-- Watchlist -->
            <button wire:click="toggleStatusFilter('WATCHLIST')" 
                class="flex items-center gap-1.5 px-3 py-1.5 rounded transition-all duration-200 group border text-[11px] font-black shrink-0 {{ $statusFilter === 'WATCHLIST' ? 'bg-yellow-500 text-slate-900 border-yellow-500 shadow-sm shadow-yellow-500/10 dark:bg-yellow-600 dark:border-yellow-600 dark:text-white' : 'bg-yellow-55 dark:bg-yellow-950/10 text-yellow-800 dark:text-yellow-400 border-yellow-250 dark:border-yellow-900/50 hover:bg-yellow-50 dark:hover:bg-yellow-950/20 hover:scale-105 active:scale-95' }}">
                <span class="material-symbols-outlined text-sm {{ $statusFilter === 'WATCHLIST' ? 'text-slate-900 dark:text-white' : 'text-yellow-600' }}">visibility</span>
                <span>Watchlist</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-black {{ $statusFilter === 'WATCHLIST' ? 'bg-yellow-605 text-slate-900 dark:bg-yellow-750 dark:text-white' : 'bg-yellow-100 text-yellow-805 dark:bg-yellow-950/30' }}">
                    {{ number_format($dashboardCounts['WATCHLIST']) }}
                </span>
            </button>

            <!-- Healthy -->
            <button wire:click="toggleStatusFilter('HEALTHY')" 
                class="flex items-center gap-1.5 px-3 py-1.5 rounded transition-all duration-200 group border text-[11px] font-black shrink-0 {{ $statusFilter === 'HEALTHY' ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm shadow-emerald-600/10' : 'bg-emerald-55 dark:bg-emerald-950/10 text-emerald-700 dark:text-emerald-400 border-emerald-205 dark:border-emerald-900/50 hover:bg-emerald-50 dark:hover:bg-emerald-950/20 hover:scale-105 active:scale-95' }}">
                <span class="material-symbols-outlined text-sm {{ $statusFilter === 'HEALTHY' ? 'text-white' : 'text-emerald-600' }}">check_circle</span>
                <span>Healthy</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-black {{ $statusFilter === 'HEALTHY' ? 'bg-emerald-700 text-white' : 'bg-emerald-105 text-emerald-805 dark:bg-emerald-950/30' }}">
                    {{ number_format($dashboardCounts['HEALTHY']) }}
                </span>
            </button>

            <!-- Overstock -->
            <button wire:click="toggleStatusFilter('OVERSTOCK')" 
                class="flex items-center gap-1.5 px-3 py-1.5 rounded transition-all duration-200 group border text-[11px] font-black shrink-0 {{ $statusFilter === 'OVERSTOCK' ? 'bg-blue-600 text-white border-blue-600 shadow-sm shadow-blue-600/10' : 'bg-blue-55 dark:bg-blue-950/10 text-blue-700 dark:text-blue-450 border-blue-200 dark:border-blue-900/50 hover:bg-blue-50 dark:hover:bg-blue-950/20 hover:scale-105 active:scale-95' }}">
                <span class="material-symbols-outlined text-sm {{ $statusFilter === 'OVERSTOCK' ? 'text-white' : 'text-blue-600' }}">stacked_bar_chart</span>
                <span>Overstock</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-black {{ $statusFilter === 'OVERSTOCK' ? 'bg-blue-700 text-white' : 'bg-blue-105 text-blue-805 dark:bg-blue-950/30' }}">
                    {{ number_format($dashboardCounts['OVERSTOCK']) }}
                </span>
            </button>

            <!-- Unknown -->
            <button wire:click="toggleStatusFilter('UNKNOWN')" 
                class="flex items-center gap-1.5 px-3 py-1.5 rounded transition-all duration-200 group border text-[11px] font-black shrink-0 {{ $statusFilter === 'UNKNOWN' ? 'bg-slate-600 text-white border-slate-600 shadow-sm shadow-slate-600/10' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-350 border-slate-200 dark:border-slate-700 hover:bg-slate-200/50 dark:hover:bg-slate-750 hover:scale-105 active:scale-95' }}">
                <span class="material-symbols-outlined text-sm {{ $statusFilter === 'UNKNOWN' ? 'text-white' : 'text-slate-500' }}">help_outline</span>
                <span>Unknown</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-black {{ $statusFilter === 'UNKNOWN' ? 'bg-slate-700 text-white' : 'bg-slate-200 text-slate-705 dark:bg-slate-900' }}">
                    {{ number_format($dashboardCounts['UNKNOWN']) }}
                </span>
            </button>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md py-2.5 px-md shadow-sm mb-sm flex flex-col xl:flex-row gap-sm items-center justify-between">
        <!-- Search -->
        <div class="relative w-full xl:w-96 flex-1 min-w-0">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
            <input wire:model.live.debounce.300ms="search" class="w-full pl-9 pr-4 h-9 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md focus:ring-1 focus:ring-primary/20 focus:border-primary text-xs font-bold placeholder:text-slate-400 transition-all text-on-surface" placeholder="Search by name, ERP, or barcode..." type="text"/>
            
            <div wire:loading wire:target="search" class="absolute right-4 top-1/2 -translate-y-1/2">
                <span class="material-symbols-outlined animate-spin text-primary text-sm">progress_activity</span>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-sm w-full xl:w-auto shrink-0 items-center">
            <!-- Procurement Filter -->
            <div class="relative flex-1 md:flex-none">
                <select wire:model.live="procurementFilter" class="w-full h-9 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md text-xs font-bold text-slate-665 focus:ring-1 focus:ring-primary/20 focus:border-primary pl-4 pr-10">
                    <option value="">All Procurement</option>
                    <option value="LOCAL">Local Only</option>
                    <option value="IMPORT">Import Only</option>
                </select>
            </div>

            <!-- Class Filter -->
            <div class="relative flex-1 md:flex-none">
                <select wire:model.live="classFilter" class="w-full h-9 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md text-xs font-bold text-slate-665 focus:ring-1 focus:ring-primary/20 focus:border-primary pl-4 pr-10">
                    <option value="">All Classes</option>
                    <option value="CONSUMABLE">Consumable Only</option>
                    <option value="SPAREPART">Sparepart Only</option>
                </select>
            </div>

            <!-- Planning Status Filter -->
            <div class="relative flex-1 md:flex-none">
                <select wire:model.live="statusFilter" class="w-full h-9 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md text-xs font-bold text-slate-665 focus:ring-1 focus:ring-primary/20 focus:border-primary pl-4 pr-10">
                    <option value="">All Statuses</option>
                    <option value="CRITICAL">Critical Only</option>
                    <option value="REORDER">Reorder Only</option>
                    <option value="WATCHLIST">Watchlist Only</option>
                    <option value="HEALTHY">Healthy Only</option>
                    <option value="OVERSTOCK">Overstock Only</option>
                    <option value="UNKNOWN">Unknown Only</option>
                </select>
            </div>

            <!-- Per Page -->
            <select wire:model.live="perPage" class="flex-1 md:flex-none h-9 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md text-xs font-bold text-slate-665 focus:ring-1 focus:ring-primary/20 focus:border-primary pl-4 pr-10 font-bold">
                <option value="25">25 per page</option>
                <option value="50">50 per page</option>
                <option value="100">100 per page</option>
            </select>
        </div>
    </div>

    <!-- Table Container -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md shadow-sm overflow-hidden mb-md flex-1">
        <div class="overflow-x-auto overflow-y-visible">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th wire:click="sortBy('erp_code')" class="px-md py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest cursor-pointer hover:text-primary transition-colors">
                            <div class="flex items-center gap-1">
                                ERP CODE
                                @if($sortField === 'erp_code')
                                    <span class="text-[9px] ml-0.5">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-md py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest">
                            BARCODE
                        </th>
                        <th wire:click="sortBy('name')" class="px-md py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest cursor-pointer hover:text-primary transition-colors w-[30%] min-w-[320px]">
                            <div class="flex items-center gap-1">
                                ITEM
                                @if($sortField === 'name')
                                    <span class="text-[9px] ml-0.5">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </div>
                        </th>
                        <th wire:click="sortBy('stock')" class="px-md py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest cursor-pointer hover:text-primary transition-colors">
                            <div class="flex items-center gap-1">
                                CURRENT STOCK
                                @if($sortField === 'stock')
                                    <span class="text-[9px] ml-0.5">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </div>
                        </th>
                        <th wire:click="sortBy('weekly_avg')" class="px-md py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest cursor-pointer hover:text-primary transition-colors">
                            <div class="flex items-center justify-center gap-1">
                                WEEKLY AVG
                                @if($sortField === 'weekly_avg')
                                    <span class="text-[9px] ml-0.5">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-md py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">
                            TREND
                        </th>
                        <th wire:click="sortBy('days_left')" class="px-md py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest cursor-pointer hover:text-primary transition-colors">
                            <div class="flex items-center justify-center gap-1">
                                DAYS LEFT
                                @if($sortField === 'days_left')
                                    <span class="text-[9px] ml-0.5">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </div>
                        </th>
                        <th wire:click="sortBy('procurement_type')" class="px-md py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest cursor-pointer hover:text-primary transition-colors">
                            <div class="flex items-center gap-1">
                                PROCUREMENT
                                @if($sortField === 'procurement_type')
                                    <span class="text-[9px] ml-0.5">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </div>
                        </th>
                        <th wire:click="sortBy('inventory_class')" class="px-md py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest cursor-pointer hover:text-primary transition-colors">
                            <div class="flex items-center gap-1">
                                INVENTORY CLASS
                                @if($sortField === 'inventory_class')
                                    <span class="text-[9px] ml-0.5">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </div>
                        </th>
                        <th wire:click="sortBy('lead_time_days')" class="px-md py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest cursor-pointer hover:text-primary transition-colors">
                            <div class="flex items-center justify-center gap-1">
                                LEAD TIME
                                @if($sortField === 'lead_time_days')
                                    <span class="text-[9px] ml-0.5">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </div>
                        </th>
                        <th wire:click="sortBy('status')" class="px-md py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest cursor-pointer hover:text-primary transition-colors">
                            <div class="flex items-center justify-center gap-1">
                                PLANNING STATUS
                                @if($sortField === 'status')
                                    <span class="text-[9px] ml-0.5">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-md py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">
                            ACTIONS
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($variants as $variant)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <!-- ERP Code -->
                            <td class="px-md py-2 font-mono text-[11px] font-bold text-slate-800 select-all whitespace-nowrap">
                                <a href="{{ route('items.show', $variant->id) }}" class="hover:text-primary hover:underline">
                                    {{ $variant->erp_code ?: '-' }}
                                </a>
                            </td>

                            <!-- Barcode -->
                            <td class="px-md py-2 font-mono text-[11px] font-bold text-slate-500 select-all whitespace-nowrap">
                                {{ $variant->barcodes->where('is_primary', true)->first()?->barcode ?? $variant->barcodes->first()?->barcode ?? '-' }}
                            </td>
                            
                            <!-- Name -->
                            <td class="px-md py-2">
                                <div class="w-full">
                                    <p class="text-xs font-black text-slate-900 leading-snug">
                                        <a href="{{ route('items.show', $variant->id) }}" class="hover:text-primary hover:underline">
                                            {{ $variant->item->name }}
                                        </a>
                                    </p>
                                </div>
                            </td>

                            <!-- Stock -->
                            <td class="px-md py-2 text-xs font-bold text-slate-700 whitespace-nowrap">
                                {{ number_format($variant->total_stock) }} <span class="text-[9px] text-slate-400 font-bold uppercase">{{ $variant->unit }}</span>
                            </td>

                            <!-- Average Weekly -->
                            <td class="px-md py-2 text-xs font-bold text-slate-700 text-center">
                                {{ number_format($variant->weekly_avg, 2) }}
                            </td>

                            <!-- Trend -->
                            <td class="px-md py-2 text-center">
                                @if($variant->trend === 'Increasing')
                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-50 text-emerald-600 font-black text-xs" title="Increasing Trend">↑</span>
                                @elseif($variant->trend === 'Decreasing')
                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-rose-50 text-rose-600 font-black text-xs" title="Decreasing Trend">↓</span>
                                @else
                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-50 text-slate-550 font-black text-xs" title="Stable Trend">→</span>
                                @endif
                            </td>

                            <!-- Days Left -->
                            <td class="px-md py-2 text-xs font-bold text-slate-700 whitespace-nowrap text-center">
                                @if($variant->days_left === null)
                                    <span class="text-slate-400 font-bold text-[11px]">—</span>
                                @else
                                    {{ number_format(round($variant->days_left)) }} <span class="text-[9px] text-slate-400 font-bold uppercase">d</span>
                                @endif
                            </td>
 
                            <!-- Procurement Type (Neutral Text) -->
                            <td class="px-md py-2 align-middle">
                                <span class="text-xs font-medium text-slate-700 dark:text-slate-300 cursor-help" title="Configured in Item Master">
                                    {{ $variant->procurement_type }}
                                </span>
                            </td>

                            <!-- Inventory Class (Neutral Text) -->
                            <td class="px-md py-2 align-middle">
                                <span class="text-xs font-medium text-slate-700 dark:text-slate-300 cursor-help" title="Configured in Item Master">
                                    {{ ucfirst(strtolower($variant->inventory_class)) }}
                                </span>
                            </td>

                            <!-- Lead Time Days (Neutral Text) -->
                            <td class="px-md py-2 text-center align-middle">
                                <span class="text-xs font-medium text-slate-700 dark:text-slate-300 cursor-help" title="Configured in Item Master">
                                    {{ $variant->lead_time_days }} Days
                                </span>
                            </td>

                            <!-- Status -->
                            <td class="px-md py-2 text-center whitespace-nowrap">
                                @if($variant->health_status === 'CRITICAL')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-rose-50 text-rose-700 border border-rose-250 dark:bg-rose-950/30 dark:text-rose-400 dark:border-rose-900 animate-pulse">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                        CRITICAL
                                    </span>
                                @elseif($variant->health_status === 'REORDER NOW')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-50 text-amber-700 border border-amber-250 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-900">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                        REORDER NOW
                                    </span>
                                @elseif($variant->health_status === 'WATCHLIST')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-yellow-50 text-yellow-800 border border-yellow-250 dark:bg-yellow-950/30 dark:text-yellow-450 dark:border-yellow-900">
                                        <span class="w-1.5 h-1.5 rounded-full bg-yellow-500"></span>
                                        WATCHLIST
                                    </span>
                                @elseif($variant->health_status === 'HEALTHY')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-250 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        HEALTHY
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-800/30 dark:text-slate-400 dark:border-slate-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        NO CONSUMPTION
                                    </span>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="px-md py-2 text-center">
                                <div class="flex items-center justify-center">
                                    <a href="{{ route('items.show', $variant->id) }}" class="flex items-center justify-center w-8 h-8 rounded hover:bg-slate-100 text-slate-500 hover:text-slate-800 transition-colors" title="View details">
                                        <span class="material-symbols-outlined text-lg">visibility</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="px-md py-12 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <span class="material-symbols-outlined text-4xl">folder_off</span>
                                    <p class="text-xs font-bold">No item variants match the specified query.</p>
                                    <p class="text-[10px] mt-0.5">Try clearing filters or adjusting your search term.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-sm">
        {{ $variants->links() }}
    </div>
</div>
