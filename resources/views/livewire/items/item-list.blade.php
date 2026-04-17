<div>
    <!-- Top Configuration / Search Toolbar -->
    <div class="bg-surface-container-lowest rounded-3xl p-6 shadow-sm mb-6 flex flex-col md:flex-row gap-4 items-center justify-between border-b-4 border-primary">
        <div class="relative w-full md:w-96 flex-1 min-w-0">
            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-2xl">search</span>
            <input wire:model.live.debounce.300ms="search" class="w-full pl-12 pr-4 py-3 bg-surface-container-high rounded-full border-none focus:ring-2 focus:ring-primary text-sm font-bold placeholder:text-slate-400 transition-all text-on-surface" placeholder="Search by name, ERP, SKU or barcode..." type="text"/>
            
            <div wire:loading wire:target="search" class="absolute right-4 top-1/2 -translate-y-1/2">
                <span class="material-symbols-outlined animate-spin text-primary">progress_activity</span>
            </div>
        </div>

        <div class="flex gap-3 w-full md:w-auto shrink-0 overflow-x-auto no-scrollbar">
            <select wire:model.live="brandFilter" class="bg-surface-container-high border-none rounded-xl text-sm font-bold text-slate-600 focus:ring-2 focus:ring-primary py-3 pl-4 pr-10">
                <option value="">All Brands</option>
                @foreach($brands as $brand)
                    <option value="{{ $brand }}">{{ $brand }}</option>
                @endforeach
            </select>
            
            <button class="bg-surface-container-high text-slate-600 px-4 py-3 rounded-xl flex items-center gap-2 hover:bg-slate-200 transition-colors active:scale-95 font-bold text-sm shrink-0">
                <span class="material-symbols-outlined text-[20px]">filter_list</span>
                Filters
            </button>
        </div>
    </div>

    <!-- Results Grid -->
    @if($variants->isEmpty())
        <div class="bg-surface-container-lowest rounded-3xl p-12 shadow-sm text-center border-2 border-dashed border-slate-200 flex flex-col items-center justify-center">
            <span class="material-symbols-outlined text-6xl text-slate-300 mb-4">inventory_2</span>
            <h3 class="text-xl font-black text-slate-700 mb-2">No Items Found</h3>
            <p class="text-slate-500 max-w-sm mx-auto">We couldn't find any items matching your search criteria. Try adjusting your filters or search term.</p>
            @if(!empty($search) || !empty($brandFilter))
                <button wire:click="$set('search', ''); $set('brandFilter', '');" class="mt-6 px-6 py-2 bg-primary/10 text-primary font-bold rounded-xl hover:bg-primary/20 transition-colors">Clear Filters</button>
            @endif
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-6 mb-8">
            @foreach($variants as $variant)
                <div class="bg-surface-container-lowest rounded-3xl overflow-hidden shadow-sm hover:shadow-md hover:-translate-y-1 transition-all duration-300 border border-slate-100 flex flex-col group relative {{ $variant->bins->sum('current_qty') <= 0 ? 'grayscale-[0.5] opacity-80' : '' }}">
                    <!-- Quick Actions Overlay -->
                    <div class="absolute top-4 right-4 z-10 flex flex-col gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        <a href="{{ route('items.edit', $variant->id) }}" class="w-10 h-10 bg-white rounded-full shadow-lg flex items-center justify-center text-slate-600 hover:text-primary hover:bg-primary/5 transition-colors cursor-pointer">
                            <span class="material-symbols-outlined text-[20px]">edit</span>
                        </a>
                        <!-- Future: Quick scan actions could be linked here -->
                    </div>

                    <!-- Image Header -->
                    <a href="{{ route('items.show', $variant->id) }}" class="aspect-video bg-slate-100 relative block overflow-hidden">
                        @php
                            $primaryImage = $variant->images->firstWhere('is_primary', true);
                            $imagePath = $primaryImage ? asset('storage/' . $primaryImage->path) : asset('images/placeholders/item.svg');
                            $stock = $variant->bins->sum('current_qty');
                        @endphp
                        <img src="{{ $imagePath }}" alt="{{ $variant->item->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        
                        <div class="absolute bottom-3 right-3">
                            <span class="px-3 py-1 bg-surface/90 backdrop-blur-md rounded-lg shadow-sm font-black text-sm {{ $stock > 0 ? 'text-emerald-600' : 'text-red-500' }}">
                                {{ number_format($stock) }} {{ $variant->unit ?? 'PCS' }}
                            </span>
                        </div>
                    </a>

                    <!-- Content -->
                    <a href="{{ route('items.show', $variant->id) }}" class="p-5 flex flex-col flex-1 block group-hover:bg-slate-50/50 transition-colors">
                        <div class="flex justify-between items-start mb-1">
                            <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ $variant->brand ?? 'No Brand' }}</span>
                            @if($variant->primaryBarcode)
                                <div class="flex items-center gap-1 text-slate-400" title="Barcode: {{ $variant->primaryBarcode->barcode }}">
                                    <span class="material-symbols-outlined text-[14px]">barcode</span>
                                </div>
                            @endif
                        </div>
                        
                        <h3 class="font-black text-lg text-on-surface leading-tight mb-3 line-clamp-2">{{ $variant->item->name }}</h3>
                        
                        <div class="mt-auto space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-slate-500 font-bold">ERP</span>
                                <span class="font-mono text-xs font-black bg-slate-100 px-2 py-0.5 rounded text-slate-700">{{ $variant->erp_code ?? '-' }}</span>
                            </div>
                            
                            @if($variant->suppliers->isNotEmpty())
                                <div class="flex items-center gap-2 pt-3 border-t border-slate-100">
                                    <span class="material-symbols-outlined text-[16px] text-slate-400">inventory_2</span>
                                    <span class="text-xs text-slate-500 font-bold truncate">
                                        {{ $variant->suppliers->pluck('name')->join(', ') }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="mt-8">
            {{ $variants->links(data: ['scrollTo' => false]) }}
        </div>
    @endif
</div>
