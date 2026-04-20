<div class="h-full lg:h-[calc(100vh-64px)] overflow-y-auto lg:overflow-hidden bg-surface" x-data>
    <style>
        /* Precision M3 Color Palette from example */
        :root {
            --outline: #737685;
            --surface-container-highest: #e1e2e4;
            --on-background: #191c1e;
            --inverse-primary: #b2c5ff;
            --on-surface-variant: #434654;
            --on-primary-fixed: #001848;
            --surface-dim: #d9dadc;
            --secondary-container: #b6c8fe;
            --surface-container: #edeef0;
            --on-tertiary-fixed-variant: #812800;
            --on-secondary-container: #415382;
            --surface-container-lowest: #ffffff;
            --on-primary: #ffffff;
            --surface: #f8f9fb;
            --secondary: #4c5d8d;
            --on-secondary-fixed: #021945;
            --error-container: #ffdad6;
            --secondary-fixed: #dae2ff;
            --on-error-container: #93000a;
            --inverse-surface: #2e3132;
            --tertiary-fixed: #ffdbcf;
            --secondary-fixed-dim: #b4c5fb;
            --primary-fixed: #dae2ff;
            --primary-container: #0052cc;
            --on-tertiary-container: #ffc6b2;
            --on-surface: #191c1e;
            --primary: #003d9b;
            --tertiary-fixed-dim: #ffb59b;
            --tertiary-container: #a33500;
            --inverse-on-surface: #f0f1f3;
            --surface-bright: #f8f9fb;
            --on-error: #ffffff;
            --background: #f8f9fb;
            --on-secondary-fixed-variant: #344573;
            --outline-variant: #c3c6d6;
            --on-tertiary: #ffffff;
            --on-secondary: #ffffff;
            --surface-variant: #e1e2e4;
            --primary-fixed-dim: #b2c5ff;
            --on-tertiary-fixed: #380d00;
            --surface-container-low: #f3f4f6;
            --surface-container-high: #e7e8ea;
            --on-primary-fixed-variant: #0040a2;
            --error: #ba1a1a;
            --tertiary: #7b2600;
            --surface-tint: #0c56d0;
            --on-primary-container: #c4d2ff;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        /* Mobile specific adjustments */
        @media (max-width: 1024px) {
            .mobile-stack {
                grid-template-columns: 1fr !important;
                height: auto !important;
                overflow-y: visible !important;
                padding-bottom: 100px !important;
            }
            .mobile-column {
                grid-column: span 12 / span 12 !important;
            }
        }

        .dashboard-main {
            padding: 2rem;
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 2rem;
        }

        /* Scrollbar styling for a cleaner look */
        .custom-scroll::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scroll::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
        }
        .custom-scroll::-webkit-scrollbar-thumb {
            background: rgba(0, 61, 155, 0.2);
            border-radius: 10px;
        }
    </style>

    <main class="dashboard-main mobile-stack h-full">
        <!-- Message Flash -->
        @if (session()->has('message'))
            <div class="col-span-12 bg-emerald-500 text-white p-4 rounded-2xl font-bold flex items-center justify-between shadow-lg mb-4">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined">check_circle</span>
                    {{ session('message') }}
                </div>
                <button @click="$el.parentElement.remove()" class="material-symbols-outlined">close</button>
            </div>
        @endif

        <!-- Left Column: Bin Scanning -->
        <section class="col-span-12 lg:col-span-4 flex flex-col gap-6 mobile-column">
            <div class="bg-surface-container-low rounded-2xl p-8 flex flex-col h-full border-l-8 border-primary shadow-sm">
                <div class="mb-10">
                    <span class="text-xs font-black text-primary tracking-[0.2em] uppercase mb-1 block">Step 01</span>
                    <h2 class="text-3xl font-black text-on-surface leading-tight tracking-tighter">Inventory Audit</h2>
                    <p class="text-on-surface-variant text-sm mt-3 leading-relaxed font-medium">Scan any <strong>Bin Label</strong> or <strong>Product Barcode</strong> to start counting.</p>
                </div>
                
                <div class="space-y-8">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 mb-2 uppercase tracking-widest">Scanner Input</label>
                        <div class="relative group flex items-center">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-primary material-symbols-outlined transition-transform group-focus-within:scale-110" style="font-variation-settings: 'FILL' 1;">qr_code_2</span>
                            <input wire:model.live.debounce.500ms="binScan" 
                                   autofocus
                                   id="scanner-input"
                                   class="w-full bg-white border-2 border-slate-100 rounded-2xl pl-14 pr-24 py-5 font-black text-xl text-primary focus:ring-4 focus:ring-primary/5 focus:border-primary transition-all shadow-sm" 
                                   placeholder="Scan Barcode..." 
                                   type="text"
                                   x-on:keydown.enter="$wire.handleScan($el.value); $el.value = ''"/>
                            <div class="absolute right-3 flex items-center gap-2">
                                <button onclick="startScanner()" type="button"
                                        class="bg-slate-100 border border-slate-200 text-slate-600 w-12 h-12 rounded-xl shadow-sm flex items-center justify-center hover:bg-primary/10 hover:text-primary active:scale-95 transition-all outline-none">
                                    <span class="material-symbols-outlined text-2xl" style="font-variation-settings: 'FILL' 1;">photo_camera</span>
                                </button>
                            </div>
                        </div>
                        @error('binScan') <span class="text-error text-xs font-bold mt-2 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Camera Scanner Container -->
                <div id="scanner-container" class="hidden fixed inset-0 z-[100] bg-black/90 backdrop-blur-lg flex flex-col items-center justify-center p-6" wire:ignore>
                    <div class="w-full max-w-lg bg-surface-container-lowest rounded-3xl overflow-hidden shadow-2xl relative border-4 border-white/20">
                        <div id="reader" style="width: 100%;"></div>
                        <div class="p-6 text-center">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4">Position barcode within the frame</p>
                            <button type="button" onclick="stopScanner()"
                                    class="w-full py-4 bg-error text-white font-black text-sm uppercase tracking-widest rounded-2xl shadow-xl shadow-error/20 hover:bg-red-600 transition-all">
                                Cancel Scan
                            </button>
                        </div>
                    </div>
                </div>

                @if($isScanning)
                <div class="mt-10 animate-pulse flex flex-col items-center justify-center p-10 border-2 border-dashed border-slate-200 rounded-3xl">
                    <span class="material-symbols-outlined text-6xl text-slate-200 mb-4">barcode_scanner</span>
                    <p class="text-slate-400 font-bold text-sm tracking-widest uppercase text-center">Waiting for scan...</p>
                </div>
                @endif

                @if($isSelectingBin)
                <div class="mt-10 space-y-4">
                    <div class="flex items-center gap-2 text-amber-600 mb-2">
                        <span class="material-symbols-outlined">warning</span>
                        <span class="text-xs font-black uppercase tracking-widest">Multi-Location Detected</span>
                    </div>
                    <p class="text-[11px] text-slate-500 font-bold leading-tight mb-4">This product is stored in multiple bins. Please select the specific bin you are currently counting:</p>
                    
                    <div class="space-y-3 max-h-60 overflow-y-auto pr-2 custom-scroll">
                        @foreach($candidateBins as $bin)
                        <button wire:click="selectBin({{ $bin->id }})" 
                                class="w-full p-4 bg-white border-2 border-slate-100 rounded-2xl flex items-center justify-between hover:border-primary hover:bg-primary/5 transition-all group">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-primary">location_on</span>
                                <span class="font-black text-on-surface uppercase">{{ $bin->code }}</span>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] text-slate-400 font-black uppercase">Qty</p>
                                <p class="text-primary font-black">{{ $bin->current_qty }}</p>
                            </div>
                        </button>
                        @endforeach
                    </div>
                </div>
                @endif

                <div class="mt-auto pt-10 space-y-4">
                    <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-widest">
                        <span class="text-slate-400">Scanner Readiness</span>
                        <span class="text-emerald-500">Operational</span>
                    </div>
                    <div class="w-full bg-slate-200 h-2 rounded-full overflow-hidden">
                        <div class="bg-emerald-500 w-full h-full"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Center Column: Active Item Count -->
        <section class="col-span-12 lg:col-span-8 flex flex-col gap-6 mobile-column">
            @if($selectedBin)
            <div class="bg-white rounded-3xl shadow-2xl shadow-slate-200/50 flex flex-col h-full overflow-hidden border border-slate-100 min-h-[600px] animate-in slide-in-from-right duration-500">
                <!-- Item Image Area -->
                <div class="h-64 relative bg-slate-100 overflow-hidden group border-b-2 border-slate-50">
                    <img alt="{{ $selectedBin->itemVariant->item->name }}" 
                         class="w-full h-full object-cover transition-transform duration-1000 group-hover:scale-110" 
                         src="{{ !empty($selectedBin->itemVariant->images) && count($selectedBin->itemVariant->images) > 0 ? asset('storage/' . $selectedBin->itemVariant->images[0]->path) : asset('assets/images/no-photo.png') }}"/>
                    
                    <div class="absolute bottom-0 left-0 right-0 p-8 bg-gradient-to-t from-black/80 via-black/40 to-transparent">
                        <div class="flex justify-between items-end">
                            <div class="max-w-[70%]">
                                <span class="text-[10px] font-black text-blue-300 uppercase tracking-[0.2em] px-3 py-1 bg-white/10 backdrop-blur-md border border-white/20 rounded-full mb-3 inline-block">
                                    SKU: {{ $selectedBin->itemVariant->sku ?? 'NO SKU' }} | BIN: {{ $selectedBin->code }}
                                </span>
                                <h3 class="text-white text-3xl font-black tracking-tight leading-tight uppercase">{{ $selectedBin->itemVariant->item->name }}</h3>
                            </div>
                            <div class="text-right">
                                <p class="text-blue-200 text-[10px] uppercase font-black tracking-widest mb-1">System Qty</p>
                                <p class="text-white text-5xl font-black leading-none tracking-tighter">{{ $systemQty }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Interaction Area -->
                <div class="p-10 flex-1 flex flex-col justify-center items-center gap-12">
                    <div class="w-full text-center">
                        <label class="text-xs font-black text-slate-400 uppercase tracking-[0.3em] mb-6 block">Actual Physical Count</label>
                        <div class="flex items-center justify-center gap-10">
                            <button wire:click="decrementQty" 
                                    class="w-24 h-24 rounded-3xl bg-surface-container-high text-on-surface hover:bg-slate-200 active:scale-90 transition-all flex items-center justify-center shadow-lg border-b-4 border-slate-300">
                                <span class="material-symbols-outlined text-4xl font-bold">remove</span>
                            </button>
                            <input wire:model.live.debounce.300ms="actualQty" 
                                   class="w-48 text-center text-[10rem] font-black text-primary border-none focus:ring-0 p-0 leading-none bg-transparent" 
                                   type="number"/>
                            <button wire:click="incrementQty" 
                                    class="w-24 h-24 rounded-3xl bg-surface-container-high text-on-surface hover:bg-slate-200 active:scale-90 transition-all flex items-center justify-center shadow-lg border-b-4 border-slate-300">
                                <span class="material-symbols-outlined text-4xl font-bold">add</span>
                            </button>
                        </div>
                    </div>

                    @if($difference !== 0)
                    <div class="flex gap-10 items-center justify-center bg-amber-50 px-10 py-4 rounded-3xl border border-amber-100">
                        <div class="text-center">
                            <p class="text-[10px] font-black text-amber-600 uppercase tracking-widest">Difference</p>
                            <p class="text-3xl font-black text-amber-700">{{ $difference > 0 ? '+' : '' }}{{ $difference }}</p>
                        </div>
                        <div class="h-10 w-px bg-amber-200"></div>
                        <p class="text-xs font-bold text-amber-800 leading-tight text-left">
                            <strong>Note:</strong> Saving this will trigger a <br/>stock adjustment transaction.
                        </p>
                    </div>
                    @else
                    <div class="flex gap-4 items-center justify-center bg-emerald-50 px-8 py-3 rounded-full border border-emerald-100 text-emerald-700 font-black uppercase text-xs tracking-widest">
                        <span class="material-symbols-outlined">verified</span>
                        Inventory Matches System
                    </div>
                    @endif
                </div>
                
                <!-- Footer Action Buttons -->
                <div class="p-8 bg-slate-50 flex gap-4 border-t-2 border-slate-100">
                    <button wire:click="resetAudit" class="flex-1 py-6 bg-white border-2 border-slate-200 text-slate-500 font-black text-sm uppercase tracking-widest rounded-3xl hover:bg-slate-100 transition-all flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-xl">close</span>
                        Cancel
                    </button>
                    <button wire:click="saveItem" 
                            class="flex-[2] py-6 bg-gradient-to-br from-primary to-primary-container text-white font-black text-xl uppercase tracking-widest rounded-3xl shadow-xl shadow-primary/20 hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-3 group">
                        FINISH OPNAME
                        <span class="material-symbols-outlined text-3xl font-black transition-transform group-hover:translate-x-2">check_circle</span>
                    </button>
                </div>
            </div>
            @else
            <div class="flex-1 flex flex-col items-center justify-center bg-slate-50 rounded-3xl border-2 border-dashed border-slate-200 p-20 text-center">
                <div class="w-24 h-24 bg-white rounded-full flex items-center justify-center shadow-lg mb-6">
                    <span class="material-symbols-outlined text-slate-300 text-4xl">inventory_2</span>
                </div>
                <h3 class="text-2xl font-black text-slate-300 uppercase tracking-tighter">No Active Audit</h3>
                <p class="text-slate-400 text-sm mt-2 max-w-xs font-bold">Scan a bin or item to begin the physical verification process.</p>
            </div>
            @endif
        </section>
    </main>

    <script>
        (function() {
            var html5QrCode;

            window.startScanner = function() {
                var container = document.getElementById('scanner-container');
                if (container) container.classList.remove('hidden');
                
                if (!html5QrCode) html5QrCode = new Html5Qrcode("reader");
                
                var config = { 
                    fps: 20, 
                    qrbox: { width: 280, height: 180 }, 
                    aspectRatio: 1.0,
                    experimentalFeatures: {
                        useBarCodeDetectorIfSupported: true 
                    },
                    videoConstraints: {
                        facingMode: "environment",
                        width: { min: 640, ideal: 1280 },
                        height: { min: 480, ideal: 720 }
                    }
                };

                html5QrCode.start(
                    { facingMode: "environment" },
                    config,
                    function(decodedText) {
                        window.stopScanner();
                        @this.set('binScan', decodedText);
                        @this.handleScan(decodedText);
                    },
                    function(errorMessage) {}
                ).catch(function(err) {
                    console.error("Camera startup error", err);
                    alert("Could not start camera. Please ensure permissions are granted.");
                    window.stopScanner();
                });
            };

            window.stopScanner = function() {
                var container = document.getElementById('scanner-container');
                if (html5QrCode && html5QrCode.isScanning) {
                    html5QrCode.stop().then(function() {
                        if (container) container.classList.add('hidden');
                    }).catch(function(err) {
                        console.error("Error stopping scanner", err);
                        if (container) container.classList.add('hidden');
                    });
                } else {
                    if (container) container.classList.add('hidden');
                }
            };

            // Auto-focus logic
            document.addEventListener('livewire:initialized', function() {
                window.addEventListener('focus-scanner', function() {
                    setTimeout(function() {
                        var el = document.getElementById('scanner-input');
                        if (el) el.focus();
                    }, 100);
                });
            });
        })();
    </script>
</div>
