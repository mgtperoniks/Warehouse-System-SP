<div>
    <!-- Top Configuration / Search Toolbar -->
    <div class="bg-surface-container-lowest rounded-3xl p-6 shadow-sm mb-6 flex flex-col xl:flex-row gap-4 items-center justify-between border-b-4 border-primary">
        <div class="relative w-full xl:w-96 flex-1 min-w-0">
            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-2xl">search</span>
            <input wire:model.live.debounce.300ms="search" class="w-full pl-12 pr-4 py-3 bg-surface-container-high rounded-full border-none focus:ring-2 focus:ring-primary text-sm font-bold placeholder:text-slate-400 transition-all text-on-surface" placeholder="Search by name, ERP, or barcode..." type="text"/>
            
            <div wire:loading wire:target="search" class="absolute right-4 top-1/2 -translate-y-1/2">
                <span class="material-symbols-outlined animate-spin text-primary">progress_activity</span>
            </div>
        </div>

        <div class="flex flex-wrap gap-3 w-full xl:w-auto shrink-0 items-center">
            <!-- Searchable Brand (Datalist) -->
            <div class="relative flex-1 md:flex-none">
                <input list="brands-list" wire:model.live="brandFilter" placeholder="Filter Brand..." class="w-full bg-surface-container-high border-none rounded-xl text-sm font-bold text-slate-600 focus:ring-2 focus:ring-primary py-3 pl-4 pr-10">
                <datalist id="brands-list">
                    @foreach($brands as $brand)
                        <option value="{{ $brand }}">
                    @endforeach
                </datalist>
                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg pointer-events-none">branding_watermark</span>
            </div>

            <!-- Stock Status -->
            <select wire:model.live="stockStatusFilter" class="flex-1 md:flex-none bg-surface-container-high border-none rounded-xl text-sm font-bold text-slate-600 focus:ring-2 focus:ring-primary py-3 pl-4 pr-10">
                <option value="">All Status</option>
                <option value="IN_STOCK">In Stock</option>
                <option value="LOW_STOCK">Low Stock</option>
                <option value="OUT_OF_STOCK">Out of Stock</option>
            </select>

            <!-- Per Page -->
            <select wire:model.live="perPage" class="flex-1 md:flex-none bg-surface-container-high border-none rounded-xl text-sm font-bold text-slate-600 focus:ring-2 focus:ring-primary py-3 pl-4 pr-10">
                <option value="25">25 per page</option>
                <option value="50">50 per page</option>
                <option value="100">100 per page</option>
            </select>
        </div>
    </div>

    <!-- Results Table -->
    <div class="bg-surface-container-lowest rounded-3xl shadow-sm overflow-hidden border border-slate-100 mb-8">
        <div class="overflow-x-auto overflow-y-visible">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th wire:click="sortBy('erp_code')" class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest cursor-pointer hover:text-primary transition-colors">
                            <div class="flex items-center gap-1">
                                ERP CODE
                                @if($sortField === 'erp_code')
                                    <span class="material-symbols-outlined text-sm">{{ $sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward' }}</span>
                                @endif
                            </div>
                        </th>
                        <th wire:click="sortBy('name')" class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest cursor-pointer hover:text-primary transition-colors">
                            <div class="flex items-center gap-1">
                                ITEM NAME
                                @if($sortField === 'name')
                                    <span class="material-symbols-outlined text-sm">{{ $sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward' }}</span>
                                @endif
                            </div>
                        </th>
                        <th wire:click="sortBy('stock')" class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest cursor-pointer hover:text-primary transition-colors text-center">
                            <div class="flex items-center justify-center gap-1">
                                STOCK
                                @if($sortField === 'stock')
                                    <span class="material-symbols-outlined text-sm">{{ $sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward' }}</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">UNIT</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">BRAND</th>
                        <th wire:click="sortBy('movement')" class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest cursor-pointer hover:text-primary transition-colors text-right">
                            <div class="flex items-center justify-end gap-1">
                                LAST MOVEMENT
                                @if($sortField === 'movement')
                                    <span class="material-symbols-outlined text-sm">{{ $sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward' }}</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">STATUS</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($variants as $variant)
                        <tr class="hover:bg-slate-50 transition-colors cursor-pointer group" onclick="window.location.href='{{ route('items.show', $variant->id) }}'">
                            <td class="px-6 py-4">
                                <span class="font-mono text-xs font-black bg-slate-100 px-2 py-1 rounded text-slate-700 group-hover:bg-primary/10 group-hover:text-primary transition-colors">{{ $variant->erp_code ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col">
                                    <span class="font-bold text-slate-900 leading-tight">{{ $variant->item->name }}</span>
                                    @if($variant->barcodes->isNotEmpty())
                                        <span class="text-[10px] text-slate-400 font-medium">Barcode: {{ $variant->barcodes->first()->barcode }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="font-black text-slate-900">{{ number_format($variant->total_stock ?? 0) }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-xs font-bold text-slate-500 uppercase">{{ $variant->unit ?? 'PCS' }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-xs font-bold text-slate-600">{{ $variant->brand ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="text-xs text-slate-400 font-medium">
                                    {{ $variant->last_movement_at ? \Carbon\Carbon::parse($variant->last_movement_at)->diffForHumans() : 'No movement' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                @php
                                    $stock = $variant->total_stock ?? 0;
                                    $min = $variant->total_min_stock ?? 0;
                                    
                                    if ($stock <= 0) {
                                        $label = 'OUT OF STOCK';
                                        $cls = 'bg-red-100 text-red-700';
                                    } elseif ($stock <= $min) {
                                        $label = 'LOW STOCK';
                                        $cls = 'bg-amber-100 text-amber-700';
                                    } else {
                                        $label = 'IN STOCK';
                                        $cls = 'bg-emerald-100 text-emerald-700';
                                    }
                                @endphp
                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-black tracking-widest uppercase {{ $cls }}">
                                    {{ $label }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
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
    <div class="px-6">
        {{ $variants->links(data: ['scrollTo' => false]) }}
    </div>
</div>
