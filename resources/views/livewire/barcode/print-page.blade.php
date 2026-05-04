<div class="pt-24 px-4 pb-12 lg:px-12 flex flex-col min-h-screen">
    <!-- Header -->
    <header class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-4">
        <div>
            <h1 class="text-3xl font-black tracking-tight text-slate-900">Label Station</h1>
            <p class="text-slate-500 mt-1 font-medium">Configure and output industrial asset identities</p>
        </div>
        
        <div class="relative w-full md:w-96 z-50">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold">🔍</span>
            <input wire:model.live.debounce.300ms="searchString" type="text" 
                class="w-full bg-white border border-slate-200 rounded-full py-3 pl-12 pr-4 shadow-sm focus:ring-2 focus:ring-blue-500 font-bold text-sm outline-none transition-all" 
                placeholder="Search item name or ERP code...">
            
            @if(strlen($searchString) > 1)
            <div class="absolute mt-2 w-full bg-white border border-slate-200 rounded-2xl shadow-xl overflow-hidden flex flex-col">
                @forelse($searchResults as $item)
                    <button wire:click="selectItem({{ $item->id }})" class="text-left px-4 py-3 hover:bg-slate-50 border-b border-slate-100 last:border-0 transition-colors">
                        <div class="font-bold text-slate-800">{{ $item->item->name }}</div>
                        <div class="text-xs font-mono text-slate-500 mt-1">{{ $item->erp_code ?? 'NO-ERP' }}</div>
                    </button>
                @empty
                    <div class="px-4 py-3 text-sm text-slate-500">No items found matching "{{ $searchString }}"</div>
                @endforelse
            </div>
            @endif
        </div>
    </header>

    @if($this->selectedVariant)
    <div class="grid grid-cols-12 gap-8 items-start">
        <!-- Left: Configuration Panels -->
        <div class="col-span-12 lg:col-span-4 space-y-6">
            
            <!-- Output Configuration -->
            <section class="bg-white p-6 rounded-[2rem] border border-slate-200 shadow-sm">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest flex items-center gap-2">
                        <span class="text-sm">⚙️</span> 
                        Output Parameters
                    </h3>
                    <button wire:click="saveSettingsAsDefault" class="text-[10px] font-black text-blue-600 hover:text-blue-700 transition-colors uppercase tracking-tighter">
                        Save as Default
                    </button>
                </div>
                
                <div class="space-y-5">
                    <!-- Label Type -->
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-600 ml-1">Label Geometry</label>
                        <select wire:model.live="labelType" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none">
                            <option value="ITEM_LABEL">Item Label (30x50 mm)</option>
                            <option value="BIN_LABEL">Bin Label (80x50 mm)</option>
                        </select>
                    </div>

                    <!-- Printer Type -->
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-600 ml-1">Printer Hub</label>
                        <select wire:model.live="printerType" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none">
                            <option value="EPSON">Epson L120 (A4 / Browser Print)</option>
                            <option value="TSC">TSC TE200 (Direct Thermal / TSPL)</option>
                        </select>
                    </div>

                    <!-- Conditional: Bin Code -->
                    @if($labelType === 'BIN_LABEL')
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-600 ml-1">Bin Location Code</label>
                        <input wire:model.live="binCode" type="text" class="w-full bg-blue-50 border border-blue-100 rounded-xl px-4 py-3 text-sm font-mono font-bold focus:ring-2 focus:ring-blue-500 outline-none" placeholder="e.g. A-01-01">
                        @error('binCode') <span class="text-[10px] text-red-500 font-bold ml-1">{{ $message }}</span> @enderror
                    </div>
                    @endif

                    <!-- Conditional: Printer IP -->
                    @if($printerType === 'TSC')
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-600 ml-1">Printer Network IPv4</label>
                        <input wire:model.live="printerIp" type="text" class="w-full bg-amber-50 border border-amber-100 rounded-xl px-4 py-3 text-sm font-mono font-bold focus:ring-2 focus:ring-amber-500 outline-none" placeholder="192.168.1.100">
                        @error('printerIp') <span class="text-[10px] text-red-500 font-bold ml-1">{{ $message }}</span> @enderror
                    </div>
                    @endif

                    <!-- Copies -->
                    <div class="bg-slate-50 p-4 rounded-xl flex justify-between items-center">
                        <div>
                            <span class="block text-[10px] uppercase font-bold text-slate-400">Copies</span>
                            <span class="text-lg font-black">{{ str_pad($copies, 2, '0', STR_PAD_LEFT) }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button wire:click="decrementCopies" class="w-8 h-8 flex items-center justify-center bg-white border border-slate-200 rounded-lg hover:bg-slate-100 active:scale-95 transition-all font-bold">
                                -
                            </button>
                            <button wire:click="incrementCopies" class="w-8 h-8 flex items-center justify-center bg-white border border-slate-200 rounded-lg hover:bg-slate-100 active:scale-95 transition-all font-bold">
                                +
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Feedback Notifications -->
            @if($flashMessage)
                <div class="p-4 rounded-2xl {{ $flashType === 'success' ? 'bg-green-50 text-green-700 border border-green-100' : 'bg-red-50 text-red-700 border border-red-100' }} text-sm font-bold flex items-center gap-3">
                    <span>{{ $flashType === 'success' ? '✅' : '❌' }}</span>
                    {{ $flashMessage }}
                </div>
            @endif

            @if(!empty($validationErrors))
                <div class="p-4 rounded-2xl bg-red-50 text-red-700 border border-red-100 text-xs font-bold space-y-1">
                    @foreach($validationErrors as $error)
                        <div class="flex items-center gap-2">
                            <span>⚠️</span>
                            {{ $error }}
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Right: Live Preview Panel -->
        <div class="col-span-12 lg:col-span-8 space-y-6">
            <div class="bg-white rounded-[3rem] p-8 lg:p-16 border border-slate-200 shadow-sm flex flex-col items-center">
                <div class="mb-8 text-center">
                    <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-[10px] font-black uppercase tracking-tighter">Live WYSIWYG Preview</span>
                </div>

                <!-- Realistic Preview Container -->
                <div class="bg-slate-50 border-8 border-slate-200 p-8 rounded-3xl overflow-auto flex justify-center items-center w-full min-h-[400px]">
                    <div class="shadow-2xl bg-white origin-center transform scale-100 transition-transform">
                        {!! $this->previewHtml !!}
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-12 flex flex-wrap justify-center gap-4">
                    <button wire:click="print" wire:loading.attr="disabled" 
                        class="px-10 py-5 bg-slate-900 text-white rounded-full font-black text-sm shadow-2xl hover:bg-black active:scale-95 transition-all flex items-center gap-3 disabled:opacity-50">
                        <span wire:loading.remove wire:target="print">🖨️</span>
                        <div wire:loading wire:target="print" class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                        <span>EXECUTE PRODUCTION</span>
                    </button>
                    
                    <button wire:click="clearItem" class="px-10 py-5 bg-slate-100 text-slate-600 rounded-full font-bold text-sm hover:bg-slate-200 active:scale-95 transition-all flex items-center gap-3">
                        <span>✖️</span>
                        <span>CANCEL</span>
                    </button>
                </div>
            </div>

            <!-- Technical Summary -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white p-5 rounded-3xl border border-slate-100">
                    <span class="block text-[10px] font-black text-slate-400 uppercase mb-2">Item Identity</span>
                    <div class="font-bold text-slate-800">{{ $this->selectedVariant->item->name }}</div>
                    <div class="text-[10px] font-mono text-slate-500">{{ $this->selectedVariant->erp_code }}</div>
                </div>
                <div class="bg-white p-5 rounded-3xl border border-slate-100">
                    <span class="block text-[10px] font-black text-slate-400 uppercase mb-2">Inventory Logic</span>
                    <div class="font-bold text-slate-800">Last In: {{ $this->lastStockInDate }}</div>
                    <div class="text-[10px] text-slate-500">Type: {{ $this->selectedVariant->unit ?? 'PCS' }}</div>
                </div>
                <div class="bg-white p-5 rounded-3xl border border-slate-100">
                    <span class="block text-[10px] font-black text-slate-400 uppercase mb-2">Output Mode</span>
                    <div class="font-bold text-blue-600">{{ $printerType }}</div>
                    <div class="text-[10px] text-slate-500">{{ $labelType }} Mode</div>
                </div>
            </div>
        </div>
    </div>
    @else
    <!-- Empty State -->
    <div class="flex-1 flex flex-col items-center justify-center p-20 text-center bg-white rounded-[4rem] border-2 border-dashed border-slate-100">
        <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mb-8">
            <span class="text-6xl text-slate-200 opacity-50">🖨️</span>
        </div>
        <h2 class="text-3xl font-black text-slate-900 mb-3">System Ready</h2>
        <p class="text-slate-500 max-w-md mx-auto leading-relaxed">Please search and select an asset to begin high-fidelity label production. Ensure the target printer hub is online before execution.</p>
    </div>
    @endif

    <!-- Script for Browser Print Handling -->
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('open-print-window', (event) => {
                const printWindow = window.open('', '_blank');
                printWindow.document.write(event.html);
                printWindow.document.close();
                printWindow.focus();
                
                printWindow.onload = function() {
                    printWindow.print();
                    printWindow.close();
                };
            });
        });
    </script>
</div>
