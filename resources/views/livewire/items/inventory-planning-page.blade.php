<div class="pt-24 px-4 pb-6 lg:px-8 min-h-screen flex flex-col bg-slate-50/30" x-data="{
    editingLeadTimeId: null,
}">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-slate-900 rounded-lg flex items-center justify-center text-purple-400 shadow-sm">
                <span class="material-symbols-outlined text-2xl">assignment</span>
            </div>
            <div>
                <h1 class="text-3xl font-black tracking-tight text-slate-900">Inventory Planning</h1>
                <p class="text-xs font-bold text-slate-500 mt-1 uppercase tracking-widest">Master Data & Lead Time Profiles</p>
            </div>
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
                <select wire:model.live="procurementFilter" class="w-full h-9 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md text-xs font-bold text-slate-600 focus:ring-1 focus:ring-primary/20 focus:border-primary pl-4 pr-10">
                    <option value="">All Procurement</option>
                    <option value="LOCAL">Local Only</option>
                    <option value="IMPORT">Import Only</option>
                </select>
            </div>

            <!-- Class Filter -->
            <div class="relative flex-1 md:flex-none">
                <select wire:model.live="classFilter" class="w-full h-9 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md text-xs font-bold text-slate-600 focus:ring-1 focus:ring-primary/20 focus:border-primary pl-4 pr-10">
                    <option value="">All Classes</option>
                    <option value="CONSUMABLE">Consumable Only</option>
                    <option value="SPAREPART">Sparepart Only</option>
                </select>
            </div>

            <!-- Per Page -->
            <select wire:model.live="perPage" class="flex-1 md:flex-none h-9 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md text-xs font-bold text-slate-600 focus:ring-1 focus:ring-primary/20 focus:border-primary pl-4 pr-10 font-bold">
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
 
                            <!-- Procurement Type (Inline Edit) -->
                            <td class="px-md py-2">
                                <div class="w-[95px]">
                                    <select 
                                        wire:change="updatePlanning({{ $variant->id }}, 'procurement_type', $event.target.value)"
                                        class="w-full h-8 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded px-2 text-[11px] font-bold text-slate-700 dark:text-slate-350 focus:ring-1 focus:ring-primary/20 focus:border-primary transition-all"
                                    >
                                        <option value="LOCAL" {{ $variant->procurement_type === 'LOCAL' ? 'selected' : '' }}>LOCAL</option>
                                        <option value="IMPORT" {{ $variant->procurement_type === 'IMPORT' ? 'selected' : '' }}>IMPORT</option>
                                    </select>
                                </div>
                            </td>

                            <!-- Inventory Class (Inline Edit) -->
                            <td class="px-md py-2">
                                <div class="w-36">
                                    <select 
                                        wire:change="updatePlanning({{ $variant->id }}, 'inventory_class', $event.target.value)"
                                        class="w-full h-8 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded px-2 text-[11px] font-bold text-slate-700 dark:text-slate-350 focus:ring-1 focus:ring-primary/20 focus:border-primary transition-all"
                                    >
                                        <option value="CONSUMABLE" {{ $variant->inventory_class === 'CONSUMABLE' ? 'selected' : '' }}>CONSUMABLE</option>
                                        <option value="SPAREPART" {{ $variant->inventory_class === 'SPAREPART' ? 'selected' : '' }}>SPAREPART</option>
                                    </select>
                                </div>
                            </td>

                            <!-- Lead Time Days (Inline Edit) -->
                            <td class="px-md py-2 text-center">
                                <div class="w-[75px] relative mx-auto" x-data="{ value: '{{ $variant->lead_time_days }}' }">
                                    <input 
                                        type="number" 
                                        x-model="value"
                                        @blur="$wire.updatePlanning({{ $variant->id }}, 'lead_time_days', value)"
                                        @keydown.enter="$wire.updatePlanning({{ $variant->id }}, 'lead_time_days', value); $el.blur()"
                                        class="w-full h-8 pr-6 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded pl-2.5 text-[11px] font-bold text-slate-700 dark:text-slate-350 focus:ring-1 focus:ring-primary/20 focus:border-primary transition-all text-center"
                                        min="1" 
                                        max="365"
                                    >
                                    <span class="absolute right-1.5 top-1/2 -translate-y-1/2 text-[9px] font-bold text-slate-400 pointer-events-none">d</span>
                                </div>
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
