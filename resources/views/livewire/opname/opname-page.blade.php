<div class="pt-[52px] px-md pb-32 lg:pb-md flex flex-col min-h-screen bg-slate-50/30" 
     x-data="{
         actualQty: @entangle('actualQty'),
         pulse: false,
         get baseFontSize() {
             const len = String(this.actualQty || '').length;
             if (len <= 3) return '6rem';
             if (len === 4) return '5.4rem';
             if (len === 5) return '4.8rem';
             if (len === 6) return '4.2rem';
             return '3.8rem';
         },
         init() {
             this.$watch('actualQty', (newVal, oldVal) => {
                 if (newVal !== oldVal) {
                     this.pulse = true;
                 }
             });
         }
     }">
    <style>
        .ready-to-scan-glow {
            border-color: #10b981 !important;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15) !important;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        @keyframes count-pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .animate-count-pulse {
            animation: count-pulse 150ms ease-in-out;
        }
        /* Hide HTML5 Up/Down spin buttons */
        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type=number] {
            -moz-appearance: textfield;
        }
        #physical-count-input {
            font-size: calc(var(--base-font-size, 6rem) * 0.7);
        }
        @media (min-width: 1024px) {
            #physical-count-input {
                font-size: var(--base-font-size, 6rem);
            }
        }
    </style>

    <!-- Message Flash -->
    @if (session()->has('message'))
        <div class="max-w-7xl mx-auto w-full mb-xs animate-in slide-in-from-top duration-300">
            <div class="bg-emerald-600 border border-emerald-800 text-white py-2.5 px-4 rounded-md flex items-center justify-between shadow-md">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                    <span class="text-xs font-black uppercase tracking-widest">{{ session('message') }}</span>
                </div>
                <button @click="$el.parentElement.remove()" class="material-symbols-outlined text-white/85 hover:text-white text-md">close</button>
            </div>
        </div>
    @endif

    <main class="max-w-[1600px] mx-auto w-full grid grid-cols-12 gap-md items-start mt-sm">
        
        {{-- ==========================================
             LEFT COLUMN: SCANNING & AUDIT READINESS
             ========================================== --}}
        <section class="col-span-12 lg:col-span-4 space-y-md">
            <div class="bg-white border border-slate-200 dark:border-slate-800 rounded-md p-md shadow-sm">
                
                {{-- Scannable Brand Title --}}
                <div class="mb-sm">
                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">WMS OPERATIONAL GATEKEEPER</span>
                    <h2 class="text-md font-black text-slate-900 uppercase tracking-tight mt-0.5">Physical Audit Terminal</h2>
                </div>

                {{-- Scanner Shell Box --}}
                <div class="space-y-sm">
                    <div>
                        <label class="block text-[9px] font-black text-slate-400 mb-1 uppercase tracking-widest">Target Barcode Input</label>
                        <div class="relative flex items-center">
                            <span class="absolute left-3 text-slate-400 material-symbols-outlined text-sm">barcode_scanner</span>
                            <input wire:model.live.debounce.500ms="binScan" 
                                   autofocus
                                   id="scanner-input"
                                   class="w-full h-11 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md pl-9 pr-14 text-xs font-mono font-black placeholder:text-slate-400 placeholder:font-sans focus:outline-none transition-all ready-to-scan-glow" 
                                   placeholder="READY TO SCAN LOCATION / ITEM..." 
                                   type="text"
                                   x-on:keydown.enter="$wire.handleScan($el.value); $el.value = ''"/>
                            
                            <div class="absolute right-1 flex items-center">
                                <button onclick="startScanner()" type="button"
                                        class="bg-slate-100 hover:bg-slate-200 text-slate-600 w-9 h-9 rounded-md flex items-center justify-center transition-colors active:scale-95">
                                    <span class="material-symbols-outlined text-md" style="font-variation-settings: 'FILL' 1;">photo_camera</span>
                                </button>
                            </div>
                        </div>
                        @error('binScan') 
                            <span class="text-red-600 text-[10px] font-black uppercase mt-1.5 block">⚠️ {{ $message }}</span> 
                        @enderror
                    </div>
                </div>

                {{-- Camera Modal Container --}}
                <div id="scanner-container" class="hidden fixed inset-0 z-[100] bg-black/90 backdrop-blur-md flex flex-col items-center justify-center p-md" wire:ignore>
                    <div class="w-full max-w-[450px] bg-white rounded-md overflow-hidden shadow-2xl relative border border-slate-200">
                        <div id="reader" style="width: 100%;"></div>
                        <div class="p-md text-center bg-slate-50 border-t border-slate-100">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3">Align Barcode within Frame</p>
                            <button type="button" onclick="stopScanner()"
                                    class="w-full h-10 bg-red-600 hover:bg-red-700 text-white font-black text-xs uppercase tracking-widest rounded-md transition-colors shadow-sm">
                                Cancel Camera Scan
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Unified Low-Cognitive Standby List --}}
                @if(!$selectedBin && !$isSelectingBin)
                <div class="mt-md border-t border-slate-100 pt-md space-y-2">
                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Workflow Checklist</span>
                    <ul class="space-y-1.5 text-[10px] text-slate-500 font-bold">
                        <li class="flex items-center gap-2">
                            <span class="w-3.5 h-3.5 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center font-mono text-[8px]">1</span>
                            <span>Scan active Location / Item barcode</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="w-3.5 h-3.5 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center font-mono text-[8px]">2</span>
                            <span>Input actual counts (read from distance)</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="w-3.5 h-3.5 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center font-mono text-[8px]">3</span>
                            <span>Verify variance levels & save adjustment</span>
                        </li>
                    </ul>
                </div>
                @endif

                {{-- Multi-Location Bin Selection Table --}}
                @if($isSelectingBin)
                <div class="mt-md border-t border-slate-100 pt-md space-y-sm">
                    <div class="flex items-center gap-1.5 text-amber-600">
                        <span class="material-symbols-outlined text-sm font-black">warning</span>
                        <span class="text-[9px] font-black uppercase tracking-widest">Multi-Bin Conflict</span>
                    </div>
                    <p class="text-[10px] text-slate-500 font-semibold leading-tight">This item resides in multiple locations. Select target bin below:</p>
                    
                    <div class="space-y-1.5 max-h-[160px] overflow-y-auto pr-1 scrollbar-thin">
                        @foreach($candidateBins as $bin)
                        <button wire:click="selectBin({{ $bin->id }})" 
                                class="w-full p-sm bg-slate-50 border border-slate-200 rounded-md flex items-center justify-between hover:bg-slate-100 hover:border-slate-300 transition-all text-left">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-slate-400 text-xs">location_on</span>
                                <span class="font-black text-slate-800 uppercase text-xs font-mono">{{ $bin->code }}</span>
                            </div>
                            <div class="text-right">
                                <p class="text-[8px] text-slate-400 font-black uppercase">System</p>
                                <p class="text-slate-800 font-black text-xs font-mono">{{ $bin->current_qty }}</p>
                            </div>
                        </button>
                        @endforeach
                    </div>
                </div>
                @endif
                
                {{-- Scanner Connection Metric Footer --}}
                <div class="mt-md pt-sm border-t border-slate-100 flex items-center justify-between text-[9px] font-black uppercase tracking-widest">
                    <div class="flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="text-slate-400">Scanner Engine</span>
                    </div>
                    <span class="text-emerald-600">READY</span>
                </div>

            </div>
        </section>

        {{-- ==========================================
             RIGHT COLUMN: RAPID AUDIT WORKSTATION
             ========================================== --}}
        <section class="col-span-12 lg:col-span-8 flex flex-col lg:min-h-[480px]">
            @if($selectedBin)
            <div class="bg-white border border-slate-200 dark:border-slate-800 rounded-md shadow-sm flex flex-col flex-1 overflow-hidden animate-in fade-in slide-in-from-right-4 duration-200">
                
                {{-- Top Minimal Metadata Strip: Prioritizes Bin, SKU, and Details --}}
                <div class="bg-slate-50 border-b border-slate-100 p-3 lg:p-md flex items-center justify-between gap-md">
                    <div class="flex items-center gap-md">
                        {{-- Tiny square picture stamp (Minimized Showcase) --}}
                        <div class="w-11 h-11 bg-white border border-slate-200 rounded-sm overflow-hidden flex-shrink-0">
                            <img alt="{{ $selectedBin->itemVariant->item->name }}" 
                                 class="w-full h-full object-cover" 
                                 src="{{ !empty($selectedBin->itemVariant->images) && count($selectedBin->itemVariant->images) > 0 ? asset('storage/' . $selectedBin->itemVariant->images[0]->path) : asset('assets/images/no-photo.png') }}"/>
                        </div>
                        
                        <div>
                            <div class="flex flex-wrap items-center gap-sm">
                                <span class="text-xs font-mono font-black bg-slate-200/80 text-slate-850 px-1.5 py-0.5 rounded-sm">BIN: {{ $selectedBin->code }}</span>
                                <span class="text-[10px] font-mono font-bold text-slate-500">SKU: {{ $selectedBin->itemVariant->sku ?? 'NO SKU' }}</span>
                                <span class="text-[10px] font-mono font-bold text-slate-500">ERP: {{ $selectedBin->itemVariant->erp_code ?? '-' }}</span>
                            </div>
                            <h3 class="text-slate-900 text-xs font-black uppercase tracking-tight mt-1">{{ $selectedBin->itemVariant->item->name }}</h3>
                        </div>
                    </div>

                    <div class="text-right flex-shrink-0">
                        <span class="block text-[8px] font-black text-slate-400 uppercase tracking-widest">System Quantity</span>
                        <span class="text-slate-800 font-mono text-lg font-black leading-none">{{ $systemQty }}</span>
                    </div>
                </div>

                {{-- Giant Center Physical Counting Anchor --}}
                <div class="flex-1 flex flex-col justify-center items-center py-4 px-3 lg:p-lg bg-white/50">
                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2 lg:mb-4">Actual Physical Count</span>
                    
                    <div class="flex items-center justify-center gap-2 lg:gap-md">
                        {{-- Forklift-friendly Square Decrement --}}
                        <button type="button" wire:click="decrementQty" @click="pulse = true"
                                class="w-16 h-16 rounded-md bg-slate-900 hover:bg-slate-800 text-white active:scale-95 transition-all flex items-center justify-center shadow-md select-none border border-slate-950">
                            <span class="material-symbols-outlined text-2xl font-black">remove</span>
                        </button>
                        
                        {{-- Massive Monospace Readout --}}
                        <input wire:model.live.debounce.300ms="actualQty" 
                               x-model="actualQty"
                               :style="{ '--base-font-size': baseFontSize }"
                               class="w-[170px] lg:w-[260px] text-center font-mono font-black text-slate-900 border-none focus:ring-0 p-0 leading-none bg-transparent select-all outline-none transition-all duration-150 ease-in-out" 
                               :class="{ 'animate-count-pulse': pulse }"
                               @animationend="pulse = false"
                               type="number"
                               id="physical-count-input"/>
                        
                        {{-- Forklift-friendly Square Increment --}}
                        <button type="button" wire:click="incrementQty" @click="pulse = true"
                                class="w-16 h-16 rounded-md bg-slate-900 hover:bg-slate-800 text-white active:scale-95 transition-all flex items-center justify-center shadow-md select-none border border-slate-950">
                            <span class="material-symbols-outlined text-2xl font-black">add</span>
                        </button>
                    </div>
                </div>
                {{-- Extreme Distance-Visible Variance Alert Strip --}}
                <div class="border-t border-b border-slate-100">
                    @if($difference !== 0)
                        <div class="bg-red-600 text-white px-md py-2 lg:py-3 flex items-center justify-between border-y-4 border-red-800 animate-pulse">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-lg animate-bounce" style="font-variation-settings: 'FILL' 1;">error</span>
                                <span class="text-xs font-black uppercase tracking-wider">⚠️ PHYSICAL VARIANCE DETECTED</span>
                            </div>
                            <div class="flex items-center gap-lg font-mono text-xs font-black">
                                <span>SYSTEM: {{ $systemQty }}</span>
                                <span>PHYSICAL: {{ $actualQty }}</span>
                                <span class="bg-black/25 px-2 py-0.5 rounded">DIFF: {{ $difference > 0 ? '+' : '' }}{{ $difference }}</span>
                            </div>
                        </div>
                    @else
                        <div class="bg-emerald-600 text-white px-md py-2 lg:py-3 flex items-center justify-between border-y-4 border-emerald-800">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-lg" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                <span class="text-xs font-black uppercase tracking-wider">✓ SYSTEM STOCK MATCHES PHYSICAL COUNT</span>
                            </div>
                            <span class="font-mono text-xs font-black bg-black/25 px-2 py-0.5 rounded">MATCH OK</span>
                        </div>
                    @endif
                </div>

                {{-- Reason & Notes Dropdown Panel for Variances --}}
                @if($difference !== 0)
                <div class="p-3 lg:p-md border-b border-slate-100 bg-slate-50/50 space-y-md animate-in slide-in-from-top-4 duration-200">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                        <div>
                            <label for="reasonCode" class="block text-[9px] font-black text-slate-400 mb-1 uppercase tracking-widest">Reason Code <span class="text-red-500">*</span></label>
                            <select wire:model.live="reasonCode" id="reasonCode" class="w-full h-10 bg-white border border-slate-200 dark:border-slate-800 rounded-md px-3 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-slate-400">
                                <option value="">-- SELECT REASON --</option>
                                @foreach($reasons as $reason)
                                    <option value="{{ $reason->code }}">{{ $reason->name }}</option>
                                @endforeach
                            </select>
                            @error('reasonCode')
                                <span class="text-red-600 text-[10px] font-black uppercase mt-1 block">⚠️ {{ $message }}</span>
                            @enderror
                        </div>
                        <div>
                            <label for="notes" class="block text-[9px] font-black text-slate-400 mb-1 uppercase tracking-widest">Notes @if($reasonCode === 'LAINNYA') <span class="text-red-500">*</span> @endif</label>
                            <textarea wire:model.live="notes" id="notes" rows="2" class="w-full bg-white border border-slate-200 dark:border-slate-800 rounded-md p-2 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-slate-400" placeholder="Enter notes or additional description..."></textarea>
                            @error('notes')
                                <span class="text-red-600 text-[10px] font-black uppercase mt-1 block">⚠️ {{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>
                @endif
                
                {{-- Sticky Action Bottom Dock (44px target buttons) --}}
                <div class="p-3 lg:p-md bg-slate-50 flex gap-sm border-t border-slate-200">
                    <button wire:click="resetAudit" 
                            class="flex-1 h-11 bg-white border border-slate-200 text-slate-600 font-black text-xs uppercase tracking-widest rounded-md hover:bg-slate-100 transition-colors flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-sm font-black">close</span>
                        Cancel
                    </button>
                    <button wire:click="saveItem" 
                            wire:loading.attr="disabled"
                            wire:target="saveItem"
                            @if($difference !== 0 && (empty($reasonCode) || ($reasonCode === 'LAINNYA' && empty(trim($notes))))) disabled @endif
                            class="flex-[2] h-11 bg-slate-900 hover:bg-slate-850 disabled:bg-slate-350 disabled:cursor-not-allowed disabled:text-slate-500 text-white font-black text-xs uppercase tracking-widest rounded-md shadow-md active:scale-95 transition-all flex items-center justify-center gap-2 group">
                        <span wire:loading.remove wire:target="saveItem" class="flex items-center gap-2">
                            <span>COMMIT PHYSICAL AUDIT</span>
                            <span class="material-symbols-outlined text-md font-black transition-transform group-hover:translate-x-1.5">arrow_forward</span>
                        </span>
                        <span wire:loading wire:target="saveItem" class="flex items-center gap-1.5">
                            <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Processing...</span>
                        </span>
                    </button>
                </div>

            </div>
            @else
            {{-- Integrated Idle Operational Canvas --}}
            <div class="flex-1 flex flex-col md:flex-row items-center justify-center gap-md bg-slate-50/50 rounded-md border-2 border-dashed border-slate-200 p-lg min-h-[480px] text-center md:text-left">
                <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center border border-slate-200 shadow-sm flex-shrink-0">
                    <span class="material-symbols-outlined text-slate-400 text-lg">barcode_scanner</span>
                </div>
                <div class="max-w-[500px]">
                    <h3 class="text-xs font-black text-slate-500 uppercase tracking-widest">Audit Workstation Idle</h3>
                    <p class="text-slate-400 text-[10.5px] mt-1 leading-relaxed font-bold">Terminal is awaiting scanner triggers. Scan an identity barcode to begin physical adjustment operations.</p>
                </div>
            </div>
            @endif
        </section>

    </main>

    <script>
        // ── Opname Camera Scanner ────────────────────────────────────────────
        // Delegates to PeroniksCameraScanner (public/assets/js/peroniksscanner.js)
        window.startScanner = function () {
            window.preventScannerRefocus = true;
            if (document.activeElement instanceof HTMLElement) {
                document.activeElement.blur();
            }
            var input = document.getElementById('scanner-input');
            if (input) input.blur();

            PeroniksCameraScanner.start({
                readerId:    'reader',
                containerId: 'scanner-container',
                onSuccess: function (decodedText) {
                    @this.set('binScan', decodedText);
                    @this.handleScan(decodedText);
                }
            });
        };

        window.stopScanner = function () {
            PeroniksCameraScanner.stop();
        };

        // Auto-focus handler for continuous scanning rhythm
        document.addEventListener('livewire:initialized', function () {
            var input = document.getElementById('scanner-input');
            if (input) {
                input.addEventListener('focus', function () {
                    window.preventScannerRefocus = false;
                });
            }
            window.addEventListener('focus-scanner', function () {
                if (window.preventScannerRefocus) return;
                setTimeout(function () {
                    var el = document.getElementById('scanner-input');
                    if (el) { el.focus(); el.select(); }
                }, 100);
            });
        });
    </script>
</div>
