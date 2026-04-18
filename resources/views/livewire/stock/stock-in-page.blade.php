<div>
    {{-- =============================================
         FLASH / LIVE FEEDBACK TOAST
    ================================================ --}}
    <div x-data="{ show: false, message: '', type: 'success' }"
         x-on:message-dispatched.window="show = true; message = $event.detail.message; type = $event.detail.type; setTimeout(() => show = false, 4000)"
         x-show="show"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="fixed top-20 lg:left-72 right-4 z-[100]"
         style="display: none;">
        <div :class="type === 'success' ? 'bg-green-600 border-green-800' : 'bg-red-600 border-red-800'"
             class="rounded-xl text-white py-3 px-5 flex items-center justify-between shadow-2xl border-b-4">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;" x-text="type === 'success' ? 'check_circle' : 'error'"></span>
                <span class="text-sm font-bold uppercase tracking-widest" x-text="message"></span>
            </div>
            <button @click="show = false" class="material-symbols-outlined text-white/80 hover:text-white text-lg ml-4">close</button>
        </div>
    </div>

    {{-- =============================================
         MAIN CANVAS  (layout shell handles sidebar offset)
    ================================================ --}}
    <div class="pt-16 pb-40 min-h-screen">
        <div class="max-w-[1600px] mx-auto p-6 grid grid-cols-12 gap-6">

            {{-- ── LAST-ACTION BANNER ──────────────────────── --}}
            @if($lastAction)
            <div class="col-span-12">
                <div class="bg-green-100 border-l-4 border-green-600 p-4 flex items-center justify-between rounded-r-lg industrial-shadow">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-green-700" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                        <div>
                            <p class="text-green-800 font-bold tracking-tight text-sm">Last Action Recorded</p>
                            <p class="text-green-700 text-xs font-medium">{{ $lastAction }}</p>
                        </div>
                    </div>
                    <button wire:click="$set('lastAction', '')" class="material-symbols-outlined text-green-700 cursor-pointer hover:bg-green-200 rounded-full p-1 transition-colors text-lg">close</button>
                </div>
            </div>
            @endif

            {{-- ══════════════════════════════════════════════
                 LEFT PANEL  (7 columns)
            ══════════════════════════════════════════════════ --}}
            <div class="col-span-12 lg:col-span-7 space-y-6">

                {{-- ── SCAN BAR ──────────────────────────────── --}}
                <div class="bg-surface-container-low rounded-full p-1 border-2 border-transparent scanning-active transition-all industrial-shadow">
                    <div class="relative flex items-center px-4">
                        <span class="material-symbols-outlined absolute left-6 text-green-600 text-3xl {{ $autoAddMode ? 'animate-pulse' : '' }}">barcode_scanner</span>
                        <input
                            wire:model="barcode"
                            wire:keydown.enter="handleScan"
                            id="barcode-input-stock-in"
                            autofocus
                            class="w-full bg-transparent border-none text-2xl font-bold py-5 pl-16 focus:ring-0 text-on-surface"
                            placeholder="READY TO SCAN..."
                            type="text"/>
                            <div class="flex items-center gap-3 pr-4 shrink-0">
                            {{-- Auto-Add Toggle --}}
                            <div class="flex items-center gap-2 bg-white rounded-lg px-3 py-2 border border-slate-100 shadow-sm">
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Auto</span>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" wire:model.live="autoAddMode" class="sr-only peer">
                                    <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-green-600"></div>
                                </label>
                            </div>
                            <button onclick="startScanner()" type="button"
                                    class="bg-slate-200 text-slate-700 w-12 h-12 shrink-0 rounded-full shadow-lg flex items-center justify-center hover:bg-slate-300 active:scale-95 transition-all outline-none">
                                <span class="material-symbols-outlined text-xl" style="font-variation-settings: 'FILL' 1;">photo_camera</span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- ── Camera Scanner (hidden by default) ── --}}
                <div id="scanner-container" class="hidden bg-black rounded-2xl overflow-hidden relative border-4 border-slate-100 shadow-2xl z-20" wire:ignore>
                    <div id="reader" style="width: 100%;"></div>
                    <div class="absolute bottom-4 left-1/2 -translate-x-1/2 bg-black/60 backdrop-blur-md text-white px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest flex items-center gap-2 z-10 pointer-events-none">
                        <span class="material-symbols-outlined text-sm text-yellow-400">info</span>
                        Move phone slowly for better focus
                    </div>
                    <button type="button" onclick="stopScanner()"
                            class="absolute top-4 right-4 bg-red-500 text-white px-4 py-2 text-sm font-bold rounded-xl shadow-lg z-50 hover:bg-red-600 flex items-center gap-2 transition-all">
                        <span class="material-symbols-outlined text-sm">close</span> Cancel
                    </button>
                </div>

                {{-- ── ITEM AREA  (Found / Not-Found / Waiting) ──── --}}
                @if($currentItem)
                    {{-- FOUND STATE: Detail card + image --}}
                    <div class="grid grid-cols-2 gap-6">
                        {{-- Detail Card --}}
                        <div class="bg-surface-container-lowest rounded-xl p-6 industrial-shadow relative overflow-hidden flex flex-col justify-center border-l-4 border-primary">
                            <div class="absolute top-0 right-0 p-3">
                                <span class="bg-green-100 text-green-700 text-[10px] font-bold px-3 py-1 rounded-full uppercase">Verified</span>
                            </div>
                            <h2 class="text-2xl font-black tracking-tighter text-on-surface mb-1 leading-tight">{{ $currentItem->item->name }}</h2>
                            <p class="text-outline font-mono text-xs tracking-widest uppercase mb-4">ERP: {{ $currentItem->erp_code }}</p>
                            {{-- Supplier --}}
                            <div class="flex flex-col gap-1 mb-5">
                                <label class="text-[10px] font-bold text-outline uppercase tracking-widest">Supplier</label>
                                <div class="relative w-full">
                                    <select wire:model="supplier_id" class="w-full pl-3 pr-10 py-2 bg-surface-container-low border-none rounded-lg font-bold text-on-surface appearance-none focus:ring-2 focus:ring-green-500 text-sm">
                                        <option value="">— Select Supplier —</option>
                                        @foreach($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                        @endforeach
                                    </select>
                                    <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-sm">keyboard_arrow_down</span>
                                </div>
                            </div>
                            {{-- Quick actions --}}
                            <div class="flex gap-2">
                                <button wire:click="generateInternalBarcode" class="flex items-center gap-2 bg-surface-container-high text-on-surface px-3 py-2 rounded-lg font-bold text-xs hover:bg-surface-container-highest transition-all uppercase tracking-wider">
                                    <span class="material-symbols-outlined text-sm">qr_code_2</span> Label
                                </button>
                                <button class="flex items-center gap-2 bg-surface-container-high text-on-surface px-3 py-2 rounded-lg font-bold text-xs hover:bg-surface-container-highest transition-all uppercase tracking-wider">
                                    <span class="material-symbols-outlined text-sm">print</span> Print
                                </button>
                            </div>
                        </div>

                        {{-- Image Card --}}
                        <div class="bg-surface-container-lowest rounded-xl p-0 industrial-shadow overflow-hidden border-l-4 border-primary group relative">
                            <img alt="Product Preview"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                 src="{{ $currentItem->images->where('is_primary', true)->first() ? asset('storage/' . $currentItem->images->where('is_primary', true)->first()->path) : asset('images/placeholders/item.svg') }}"/>
                        </div>
                    </div>

                @elseif($isNewItem)
                    {{-- NOT-FOUND STATE: inline registration form --}}
                    <div class="bg-surface-container-low rounded-xl p-6 industrial-shadow border-2 border-dashed border-outline-variant">
                        <div class="flex items-center gap-4 mb-5 text-outline">
                            <span class="material-symbols-outlined text-3xl text-amber-500" style="font-variation-settings: 'FILL' 1;">error</span>
                            <div>
                                <h3 class="text-lg font-bold text-on-surface">Barcode Not Found</h3>
                                <p class="text-sm font-medium">ID: <span class="bg-amber-100 text-amber-700 px-2 rounded font-mono">{{ $barcode }}</span> — Register manually below.</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-5">
                            <div class="space-y-4">
                                <div>
                                    <label class="text-xs font-bold text-outline uppercase tracking-widest mb-1 block">ERP Code</label>
                                    <input wire:model="erpCode" class="w-full bg-white border-none rounded-lg font-medium p-3 focus:ring-2 focus:ring-green-500 text-sm" placeholder="e.g. 5.10.880.XXX" type="text"/>
                                    @error('erpCode') <p class="text-[10px] text-error font-bold mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-outline uppercase tracking-widest mb-1 block">Item Name</label>
                                    <input wire:model="itemName" class="w-full bg-white border-none rounded-lg font-medium p-3 focus:ring-2 focus:ring-green-500 text-sm" placeholder="Full descriptive name" type="text"/>
                                    @error('itemName') <p class="text-[10px] text-error font-bold mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <div class="flex flex-col justify-end gap-3">
                                <button wire:click="createNewItem" class="w-full bg-green-600 text-white font-bold py-3 rounded-lg hover:bg-green-700 transition-colors uppercase tracking-widest text-sm">Save &amp; Continue</button>
                                <button wire:click="$set('isNewItem', false)" class="w-full bg-surface-container-highest text-on-surface font-bold py-3 rounded-lg hover:bg-slate-300 transition-colors uppercase tracking-widest text-sm">Cancel</button>
                            </div>
                        </div>
                    </div>

                @else
                    {{-- WAITING STATE --}}
                    <div class="rounded-xl border-2 border-dashed border-outline-variant/30 p-12 flex flex-col items-center justify-center opacity-40">
                        <span class="material-symbols-outlined text-7xl text-slate-300">barcode_scanner</span>
                        <p class="text-base font-bold text-slate-400 mt-3 uppercase tracking-widest">Waiting for scan…</p>
                    </div>
                @endif

                {{-- ── QTY & BIN ROW (only when item is loaded) ─── --}}
                @if($currentItem)
                <div class="bg-surface-container-low rounded-xl p-6 industrial-shadow grid grid-cols-2 gap-6 items-center">
                    {{-- Quantity control --}}
                    <div class="space-y-3">
                        <label class="text-xs font-black text-outline uppercase tracking-[0.2em] block text-center">Received Quantity</label>
                        <div class="flex items-center justify-center gap-4">
                            <button wire:click="$set('qty', {{ $qty > 1 ? $qty - 1 : 1 }})"
                                    class="w-14 h-14 rounded-full bg-surface-container-highest flex items-center justify-center hover:bg-slate-300 transition-colors active:scale-90">
                                <span class="material-symbols-outlined text-2xl">remove</span>
                            </button>
                            <span class="text-7xl font-black tracking-tighter text-on-surface w-20 text-center">{{ $qty }}</span>
                            <button wire:click="$set('qty', {{ (int)$qty + 1 }})"
                                    class="w-14 h-14 rounded-full bg-surface-container-highest flex items-center justify-center hover:bg-slate-300 transition-colors active:scale-90">
                                <span class="material-symbols-outlined text-2xl">add</span>
                            </button>
                        </div>
                    </div>

                    {{-- Bin selection --}}
                    <div class="bg-surface-container-lowest p-4 rounded-xl border-2 {{ $errors->has('bin_id') ? 'border-error' : 'border-green-600/30' }}">
                        <label class="text-xs font-black {{ $errors->has('bin_id') ? 'text-error' : 'text-green-700' }} uppercase tracking-widest mb-3 block">Destination Bin</label>
                        <div class="relative flex items-center">
                            <span class="material-symbols-outlined absolute left-3 text-green-600 text-2xl">warehouse</span>
                            <select wire:model="bin_id"
                                    class="w-full pl-12 pr-10 py-4 bg-surface-container-low border-none rounded-xl font-black text-xl text-on-surface appearance-none focus:ring-2 focus:ring-green-500">
                                <option value="">— SELECT BIN —</option>
                                @foreach($bins as $bin)
                                    <option value="{{ $bin->id }}">{{ $bin->code }}</option>
                                @endforeach
                            </select>
                            <span class="material-symbols-outlined absolute right-3 text-slate-400 pointer-events-none">swap_vert</span>
                        </div>
                        @error('bin_id') <p class="text-[10px] text-error font-bold mt-1">{{ $message }}</p> @enderror
                        <p class="text-[10px] text-outline font-bold mt-2 uppercase tracking-tighter">Level 01 • Sector High-Density</p>
                    </div>
                </div>

                {{-- ADD TO BATCH button --}}
                <button wire:click="addToCart"
                        class="w-full green-action-gradient text-white py-5 rounded-xl font-black text-xl tracking-widest uppercase shadow-xl shadow-green-500/20 hover:scale-[1.01] active:scale-[0.98] transition-all flex items-center justify-center gap-3">
                    <span class="material-symbols-outlined text-2xl">add_task</span>
                    ADD TO RECEIVING LIST
                </button>
                @endif
            </div>{{-- /LEFT PANEL --}}

            {{-- ══════════════════════════════════════════════
                 RIGHT PANEL  (5 columns) — Batch Manifest
            ══════════════════════════════════════════════════ --}}
            <div class="col-span-12 lg:col-span-5 bg-surface-container-low rounded-xl p-6 industrial-shadow flex flex-col" style="max-height: calc(100vh - 160px);">
                {{-- Header --}}
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h3 class="text-xl font-black tracking-tight text-on-surface">Receiving Batch</h3>
                        <p class="text-xs text-outline font-medium mt-0.5">Ref: {{ $reference ?: 'B-' . date('Y-md') }}</p>
                    </div>
                    <span class="bg-surface-container-highest px-3 py-1.5 rounded-lg font-bold text-sm text-primary">{{ count($cart) }} ITEMS</span>
                </div>

                {{-- Scrollable list --}}
                <div class="flex-1 overflow-y-auto space-y-3 pr-1 no-scrollbar">
                    @forelse($cart as $key => $item)
                        <div class="bg-surface-container-lowest p-4 rounded-xl industrial-shadow relative border-l-4 border-green-600 hover:translate-x-0.5 transition-transform">
                            <div class="flex justify-between items-start">
                                <div class="flex-1 min-w-0">
                                    <p class="font-black text-sm text-on-surface tracking-tight truncate">{{ $item['name'] }}</p>
                                    <p class="text-[10px] font-medium text-outline uppercase mt-0.5">{{ $item['bin_name'] }}{{ $item['supplier_name'] !== 'N/A' ? ' • ' . $item['supplier_name'] : '' }}</p>
                                </div>
                                <div class="flex items-center gap-3 ml-3 shrink-0">
                                    <div class="text-right">
                                        <p class="text-2xl font-black text-green-700 leading-none">{{ number_format($item['qty']) }}</p>
                                        <p class="text-[9px] font-bold text-outline uppercase tracking-tighter text-right">UNITS</p>
                                    </div>
                                    <button wire:click="removeFromCart('{{ $key }}')" class="material-symbols-outlined text-slate-300 hover:text-error transition-colors text-lg cursor-pointer">delete</button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="h-64 flex flex-col items-center justify-center opacity-30">
                            <span class="material-symbols-outlined text-6xl text-slate-300">move_to_inbox</span>
                            <p class="text-base font-bold text-slate-400 mt-3 uppercase tracking-widest">Waiting for scans…</p>
                        </div>
                    @endforelse
                </div>

                {{-- Grand total footer --}}
                @if(count($cart) > 0)
                <div class="mt-5 pt-5 border-t border-surface-container-highest">
                    <div class="flex justify-between items-end">
                        <p class="text-xs font-bold text-outline uppercase tracking-widest">Grand Total Qty</p>
                        <p class="text-4xl font-black tracking-tighter text-on-surface">{{ number_format(collect($cart)->sum('qty')) }}</p>
                    </div>
                </div>
                @endif
            </div>{{-- /RIGHT PANEL --}}

        </div>
    </div>

    {{-- =============================================
         STICKY BOTTOM BAR  (desktop md+)
    ================================================ --}}
    <div class="fixed bottom-0 left-0 lg:left-64 right-0 bg-surface-container-lowest/95 backdrop-blur-xl px-8 py-4 flex items-center justify-between industrial-shadow z-50 border-t border-surface-container-high">
        {{-- Left actions --}}
        <div class="flex gap-3">
            <button class="flex items-center gap-2 bg-surface-container-high text-on-surface px-6 py-4 rounded-xl font-bold text-sm hover:bg-surface-container-highest transition-all active:scale-95 uppercase tracking-wide">
                <span class="material-symbols-outlined">print</span>
                Print Labels
            </button>
            <button class="flex items-center gap-2 bg-surface-container-high text-on-surface px-6 py-4 rounded-xl font-bold text-sm hover:bg-surface-container-highest transition-all active:scale-95 uppercase tracking-wide">
                <span class="material-symbols-outlined">description</span>
                View Manifest
            </button>
        </div>

        {{-- Right: session info + submit --}}
        <div class="flex gap-6 items-center">
            <div class="text-right hidden xl:block">
                <p class="text-[10px] font-bold text-outline uppercase tracking-widest">Operator Session</p>
                <p class="text-sm font-bold text-on-surface">Terminal 01 • Active</p>
            </div>

            @if(count($cart) > 0)
                <button wire:click="submit"
                        class="green-action-gradient text-white px-16 py-5 rounded-xl font-black text-xl tracking-tighter shadow-2xl shadow-green-500/30 hover:scale-[1.02] active:scale-95 transition-all flex items-center gap-3">
                    <span class="material-symbols-outlined text-2xl">publish</span>
                    SUBMIT STOCK IN
                </button>
            @else
                <button class="bg-surface-container-high text-slate-400 px-16 py-5 rounded-xl font-black text-xl tracking-tighter cursor-not-allowed opacity-50 flex items-center gap-3">
                    <span class="material-symbols-outlined text-2xl">publish</span>
                    SUBMIT STOCK IN
                </button>
            @endif
        </div>
    </div>

    {{-- =============================================
         NEW-ITEM MODAL  (full-screen overlay)
    ================================================ --}}
    @if($isNewItem && !$currentItem)
    {{-- Handled inline above --}}
    @endif

    {{-- =============================================
         JS: audio beep + focus helpers
    ================================================ --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            function playBeep(freq, duration) {
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc  = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain); gain.connect(ctx.destination);
                    osc.type = 'sine'; osc.frequency.value = freq;
                    gain.gain.setValueAtTime(0.05, ctx.currentTime);
                    osc.start(); osc.stop(ctx.currentTime + duration);
                } catch(e) {}
            }
            window.addEventListener('scan-completed', () => { if (navigator.vibrate) navigator.vibrate(50); playBeep(880, 0.08); });
            window.addEventListener('scan-success',   () => playBeep(440, 0.04));
        });

        window.addEventListener('focus-barcode-input', () => {
            setTimeout(() => {
                const el = document.getElementById('barcode-input-stock-in');
                if (el) { el.focus(); el.select(); }
            }, 50);
        });

        document.addEventListener('keydown', e => {
            if (e.ctrlKey && e.key === '/') document.getElementById('barcode-input-stock-in')?.focus();
        });

        let html5QrCode;

        function startScanner() {
            document.getElementById('scanner-container').classList.remove('hidden');
            if (!html5QrCode) html5QrCode = new Html5Qrcode("reader");
            
            const config = { 
                fps: 20, 
                qrbox: { width: 280, height: 160 }, // Rectangular for barcodes
                aspectRatio: 1.0,
                experimentalFeatures: {
                    useBarCodeDetectorIfSupported: true // High performance on iOS
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
                (decodedText) => {
                    stopScanner();
                    @this.set('barcode', decodedText);
                    @this.call('handleScan');
                },
                () => {}
            ).catch(err => {
                console.error("Camera startup error", err);
                alert("Could not start camera. Please ensure permissions are granted.");
                stopScanner();
            });
        }

        function stopScanner() {
            if (html5QrCode && html5QrCode.isScanning) {
                html5QrCode.stop().then(() => {
                    document.getElementById('scanner-container').classList.add('hidden');
                }).catch(err => console.error("Error stopping scanner", err));
            } else {
                document.getElementById('scanner-container').classList.add('hidden');
            }
        }
    </script>
</div>
