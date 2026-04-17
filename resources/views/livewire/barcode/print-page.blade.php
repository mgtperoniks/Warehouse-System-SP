<div class="pt-24 px-4 pb-12 lg:px-12 flex flex-col min-h-screen">
<style>
    .barcode-container {
        background: repeating-linear-gradient(
            90deg,
            #191c1e,
            #191c1e 4px,
            transparent 4px,
            transparent 8px,
            #191c1e 8px,
            #191c1e 10px,
            transparent 10px,
            transparent 14px
        );
    }
</style>
    <!-- Header -->
    <header class="flex flex-col md:flex-row justify-between items-start md:items-center mb-12 gap-4">
        <div>
            <h1 class="text-3xl font-black tracking-tight text-on-surface">Label Production</h1>
            <p class="text-on-surface-variant mt-1 font-medium">Verify asset identity before thermal output</p>
        </div>
        
        <!-- Search & Select Item for printing (Actual Logic Added) -->
        <div class="relative w-full md:w-96 z-20">
            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">search</span>
            <input wire:model.live.debounce.300ms="searchString" type="text" class="w-full bg-white border border-slate-200 rounded-full py-3 pl-12 pr-4 shadow-sm focus:ring-2 focus:ring-primary font-bold text-sm" placeholder="Search item to print...">
            
            @if(strlen($searchString) > 2)
            <div class="absolute mt-2 w-full bg-white border border-slate-200 rounded-2xl shadow-xl overflow-hidden flex flex-col">
                @forelse($searchResults as $item)
                    <button wire:click="selectItem({{ $item->id }})" class="text-left px-4 py-3 hover:bg-slate-50 border-b border-slate-100 last:border-0 transition-colors">
                        <div class="font-bold text-slate-800">{{ $item->item->name }}</div>
                        <div class="text-xs font-mono text-slate-500 mt-1">{{ $item->erp_code ?? 'NO-ERP' }}</div>
                    </button>
                @empty
                    <div class="px-4 py-3 text-sm text-slate-500">No items found.</div>
                @endforelse
            </div>
            @endif
        </div>
    </header>

    @if($selectedVariant)
    <!-- Asymmetric Workspace Grid -->
    <div class="grid grid-cols-12 gap-8 items-start">
        <!-- Left Column: Settings & Details (Bento Style) -->
        <div class="col-span-12 lg:col-span-4 space-y-6">
            <!-- Print Configuration Card -->
            <section class="bg-surface-container-low p-6 rounded-[2rem] shadow-sm">
                <h3 class="text-sm font-bold text-outline uppercase tracking-widest mb-6">Print Parameters</h3>
                <div class="space-y-4">
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-on-surface-variant ml-1">Printer Selection</label>
                        <div class="bg-surface-container-lowest p-4 rounded-xl flex justify-between items-center border-l-4 border-primary">
                            <span class="font-medium text-sm">Zebra ZT411 - Bay 04</span>
                            <span class="material-symbols-outlined text-primary text-sm" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-on-surface-variant ml-1">Label Dimension</label>
                        <div class="bg-surface-container-lowest p-4 rounded-xl flex justify-between items-center cursor-pointer hover:bg-white transition-colors">
                            <span class="font-medium text-sm">4.0" x 6.0" Industrial</span>
                            <span class="material-symbols-outlined text-outline text-sm">expand_more</span>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 pt-2">
                        <div class="bg-surface-container-high p-4 rounded-xl">
                            <span class="block text-[10px] uppercase font-bold text-outline mb-1">Copies</span>
                            <div class="flex items-center gap-3">
                                <button wire:click="$set('copies', {{ max(1, $copies - 1) }})" class="p-1 bg-slate-300 rounded hover:bg-primary hover:text-white transition-colors"><span class="material-symbols-outlined text-sm">remove</span></button>
                                <span class="text-xl font-black w-8 text-center">{{ str_pad($copies, 2, '0', STR_PAD_LEFT) }}</span>
                                <button wire:click="$set('copies', {{ $copies + 1 }})" class="p-1 bg-slate-300 rounded hover:bg-primary hover:text-white transition-colors"><span class="material-symbols-outlined text-sm">add</span></button>
                            </div>
                        </div>
                        <div class="bg-surface-container-high p-4 rounded-xl">
                            <span class="block text-[10px] uppercase font-bold text-outline">Density</span>
                            <span class="text-xl font-black mt-1 block">300 DPI</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Metadata Card -->
            <section class="bg-surface-container-low p-6 rounded-[2rem] shadow-sm">
                <h3 class="text-sm font-bold text-outline uppercase tracking-widest mb-4">Item Metadata</h3>
                <div class="space-y-3">
                    <div class="flex justify-between py-2 border-b border-outline-variant/10">
                        <span class="text-xs text-on-surface-variant">Manufacturer</span>
                        <span class="text-xs font-bold">{{ $selectedVariant->brand ?? 'Unknown' }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-outline-variant/10">
                        <span class="text-xs text-on-surface-variant">Unit Type</span>
                        <span class="text-xs font-bold">{{ $selectedVariant->unit ?? 'PCS' }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-outline-variant/10">
                        <span class="text-xs text-on-surface-variant">ERP Code</span>
                        <span class="font-mono text-xs font-bold bg-slate-200 px-1.5 py-0.5 rounded">{{ $selectedVariant->erp_code ?? '-' }}</span>
                    </div>
                </div>
            </section>
        </div>

        <!-- Right Column: Large Label Preview (Primary Focal Point) -->
        <div class="col-span-12 lg:col-span-8 flex flex-col space-y-8">
            <!-- Main Preview Card -->
            <div class="bg-surface-container-lowest rounded-[3rem] p-6 lg:p-12 shadow-sm border border-slate-200 flex flex-col items-center">
                <!-- Label Inner Design (Thermal Look) -->
                <div class="w-full max-w-2xl bg-white aspect-[3/2] border-2 border-slate-200 flex flex-col p-8 select-none relative overflow-hidden shadow-inner">
                    <!-- Technical Sheen Overlay -->
                    <div class="absolute inset-0 bg-gradient-to-br from-transparent via-transparent to-black/5 pointer-events-none"></div>
                    
                    <div class="flex justify-between items-start mb-8 z-10">
                        <div class="space-y-1">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-widest flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">factory</span> Property of Forge WMS
                            </span>
                            <h2 class="text-3xl lg:text-4xl font-black text-slate-900 leading-tight tracking-tight">{{ $selectedVariant->item->name }}</h2>
                            <p class="text-xl font-medium text-slate-600 font-mono tracking-tight">{{ $selectedVariant->sku ?? 'NO-SKU-ASSIGNED' }}</p>
                        </div>
                    </div>

                    <!-- Barcode Area -->
                    <div class="mt-auto flex flex-col items-center w-full z-10">
                        <!-- Simulated Barcode Lines -->
                        <div class="w-full h-32 lg:h-48 barcode-container mb-4 opacity-90 mix-blend-multiply"></div>
                        <div class="text-2xl lg:text-3xl font-mono font-black tracking-[0.4em] text-slate-900">
                            *{{ $selectedVariant->primaryBarcode->barcode ?? 'PENDING-GENERATION' }}*
                        </div>
                    </div>

                    <div class="mt-8 flex justify-between items-end border-t-2 border-slate-900 pt-4 z-10">
                        <div class="flex space-x-6">
                            <div>
                                <span class="block text-[10px] font-black uppercase text-slate-400">Date Logged</span>
                                <span class="text-sm font-bold">{{ now()->format('d M Y') }}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="block text-[10px] font-black uppercase text-slate-400">Security Hash</span>
                            <span class="text-[10px] font-mono font-bold">{{ substr(md5($selectedVariant->id . now()), 0, 10) }}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Label Actions Overlay -->
                <div class="mt-12 flex flex-wrap justify-center gap-4 w-full">
                    <button class="px-8 py-4 bg-primary text-white rounded-full font-black text-sm shadow-xl hover:bg-primary-fixed-variant active:scale-95 transition-all flex items-center gap-3">
                        <span class="material-symbols-outlined text-lg">print</span>
                        <span>SEND TO PRINTER</span>
                    </button>
                    <button class="px-8 py-4 bg-slate-100 text-slate-700 rounded-full font-bold text-sm hover:bg-slate-200 active:scale-95 transition-all flex items-center gap-3 border border-slate-200">
                        <span class="material-symbols-outlined text-lg">picture_as_pdf</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @else
    <!-- Empty State -->
    <div class="flex-1 flex flex-col items-center justify-center p-12 text-center">
        <div class="w-24 h-24 bg-slate-100 rounded-full flex items-center justify-center mb-6">
            <span class="material-symbols-outlined text-5xl text-slate-300">print_disabled</span>
        </div>
        <h2 class="text-2xl font-black text-slate-800 mb-2">No Item Selected</h2>
        <p class="text-slate-500 max-w-md">Use the search bar above to select an item. You can then configure the dimensions, density, and queues before sending labels to the industrial thermal printer.</p>
    </div>
    @endif
</div>
