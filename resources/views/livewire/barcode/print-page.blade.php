<div class="pt-[52px] px-md pb-md flex flex-col min-h-screen bg-slate-50/30" x-data>
    <style>
        .ready-to-scan-glow {
            border-color: #10b981 !important;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15) !important;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>

    <!-- Top Operational Command Header -->
    <header class="max-w-[1600px] mx-auto w-full flex flex-col md:flex-row justify-between items-start md:items-center mb-sm gap-sm mt-sm">
        <div>
            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">WMS IDENTITIES TERMINAL</span>
            <h1 class="text-md font-black text-slate-900 uppercase tracking-tight mt-0.5">Label Production Station</h1>
        </div>
        
        {{-- Terminal Search Lookup Strip --}}
        <div class="relative w-full md:w-96 z-[99]">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 material-symbols-outlined text-sm">search</span>
            <input wire:model.live.debounce.300ms="searchString" 
                   type="text" 
                   class="w-full h-11 bg-white border border-slate-200 dark:border-slate-800 rounded-md pl-9 pr-4 text-xs font-mono font-black placeholder:text-slate-400 placeholder:font-sans focus:outline-none focus:border-slate-400 transition-all shadow-sm" 
                   placeholder="SEARCH ERP CODE, SKU OR NAME...">
            
            @if(strlen($searchString) > 1)
            <div class="absolute mt-1.5 w-full bg-white border border-slate-200 rounded-md shadow-xl overflow-hidden flex flex-col z-[100] animate-in fade-in slide-in-from-top-1 duration-150">
                @forelse($searchResults as $item)
                    <button wire:click="selectItem({{ $item->id }})" 
                            class="text-left px-3 py-2 hover:bg-slate-50 border-b border-slate-100 last:border-0 transition-colors flex flex-col">
                        <span class="font-black text-xs text-slate-800 uppercase tracking-tight">{{ $item->item->name }}</span>
                        <span class="text-[9px] font-mono font-bold text-slate-400 mt-0.5">ERP: {{ $item->erp_code ?? 'NO-ERP' }} | SKU: {{ $item->sku ?? 'NO-SKU' }}</span>
                    </button>
                @empty
                    <div class="px-3 py-2.5 text-[10px] text-slate-400 font-bold">No assets found matching "{{ $searchString }}"</div>
                @endforelse
            </div>
            @endif
        </div>
    </header>

    @if($this->selectedVariant)
    {{-- True Workstation Split Columns --}}
    <div class="max-w-[1600px] mx-auto w-full grid grid-cols-12 gap-md items-start">
        
        {{-- ==========================================
             LEFT COLUMN: CONFIGURATION PANEL
             ========================================== --}}
        <section class="col-span-12 lg:col-span-4 space-y-md">
            
            <div class="bg-white border border-slate-200 dark:border-slate-800 p-md rounded-md shadow-sm">
                
                <div class="flex justify-between items-center mb-sm border-b border-slate-100 pb-sm">
                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1">
                        <span class="material-symbols-outlined text-xs">tune</span>
                        Output Parameters
                    </span>
                    <button wire:click="saveSettingsAsDefault" 
                            class="text-[9px] font-black text-blue-600 hover:text-blue-700 transition-colors uppercase tracking-tighter">
                        Save as Default
                    </button>
                </div>
                
                <div class="space-y-sm">
                    <!-- Label Geometry Select -->
                    <div class="space-y-1">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block ml-1">Label Size</label>
                        <select wire:model.live="labelType" 
                                class="w-full h-9 bg-slate-50 border border-slate-200 dark:border-slate-850 rounded-md px-2.5 text-xs font-bold focus:outline-none focus:border-slate-400">
                            <option value="ITEM_LABEL">Item Label (30x50 mm)</option>
                            <option value="BIN_LABEL">Bin Label (80x50 mm)</option>
                        </select>
                    </div>

                    <!-- Printer Hub Target -->
                    <div class="space-y-1">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block ml-1">Printer Target</label>
                        <select wire:model.live="printerType" 
                                class="w-full h-9 bg-slate-50 border border-slate-200 dark:border-slate-850 rounded-md px-2.5 text-xs font-bold focus:outline-none focus:border-slate-400">
                            <option value="EPSON">Epson L120 (A4 / Browser Print)</option>
                            <option value="TSC">TSC TE200 (Direct Thermal / TSPL)</option>
                        </select>
                    </div>

                    <!-- Conditional Bin Location Input -->
                    @if($labelType === 'BIN_LABEL')
                    <div class="space-y-1">
                        <label class="text-[9px] font-black text-slate-600 uppercase tracking-widest block ml-1">Override Bin Location Code</label>
                        <input wire:model.live="binCode" 
                               type="text" 
                               class="w-full h-9 bg-blue-50/50 border border-blue-100 rounded-md px-3 text-xs font-mono font-black focus:outline-none focus:border-blue-300" 
                               placeholder="e.g. A-01-01">
                        @error('binCode') 
                            <span class="text-[10px] text-red-500 font-bold ml-1 block mt-1">⚠️ {{ $message }}</span> 
                        @enderror
                    </div>
                    @endif



                    <!-- Copies Stepper -->
                    <div class="bg-slate-50 border border-slate-200 p-2 rounded-md flex justify-between items-center h-11">
                        <div>
                            <span class="block text-[8px] uppercase font-black text-slate-400 tracking-widest">Print Copies</span>
                            <span class="text-xs font-mono font-black text-slate-800">{{ str_pad($copies, 2, '0', STR_PAD_LEFT) }}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <button type="button" wire:click="decrementCopies" 
                                    class="w-8 h-8 flex items-center justify-center bg-white border border-slate-200 rounded-md hover:bg-slate-100 active:scale-95 transition-all font-black text-xs select-none">
                                -
                            </button>
                            <button type="button" wire:click="incrementCopies" 
                                    class="w-8 h-8 flex items-center justify-center bg-white border border-slate-200 rounded-md hover:bg-slate-100 active:scale-95 transition-all font-black text-xs select-none">
                                +
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Toast-Style Notification Center inside Config Column -->
            @if($flashMessage)
                <div class="p-sm rounded-md border text-[10px] font-black uppercase tracking-wider flex items-center gap-2 animate-in slide-in-from-top-1 duration-200 {{ $flashType === 'success' ? 'bg-emerald-600 border-emerald-800 text-white shadow-sm' : 'bg-red-600 border-red-800 text-white shadow-sm' }}">
                    <span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1;">{{ $flashType === 'success' ? 'check_circle' : 'error' }}</span>
                    <span>{{ $flashMessage }}</span>
                </div>
            @endif

            @if(!empty($validationErrors))
                <div class="p-sm rounded-md bg-red-65 border-4 border-red-800 text-red-800 text-[10px] font-black uppercase space-y-1 shadow-sm">
                    @foreach($validationErrors as $error)
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-xs">warning</span>
                            <span>{{ $error }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ==========================================
             RIGHT COLUMN: LIVE DENSE PREVIEW & ACTIONS
             ========================================== --}}
        <section class="col-span-12 lg:col-span-8 space-y-md">
            
            {{-- Mock Preview Canvas --}}
            <div class="bg-white border border-slate-200 dark:border-slate-800 rounded-md p-md shadow-sm flex flex-col items-center">
                <div class="mb-2 text-center">
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-500 rounded-sm text-[8px] font-black uppercase tracking-widest border border-slate-250">
                        Realistic Thermal Mockup Preview
                    </span>
                </div>

                <!-- realistic preview box (compact rendering) -->
                <div class="bg-slate-50 border border-slate-200 p-sm rounded-md overflow-hidden flex justify-center items-center w-full min-h-[220px]">
                    <div class="shadow-sm bg-white border border-slate-300 origin-center transform scale-[0.85] transition-transform">
                        {!! $this->previewHtml !!}
                    </div>
                </div>

                <!-- Print Executon Action Bar (Dominant emerald) -->
                <div class="mt-md flex flex-wrap justify-center gap-sm w-full border-t border-slate-100 pt-md">
                    <button wire:click="print" wire:loading.attr="disabled" 
                        class="h-11 px-8 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md font-black text-xs uppercase tracking-widest shadow-md hover:brightness-105 active:scale-95 transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:pointer-events-none">
                        <span wire:loading.remove wire:target="print" class="material-symbols-outlined text-sm font-black" style="font-variation-settings: 'FILL' 1;">print</span>
                        <div wire:loading wire:target="print" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                        <span>EXECUTE PRODUCTION</span>
                    </button>
                    
                    <button wire:click="clearItem" 
                            class="h-11 px-6 bg-slate-100 hover:bg-slate-200 text-slate-600 border border-slate-200 rounded-md font-black text-xs uppercase tracking-widest active:scale-95 transition-colors flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm font-black">close</span>
                        <span>Cancel Selection</span>
                    </button>
                </div>
            </div>

            <!-- Dense Technical Summary Strip -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-sm">
                <div class="bg-slate-50 p-sm rounded-md border border-slate-200">
                    <span class="block text-[8px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Asset SKU</span>
                    <div class="font-black text-slate-800 text-[11px] font-mono tracking-tighter leading-tight">{{ $this->selectedVariant->sku ?? '-' }}</div>
                    <div class="text-[9px] font-mono font-bold text-slate-400 mt-0.5">ERP: {{ $this->selectedVariant->erp_code ?? '-' }}</div>
                </div>
                <div class="bg-slate-50 p-sm rounded-md border border-slate-200">
                    <span class="block text-[8px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Stock Receipt</span>
                    <div class="font-black text-slate-800 text-[11px] leading-tight">Last Inbound: {{ $this->lastStockInDate }}</div>
                    <div class="text-[9px] text-slate-400 font-bold mt-0.5 uppercase">Unit: {{ $this->selectedVariant->unit ?? 'PCS' }}</div>
                </div>
                <div class="bg-slate-50 p-sm rounded-md border border-slate-200">
                    <span class="block text-[8px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Active Setup</span>
                    <div class="font-black text-blue-600 text-[11px] uppercase tracking-tighter leading-tight">{{ $printerType }}</div>
                    <div class="text-[9px] text-slate-400 font-bold mt-0.5 uppercase">{{ $labelType }} Output</div>
                </div>
            </div>
        </section>
    </div>
    @else
    {{-- Dense Standby State --}}
    <div class="max-w-[1600px] mx-auto w-full flex-1 flex flex-col md:flex-row items-center justify-center gap-md p-lg bg-slate-50/50 border-2 border-dashed border-slate-200 rounded-md min-h-[420px] text-center md:text-left">
        <div class="w-12 h-12 bg-white border border-slate-200 rounded-full flex items-center justify-center shadow-sm flex-shrink-0">
            <span class="material-symbols-outlined text-slate-450 text-lg">print</span>
        </div>
        <div class="max-w-[500px]">
            <h2 class="text-xs font-black text-slate-500 uppercase tracking-widest">Station Standby</h2>
            <p class="text-slate-450 text-[10.5px] mt-1 leading-relaxed font-bold">Please lookup an item via the top command search bar to initialize. Make sure the thermal printing hub is online.</p>
        </div>
    </div>
    @endif

    {{-- Browser Printing JS Trigger --}}
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
