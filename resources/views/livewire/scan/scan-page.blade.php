<div class="pt-24 px-4 pb-6 lg:px-6 min-h-screen bg-slate-50/30">

    @if($isSubmitted)
    {{-- ══════════════════════════════════════════
         SUCCESS / PRINT SCREEN
    ══════════════════════════════════════════════ --}}
    <div class="max-w-2xl mx-auto mt-10 text-center space-y-8 animate-in fade-in zoom-in duration-500">
        <div class="w-24 h-24 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto shadow-xl shadow-emerald-500/10">
            <span class="material-symbols-outlined text-6xl" style="font-variation-settings: 'FILL' 1;">check_circle</span>
        </div>
        
        <div class="space-y-2">
            <h1 class="text-4xl font-black text-slate-900 tracking-tight">Transaction Confirmed!</h1>
            <p class="text-slate-500 font-bold uppercase tracking-widest text-sm">Code: <span class="text-primary">{{ $lastTransactionCode }}</span></p>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <button onclick="window.print()" class="bg-primary text-white py-5 rounded-2xl font-black text-xl shadow-2xl shadow-primary/20 hover:scale-105 active:scale-95 transition-all flex items-center justify-center gap-3">
                <span class="material-symbols-outlined text-3xl">print</span>
                PRINT RECEIPT
            </button>
            <button wire:click="resetSession" class="bg-white text-slate-700 border-2 border-slate-100 py-5 rounded-2xl font-black text-xl shadow-lg hover:bg-slate-50 active:scale-95 transition-all flex items-center justify-center gap-3">
                <span class="material-symbols-outlined text-3xl">add_box</span>
                NEW SESSION
            </button>
        </div>

        {{-- Mini Receipt Preview for screen --}}
        <div class="bg-white border-2 border-slate-100 rounded-2xl p-8 text-left shadow-sm">
            <div class="flex justify-between items-start mb-6 border-b-2 border-slate-50 pb-4">
                <h2 class="font-black uppercase tracking-tighter text-lg">Transaction Summary</h2>
                <span class="text-xs font-mono text-slate-400">{{ now()->format('d M Y H:i') }}</span>
            </div>
            <div class="space-y-4">
                @php
                    $submittedTrx = \App\Models\StockTransaction::with(['items', 'department', 'user'])->find($lastTransactionId);
                @endphp
                @if($submittedTrx)
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-400 font-bold uppercase tracking-widest">Department</span>
                        <span class="font-black text-slate-900">{{ $submittedTrx->department->name }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-400 font-bold uppercase tracking-widest">PIC</span>
                        <span class="font-black text-slate-900">{{ $submittedTrx->user->name }}</span>
                    </div>
                    <div class="border-t-2 border-dashed border-slate-100 pt-4 mt-4 space-y-2">
                        @foreach($submittedTrx->items as $item)
                        <div class="flex justify-between text-xs">
                            <span class="font-bold text-slate-600 truncate flex-1 pr-4">{{ $item->item_name_snapshot }}</span>
                            <span class="font-black text-slate-900 shrink-0">x{{ $item->qty }}</span>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
    @else
    {{-- ══════════════════════════════════════════
         MAIN INTERFACE (SCAN & CART)
    ══════════════════════════════════════════════ --}}
    
    {{-- ── 1. SESSION HEADER (DEPT / PIC / REF) ── --}}
    <section class="max-w-7xl mx-auto mb-8 animate-in slide-in-from-top-4 duration-500">
        <div class="bg-white border-2 border-slate-100 rounded-3xl p-6 lg:p-8 industrial-shadow">
            <div class="flex flex-col lg:flex-row gap-6 items-end">
                <div class="flex-1 w-full space-y-3">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] px-1 italic">01. Destination Dept</label>
                    <div class="relative group">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 material-symbols-outlined text-primary group-focus-within:scale-110 transition-transform">corporate_fare</span>
                        <select wire:model.live="deptId" class="w-full pl-12 pr-4 py-4 bg-slate-50 rounded-2xl border-2 border-transparent focus:border-primary focus:ring-0 font-bold text-slate-700 transition-all cursor-pointer shadow-sm">
                            <option value="">Select Department...</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }} ({{ $dept->code }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex-1 w-full space-y-3">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] px-1 italic">02. Recipient (PIC)</label>
                    <div class="relative group">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 material-symbols-outlined text-primary group-focus-within:scale-110 transition-transform">person</span>
                        <select wire:model.live="picId" class="w-full pl-12 pr-4 py-4 bg-slate-50 rounded-2xl border-2 border-transparent focus:border-primary focus:ring-0 font-bold text-slate-700 transition-all cursor-pointer shadow-sm" {{ empty($availablePics) ? 'disabled' : '' }}>
                            <option value="">Select PIC...</option>
                            @foreach($availablePics as $pic)
                                <option value="{{ $pic->id }}">{{ $pic->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex-1 w-full space-y-3">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] px-1 italic">03. Machine / Work Order</label>
                    <div class="relative group">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 material-symbols-outlined text-primary group-focus-within:scale-110 transition-transform">manufacturing</span>
                        <input wire:model="reference" placeholder="e.g. MCH-012, WO-492" class="w-full pl-12 pr-4 py-4 bg-slate-50 rounded-2xl border-2 border-transparent focus:border-primary focus:ring-0 font-bold text-slate-700 transition-all shadow-sm" type="text"/>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="grid grid-cols-12 gap-8 max-w-[1600px] mx-auto">
        {{-- ── 2. LEFT PANEL: SCAN AREA (8 Cols on Desktop) ── --}}
        <section class="col-span-12 lg:col-span-7 xl:col-span-8 space-y-8 min-w-0">

            @if($message)
            <div class="{{ $messageType === 'success' ? 'bg-emerald-50 border-emerald-500' : 'bg-red-50 border-red-500' }} border-l-4 p-5 rounded-2xl flex items-center gap-4 shadow-sm animate-in fade-in slide-in-from-left-2 transition-all">
                <span class="material-symbols-outlined {{ $messageType === 'success' ? 'text-emerald-500' : 'text-red-500' }} text-3xl" style="font-variation-settings: 'FILL' 1;">
                    {{ $messageType === 'success' ? 'check_circle' : 'error' }}
                </span>
                <p class="text-base font-bold text-slate-800">{{ $message }}</p>
            </div>
            @endif

            <div class="bg-primary p-1.5 rounded-[2rem] shadow-2xl shadow-primary/20">
                <div class="bg-white p-6 rounded-[calc(2rem-6px)]">
                    <div class="flex items-center gap-6">
                        <div class="flex-1 relative">
                            <span class="absolute left-5 top-1/2 -translate-y-1/2 text-primary text-4xl material-symbols-outlined">barcode_scanner</span>
                            <input
                                wire:model="barcode"
                                wire:keydown.enter="handleScan"
                                id="barcode-input"
                                autofocus
                                class="w-full pl-20 pr-8 py-7 bg-slate-50 rounded-3xl border-2 border-transparent focus:border-primary focus:ring-4 focus:ring-primary/5 text-3xl font-black placeholder:text-slate-200 transition-all font-mono"
                                placeholder="SCAN ITEM BARCODE"
                                type="text"/>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <button wire:click="handleScan" class="bg-primary text-white w-20 h-20 rounded-3xl shadow-xl flex items-center justify-center hover:brightness-110 active:scale-95 transition-all">
                                <span class="material-symbols-outlined text-5xl">keyboard_return</span>
                            </button>
                            <button onclick="startScanner()" type="button" class="bg-slate-100 text-slate-500 w-20 h-20 rounded-3xl flex items-center justify-center hover:bg-slate-200 active:scale-95 transition-all outline-none">
                                <span class="material-symbols-outlined text-4xl font-black">photo_camera</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Camera Scanner Overlay --}}
            <div id="scanner-container" class="hidden mt-4 bg-black rounded-[2rem] overflow-hidden relative border-8 border-white shadow-2xl" wire:ignore>
                <div id="reader" style="width: 100%;"></div>
                <button type="button" onclick="stopScanner()" class="absolute top-6 right-6 bg-red-600 text-white px-8 py-4 text-sm font-black rounded-2xl shadow-xl z-50 hover:bg-red-700 flex items-center gap-3 transition-all">
                    <span class="material-symbols-outlined">close</span> CANCEL SCAN
                </button>
            </div>

            @if($currentItem)
            <div class="bg-white rounded-3xl shadow-xl overflow-hidden border border-slate-100 success-flash animate-in zoom-in-95 duration-300">
                <div class="flex flex-col sm:flex-row">
                    <div class="sm:w-56 shrink-0 bg-slate-100 relative group overflow-hidden">
                        <img alt="Product" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" src="{{ $currentItem->images->where('is_primary', true)->first() ? asset('storage/' . $currentItem->images->where('is_primary', true)->first()->path) : asset('images/placeholders/item.svg') }}"/>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
                    </div>
                    <div class="flex-1 p-8 flex flex-col justify-between gap-6">
                        <div class="flex justify-between items-start gap-4">
                            <div>
                                <h2 class="text-3xl font-black tracking-tight text-slate-900 leading-none">{{ $currentItem->item->name }}</h2>
                                <p class="text-xs font-black text-primary uppercase mt-3 tracking-widest bg-primary/5 inline-block px-3 py-1 rounded-full">{{ $currentItem->sku }}</p>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Local Stock</span>
                                <span class="text-3xl font-black text-slate-900 leading-none">{{ \App\Models\Bin::where('item_variant_id', $currentItem->id)->sum('current_qty') }} <span class="text-xs text-slate-400 uppercase">{{ $currentItem->unit }}</span></span>
                            </div>
                        </div>

                        <div class="flex items-center gap-6">
                            <div class="flex-1 space-y-2">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] text-center">Batch Quantity</label>
                                <div class="flex items-center justify-between bg-slate-50 border-2 border-slate-100 rounded-2xl p-2">
                                    <button wire:click="$set('qty', {{ $qty > 1 ? $qty - 1 : 1 }})" class="w-14 h-14 bg-white text-primary rounded-xl shadow-sm hover:translate-y-[-2px] active:translate-y-0 transition-all">
                                        <span class="material-symbols-outlined text-3xl font-black">remove</span>
                                    </button>
                                    <input wire:model="qty" class="w-24 text-center bg-transparent border-none focus:ring-0 text-5xl font-black text-slate-900" type="number"/>
                                    <button wire:click="$set('qty', {{ (int)$qty + 1 }})" class="w-14 h-14 bg-white text-primary rounded-xl shadow-sm hover:translate-y-[-2px] active:translate-y-0 transition-all">
                                        <span class="material-symbols-outlined text-3xl font-black">add</span>
                                    </button>
                                </div>
                            </div>
                            <button wire:click="addToCart" class="shrink-0 h-24 px-10 bg-primary text-white rounded-3xl font-black text-xl flex items-center justify-center gap-3 shadow-2xl shadow-primary/30 hover:shadow-primary/50 hover:-translate-y-1 active:translate-y-0 transition-all">
                                <span class="material-symbols-outlined text-3xl">add_shopping_cart</span>
                                COMMIT
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </section>

        {{-- ── 3. RIGHT PANEL: CART BATCH (4 Cols on Desktop) ── --}}
        <aside class="col-span-12 lg:col-span-5 xl:col-span-4 flex flex-col bg-white border-2 border-slate-100 rounded-[2rem] overflow-hidden shadow-sm sticky top-28" style="max-height: calc(100vh - 120px);">
            <div class="p-6 border-b-2 border-slate-50 flex items-center justify-between bg-slate-50">
                <div>
                    <h3 class="font-black text-xl uppercase tracking-tighter text-slate-900">Active Batch</h3>
                    <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest">Pending Movement</p>
                </div>
                <span class="bg-primary text-white font-black text-[10px] px-4 py-2 rounded-full tracking-widest">{{ count($cart) }} ROWS</span>
            </div>

            <div class="flex-1 overflow-y-auto p-5 space-y-4 custom-scroll">
                @forelse($cart as $index => $item)
                <div class="bg-slate-50 group p-4 rounded-2xl border-2 border-transparent hover:border-primary/20 transition-all relative">
                    <button wire:click="removeFromCart({{ $index }})" class="absolute -top-2 -right-2 bg-white border-2 border-slate-100 text-slate-300 hover:text-red-500 w-8 h-8 rounded-full flex items-center justify-center shadow-lg transition-all scale-0 group-hover:scale-100">
                        <span class="material-symbols-outlined text-lg">close</span>
                    </button>
                    <div class="flex justify-between items-start mb-2">
                        <span class="font-black text-sm text-slate-800 leading-tight flex-1 pr-1 truncate">{{ $item['name'] }}</span>
                        <span class="text-sm font-black text-primary shrink-0">x{{ $item['qty'] }}</span>
                    </div>
                    <div class="flex justify-between items-end">
                        <span class="text-[10px] font-mono font-bold text-slate-400 uppercase">{{ $item['barcode'] }}</span>
                        <span class="text-xs font-black text-slate-400">Total: @money($item['qty'] * $item['price'])</span>
                    </div>
                </div>
                @empty
                <div class="h-full flex flex-col items-center justify-center py-20 opacity-30">
                    <span class="material-symbols-outlined text-7xl mb-4">shopping_basket</span>
                    <p class="text-sm font-black uppercase tracking-widest">Cart Empty</p>
                </div>
                @endforelse
            </div>

            <div class="p-6 bg-slate-50 border-t-2 border-slate-100 space-y-4">
                <div class="flex justify-between items-center px-1">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Batch Value</span>
                    <span class="text-2xl font-black text-slate-900">@money(collect($cart)->sum(fn($i) => $i['qty'] * $i['price']))</span>
                </div>
                <button wire:click="submit"
                        class="w-full bg-emerald-500 text-white py-5 rounded-2xl font-black text-lg flex items-center justify-center gap-3 shadow-2xl shadow-emerald-500/30 hover:brightness-110 active:scale-95 transition-all disabled:opacity-30"
                        @if(empty($cart)) disabled @endif>
                    <span class="material-symbols-outlined text-2xl" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                    SUBMIT TRANSACTION
                </button>
            </div>
        </aside>
    </div>
    @endif

    {{-- ── PRINT RECEIPT AREA (HIDDEN) ── --}}
    <div id="print-area" class="hidden print:block fixed inset-0 bg-white z-[9999] p-8">
        @if($isSubmitted && $submittedTrx)
        <div class="max-w-md mx-auto border-2 border-black p-6 font-mono text-sm space-y-6">
            <div class="text-center border-b-2 border-black pb-4">
                <h1 class="text-xl font-bold uppercase">WAREHOUSE RECEIPT</h1>
                <p class="text-lg font-bold">{{ $submittedTrx->code }}</p>
                <p>{{ $submittedTrx->created_at->format('d/m/Y H:i') }}</p>
            </div>

            <div class="space-y-1">
                <p><strong>DEPT:</strong> {{ $submittedTrx->department->name }}</p>
                <p><strong>PIC:</strong> {{ $submittedTrx->user->name }}</p>
                <p><strong>REF:</strong> {{ $submittedTrx->reference ?: '-' }}</p>
            </div>

            <table class="w-full border-t-2 border-black pt-4">
                <thead>
                    <tr class="text-left border-b border-black">
                        <th class="py-1">ITEM</th>
                        <th class="text-right py-1">QTY</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/10">
                    @foreach($submittedTrx->items as $item)
                    <tr>
                        <td class="py-2 pr-4">
                            <p class="font-bold">{{ $item->item_name_snapshot }}</p>
                            <p class="text-xs">{{ $item->erp_code_snapshot }}</p>
                        </td>
                        <td class="text-right py-2 font-bold">{{ $item->qty }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="border-t-2 border-black pt-4 flex justify-between items-end">
                <div class="text-[10px] space-y-4">
                    <div class="w-24 border-t border-black text-center pt-1">Authorized By</div>
                    <div class="w-24 border-t border-black text-center pt-1">Receiver (PIC)</div>
                </div>
                <div class="text-right">
                    <p class="text-[10px] uppercase font-bold text-slate-400">Total Value</p>
                    <p class="text-lg font-bold">@money($submittedTrx->total_price)</p>
                </div>
            </div>
        </div>
        @endif
    </div>

</div>

<style>
    @media print {
        body * { visibility: hidden; }
        #print-area, #print-area * { visibility: visible; }
        #print-area { position: absolute; left: 0; top: 0; width: 100%; }
    }
</style>

<script>
    (function() {
        var html5QrCode;

        window.startScanner = function() {
            var container = document.getElementById('scanner-container');
            if (container) container.classList.remove('hidden');
            
            if (!html5QrCode) html5QrCode = new Html5Qrcode("reader");
            
            var config = { 
                fps: 20, 
                qrbox: { width: 280, height: 160 }, 
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
                    window.playSuccessBeep();
                    window.stopScanner();
                    @this.set('barcode', decodedText);
                    @this.call('handleScan');
                },
                function() {}
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

        window.addEventListener('scan-completed', function(event) {
            window.playSuccessBeep();
            if (navigator.vibrate) navigator.vibrate(100);
        });

        window.addEventListener('focus-barcode-input', function(event) {
            var input = document.getElementById('barcode-input');
            if (input) { input.focus(); input.select(); }
        });

        window.playSuccessBeep = function() {
            try {
                var AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) return;
                var audioCtx = new AudioCtx();
                var oscillator = audioCtx.createOscillator();
                var gainNode = audioCtx.createGain();
                oscillator.connect(gainNode);
                gainNode.connect(audioCtx.destination);
                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(880, audioCtx.currentTime);
                gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.1);
                oscillator.start(); oscillator.stop(audioCtx.currentTime + 0.1);
            } catch(e) {}
        };

        document.addEventListener('keydown', function(e) {
            if (document.activeElement && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                var input = document.getElementById('barcode-input');
                if (input && /^[a-zA-Z0-9]$/.test(e.key)) input.focus();
            }
        });
    })();
</script>
