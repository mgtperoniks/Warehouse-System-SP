<div x-data="{ 
    showToast: false, 
    toastMsg: '',
    copyBarcode(barcode) {
        if (!barcode || barcode === '-') return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(barcode).then(() => {
                this.toastMsg = 'Barcode copied';
                this.showToast = true;
                setTimeout(() => { this.showToast = false; }, 2000);
            }).catch(err => {
                console.error('Clipboard error', err);
            });
        } else {
            let textArea = document.createElement('textarea');
            textArea.value = barcode;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                this.toastMsg = 'Barcode copied';
                this.showToast = true;
                setTimeout(() => { this.showToast = false; }, 2000);
            } catch (err) {
                console.error('Fallback copy failed', err);
            }
            document.body.removeChild(textArea);
        }
    }
}">
    <!-- Top Configuration / Search Toolbar -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md py-2 px-md shadow-sm mb-sm flex flex-col xl:flex-row gap-sm items-center justify-between">
        <div class="relative w-full xl:w-96 flex-1 min-w-0">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
            <input wire:model.live.debounce.300ms="search" class="w-full pl-9 pr-4 h-9 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md focus:ring-1 focus:ring-primary/20 focus:border-primary text-xs font-bold placeholder:text-slate-400 transition-all text-on-surface" placeholder="Search by name, ERP, or barcode..." type="text"/>
            
            <div wire:loading wire:target="search" class="absolute right-4 top-1/2 -translate-y-1/2">
                <span class="material-symbols-outlined animate-spin text-primary text-sm">progress_activity</span>
            </div>
        </div>

        <div class="flex flex-wrap gap-sm w-full xl:w-auto shrink-0 items-center">
            <!-- Searchable Brand (Datalist) -->
            <div class="relative flex-1 md:flex-none">
                <input list="brands-list" wire:model.live="brandFilter" placeholder="Filter Brand..." class="w-full h-9 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md text-xs font-bold text-slate-600 focus:ring-1 focus:ring-primary/20 focus:border-primary pl-4 pr-10">
                <datalist id="brands-list">
                    @foreach($brands as $brand)
                        <option value="{{ $brand }}">
                    @endforeach
                </datalist>
                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-md pointer-events-none">branding_watermark</span>
            </div>

            <!-- Stock Status -->
            <select wire:model.live="stockStatusFilter" class="flex-1 md:flex-none h-9 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md text-xs font-bold text-slate-600 focus:ring-1 focus:ring-primary/20 focus:border-primary pl-4 pr-10">
                <option value="">All Status</option>
                <option value="IN_STOCK">In Stock</option>
                <option value="LOW_STOCK">Low Stock</option>
                <option value="OUT_OF_STOCK">Out of Stock</option>
            </select>

            <!-- Per Page -->
            <select wire:model.live="perPage" class="flex-1 md:flex-none h-9 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md text-xs font-bold text-slate-600 focus:ring-1 focus:ring-primary/20 focus:border-primary pl-4 pr-10 font-bold">
                <option value="25">25 per page</option>
                <option value="50">50 per page</option>
                <option value="100">100 per page</option>
            </select>
        </div>
    </div>

    <!-- Results Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md shadow-sm overflow-hidden mb-md">
        <div class="overflow-x-auto overflow-y-visible">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th wire:click="sortBy('erp_code')" class="px-md py-1.5 text-[9px] font-black text-slate-400 uppercase tracking-widest cursor-pointer hover:text-primary transition-colors">
                            <div class="flex items-center gap-1">
                                ERP CODE
                                @if($sortField === 'erp_code')
                                    <span class="material-symbols-outlined text-xs">{{ $sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward' }}</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-md py-1.5 text-[9px] font-black text-slate-400 uppercase tracking-widest">
                            BARCODE
                        </th>
                        <th class="px-md py-1.5 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">
                            COPY
                        </th>
                        <th wire:click="sortBy('name')" class="px-md py-1.5 text-[9px] font-black text-slate-400 uppercase tracking-widest cursor-pointer hover:text-primary transition-colors">
                            <div class="flex items-center gap-1">
                                ITEM NAME
                                @if($sortField === 'name')
                                    <span class="material-symbols-outlined text-xs">{{ $sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward' }}</span>
                                @endif
                            </div>
                        </th>
                        <th wire:click="sortBy('stock')" class="px-md py-1.5 text-[9px] font-black text-slate-400 uppercase tracking-widest cursor-pointer hover:text-primary transition-colors text-center">
                            <div class="flex items-center justify-center gap-1">
                                STOCK
                                @if($sortField === 'stock')
                                    <span class="material-symbols-outlined text-xs">{{ $sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward' }}</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-md py-1.5 text-[9px] font-black text-slate-400 uppercase tracking-widest">UNIT</th>
                        <th class="px-md py-1.5 text-[9px] font-black text-slate-400 uppercase tracking-widest">BRAND</th>
                        <th wire:click="sortBy('movement')" class="px-md py-1.5 text-[9px] font-black text-slate-400 uppercase tracking-widest cursor-pointer hover:text-primary transition-colors text-right">
                            <div class="flex items-center justify-end gap-1">
                                LAST MOVEMENT
                                @if($sortField === 'movement')
                                    <span class="material-symbols-outlined text-xs">{{ $sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward' }}</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-md py-1.5 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">STATUS</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($variants as $variant)
                        <tr class="hover:bg-slate-50 transition-colors cursor-pointer group" onclick="window.location.href='{{ route('items.show', $variant->id) }}'">
                            <td class="px-md py-1.5">
                                <span class="font-mono text-xs font-black bg-slate-100 px-2 py-0.5 rounded text-slate-700 group-hover:bg-primary/10 group-hover:text-primary transition-colors">{{ $variant->erp_code ?? '-' }}</span>
                            </td>
                            @php
                                $primaryBarcode = $variant->barcodes->firstWhere('is_primary', true)?->barcode ?? $variant->barcodes->first()?->barcode ?? '-';
                            @endphp
                            <td class="px-md py-1.5">
                                @if($primaryBarcode !== '-')
                                    <span class="font-mono text-xs font-bold text-slate-600">{{ $primaryBarcode }}</span>
                                @else
                                    <span class="text-xs font-bold text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-md py-1.5 text-center">
                                @if($primaryBarcode !== '-')
                                    <button type="button" @click.stop="copyBarcode('{{ $primaryBarcode }}')" class="p-1 rounded hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-primary dark:hover:text-primary transition-colors inline-flex items-center justify-center outline-none" title="Copy Barcode">
                                        <span class="material-symbols-outlined text-sm">content_copy</span>
                                    </button>
                                @else
                                    <span class="text-xs font-bold text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-md py-1.5">
                                <div class="flex flex-col">
                                    <span class="font-bold text-xs text-slate-900 leading-tight">{{ $variant->item->name }}</span>
                                </div>
                            </td>
                            <td class="px-md py-1.5 text-center">
                                <span class="font-black text-xs text-slate-900">{{ number_format($variant->total_stock ?? 0) }}</span>
                            </td>
                            <td class="px-md py-1.5">
                                <span class="text-xs font-bold text-slate-500 uppercase">{{ $variant->unit ?? 'PCS' }}</span>
                            </td>
                            <td class="px-md py-1.5">
                                <span class="text-xs font-bold text-slate-600">{{ $variant->brand ?? '-' }}</span>
                            </td>
                            <td class="px-md py-1.5 text-right">
                                <span class="text-[10px] text-slate-400 font-medium">
                                    {{ $variant->last_movement_at ? \Carbon\Carbon::parse($variant->last_movement_at)->diffForHumans() : 'No movement' }}
                                </span>
                            </td>
                            <td class="px-md py-1.5 text-center">
                                @php
                                    $stock = $variant->total_stock ?? 0;
                                    $min = $variant->total_min_stock ?? 0;
                                    
                                    if ($stock <= 0) {
                                        $label = 'OUT OF STOCK';
                                        $cls = 'bg-red-100 text-red-750';
                                    } elseif ($stock <= $min) {
                                        $label = 'LOW STOCK';
                                        $cls = 'bg-amber-100 text-amber-750';
                                    } else {
                                        $label = 'IN STOCK';
                                        $cls = 'bg-emerald-100 text-emerald-750';
                                    }
                                @endphp
                                <span class="px-2 py-0.5 rounded text-[9px] font-black tracking-widest uppercase {{ $cls }}">
                                    {{ $label }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-md py-lg text-center">
                                <div class="flex flex-col items-center">
                                    <span class="material-symbols-outlined text-4xl text-slate-300 mb-2">inventory_2</span>
                                    <p class="text-sm text-slate-500 font-bold">No items found matching your criteria.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="px-md">
        {{ $variants->links(data: ['scrollTo' => false]) }}
    </div>

    <!-- Toast Notification Banner -->
    <div x-show="showToast" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-4 right-4 z-50 bg-slate-900 text-emerald-400 font-mono text-[11px] font-bold px-4 py-2.5 rounded-lg shadow-xl border border-emerald-500/20 flex items-center gap-2"
         style="display: none;">
        <span class="material-symbols-outlined text-sm">check_circle</span>
        <span x-text="toastMsg"></span>
    </div>
</div>
