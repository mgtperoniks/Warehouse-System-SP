<div class="pt-[52px] px-md pb-md min-h-screen bg-slate-50/30" x-data="scannerEngine()" @click="recoverFocus()">

    <!-- Industrial Overlay Confirmation Flashes -->
    <div x-show="flashSuccess" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 pointer-events-none border-[12px] border-emerald-500/40 z-[9999]" style="display: none;"></div>
    <div x-show="flashError" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 pointer-events-none border-[12px] border-red-500/40 z-[9999]" style="display: none;"></div>

    {{-- =============================================
         FLASH / LIVE FEEDBACK TOAST
    ================================================ --}}
    <div x-data="{ show: false, message: '', type: 'success' }"
         x-on:message-dispatched.window="show = true; message = $event.detail.message; type = $event.detail.type; setTimeout(function() { show = false; }, 4000)"
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
             class="rounded-md text-white py-2.5 px-4 flex items-center justify-between shadow-2xl border-b-2">
             <div class="flex items-center gap-2">
                 <span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1;" x-text="type === 'success' ? 'check_circle' : 'error'"></span>
                 <span class="text-xs font-bold uppercase tracking-widest" x-text="message"></span>
             </div>
             <button @click="show = false" class="material-symbols-outlined text-white/80 hover:text-white text-md ml-4">close</button>
        </div>
    </div>

    {{-- =============================================
         🎛️ SCANNER ENGINE STATUS & CONTROL WIDGET
    ================================================ --}}
    <div class="max-w-7xl mx-auto mb-xs mt-sm flex flex-wrap items-center justify-between gap-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md py-1.5 px-md text-xs shadow-sm">
        <div class="flex items-center gap-sm">
            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Inbound Engine:</span>
            <button type="button" @click="toggleEngine()" class="flex items-center gap-1.5 px-2 py-0.5 rounded font-black text-[9px] uppercase transition-all border outline-none"
                    :class="engineMode === 'enhanced' ? 'bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 border-emerald-200' : 'bg-slate-50 dark:bg-slate-800 text-slate-500 border-slate-200 dark:border-slate-700'">
                <span class="w-1.5 h-1.5 rounded-full inline-block" :class="engineMode === 'enhanced' ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'"></span>
                <span x-text="engineMode === 'enhanced' ? '🟢 ENHANCED MODE' : '⚪ LEGACY MODE'"></span>
            </button>
        </div>
        
        <div class="flex items-center gap-md">
            <div class="flex items-center gap-sm">
                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Volume:</span>
                <div class="flex bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-md p-0.5">
                    <button type="button" @click="setVolume('mute')" class="px-2 py-0.5 rounded text-[8px] font-black uppercase transition-all" :class="volume === 'mute' ? 'bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 font-bold shadow-sm' : 'text-slate-400' ">MUTE</button>
                    <button type="button" @click="setVolume('low')" class="px-2 py-0.5 rounded text-[8px] font-black uppercase transition-all" :class="volume === 'low' ? 'bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 font-bold shadow-sm' : 'text-slate-400' ">LOW</button>
                    <button type="button" @click="setVolume('normal')" class="px-2 py-0.5 rounded text-[8px] font-black uppercase transition-all" :class="volume === 'normal' ? 'bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 font-bold shadow-sm' : 'text-slate-400' ">NORMAL</button>
                </div>
            </div>
            
            <div class="flex items-center gap-sm border-l border-slate-200 dark:border-slate-800 pl-md">
                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Focus:</span>
                <span class="flex items-center gap-1 font-bold text-[9px]" :class="isFocused ? 'text-emerald-600' : 'text-amber-500'">
                    <span class="w-1.5 h-1.5 rounded-full" :class="isFocused ? 'bg-emerald-500' : 'bg-amber-400'"></span>
                    <span x-text="isFocused ? 'READY' : 'LOST'"></span>
                </span>
            </div>
        </div>
    </div>

    <!-- 🚨 MULTI-TAB GOVERNANCE & LOCK WARNING -->
    <div x-show="governanceStatus !== 'ACTIVE'" x-transition class="max-w-7xl mx-auto mb-sm animate-in slide-in-from-top-4 duration-300" style="display: none;">
        <div class="border rounded-md p-md shadow-sm" 
             :class="{
                'bg-slate-100 border-slate-300 text-slate-700': governanceStatus === 'MONITOR',
                'bg-amber-55 border-amber-300 text-amber-800': governanceStatus === 'UNSTABLE',
                'bg-red-55 border-red-300 text-red-800': governanceStatus === 'TAKEOVER_ELIGIBLE'
             }">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-sm">
                <div class="flex items-center gap-sm">
                    <span class="material-symbols-outlined text-2xl animate-pulse"
                          :class="{
                             'text-slate-500': governanceStatus === 'MONITOR',
                             'text-amber-600': governanceStatus === 'UNSTABLE',
                             'text-red-600': governanceStatus === 'TAKEOVER_ELIGIBLE'
                          }">
                        security
                    </span>
                    <div>
                        <h4 class="text-xs font-black uppercase tracking-wider">
                            <span x-show="governanceStatus === 'MONITOR'">🔒 Read-Only Monitor Mode Active</span>
                            <span x-show="governanceStatus === 'UNSTABLE'">⚠️ Terminal Connection Unstable</span>
                            <span x-show="governanceStatus === 'TAKEOVER_ELIGIBLE'">🚨 Terminal Hijack Protection</span>
                        </h4>
                        <p class="text-[10px] opacity-90 mt-0.5">
                            <span x-show="governanceStatus === 'MONITOR'">Another tab (<span class="font-mono" x-text="activeOwnerTabId"></span>) is currently operating this terminal. Inputs on this tab are temporarily locked to prevent out-of-order scans.</span>
                            <span x-show="governanceStatus === 'UNSTABLE'">No active heartbeat detected from the owning tab for 5 seconds. Standing by for auto-recovery...</span>
                            <span x-show="governanceStatus === 'TAKEOVER_ELIGIBLE'">The primary operator tab has been silent for 10+ seconds. You can now forcefully claim control of this terminal session.</span>
                        </p>
                    </div>
                </div>
                <div x-show="governanceStatus === 'TAKEOVER_ELIGIBLE'">
                    <button type="button" @click="forceTakeover()" class="px-4 h-9 bg-red-600 hover:bg-red-700 text-white rounded-md text-xs font-black uppercase tracking-wider shadow-md hover:shadow-lg transition-all active:scale-95">
                        Claim Active Control
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- =============================================
         MAIN CANVAS
    ================================================ --}}
    <div class="max-w-[1600px] mx-auto py-sm px-md grid grid-cols-12 gap-md">

        {{-- ── LAST-ACTION BANNER ──────────────────────── --}}
        @if($lastAction)
        <div class="col-span-12">
            <div class="bg-green-100 dark:bg-emerald-950/20 border-l-4 border-green-600 p-sm flex items-center justify-between rounded-r-md shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-green-700 dark:text-emerald-500" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                    <div>
                        <p class="text-green-800 dark:text-emerald-350 font-bold tracking-tight text-xs">Last Action Recorded</p>
                        <p class="text-green-700 dark:text-emerald-400 text-[10px] font-semibold mt-0.5">{{ $lastAction }}</p>
                    </div>
                </div>
                <button wire:click="$set('lastAction', '')" class="material-symbols-outlined text-green-700 hover:bg-green-200 rounded-full p-1 transition-colors text-sm">close</button>
            </div>
        </div>
        @endif

        {{-- ══════════════════════════════════════════════
             LEFT PANEL  (7 columns)
        ══════════════════════════════════════════════════ --}}
        <div class="col-span-12 lg:col-span-7 space-y-md">

            {{-- ── SCAN BAR ──────────────────────────────── --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md p-0.5 ready-to-scan-glow transition-all shadow-sm">
                <div class="relative flex items-center px-3">
                    <span class="material-symbols-outlined absolute left-3.5 text-emerald-600 text-lg">barcode_scanner</span>
                    <input
                        x-model="barcodeText"
                        @keydown.enter.prevent="handleScanInput()"
                        id="barcode-input"
                        autofocus
                        class="w-full bg-transparent border-none text-sm font-black py-2.5 pl-10 focus:ring-0 text-on-surface outline-none placeholder:text-slate-400 font-mono text-on-surface animate-pulse"
                        placeholder="READY TO SCAN PHYSICAL BARCODE..."
                        type="text"/>
                    <div class="flex items-center gap-sm pr-1 shrink-0">
                        {{-- Auto-Add Toggle --}}
                        <div class="flex items-center gap-2 bg-slate-50 dark:bg-slate-800 rounded-md px-2.5 h-9 border border-slate-200 dark:border-slate-800 shadow-sm">
                            <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Auto</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" wire:model.live="autoAddMode" class="sr-only peer">
                                <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-green-600"></div>
                            </label>
                        </div>
                        <button onclick="startScanner()" type="button"
                                class="bg-slate-100 hover:bg-slate-250 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-655 dark:text-slate-400 w-9 h-9 shrink-0 rounded-md flex items-center justify-center transition-all outline-none">
                            <span class="material-symbols-outlined text-lg" style="font-variation-settings: 'FILL' 1;">photo_camera</span>
                        </button>
                    </div>
                    <div x-show="invalidFormatMessage" x-transition.opacity.duration.150ms class="absolute left-0 right-0 top-[48px] bg-red-500 text-white rounded-md text-[10px] font-black uppercase tracking-widest text-center py-1 z-20 shadow-md" x-text="invalidFormatMessage" style="display: none;"></div>
                </div>
            </div>

            <!-- Compact Temporary Industrial Scanner Debug Telemetry Panel -->
            <div class="mt-2 p-2 bg-slate-950 border border-slate-800 rounded-md text-[9px] font-mono text-slate-400 grid grid-cols-2 sm:grid-cols-5 gap-2 uppercase tracking-wider shadow-inner" style="letter-spacing: 0.05em;">
                <div class="border-r border-slate-800 pr-1">
                    <span class="text-slate-550 dark:text-slate-500 block text-[8px] font-black">LAST INPUT:</span>
                    <span class="font-bold text-white text-[10px]" x-text="debugLastInput || '-'"></span>
                </div>
                <div class="border-r border-slate-800 pr-1">
                    <span class="text-slate-550 dark:text-slate-500 block text-[8px] font-black">MODE:</span>
                    <span class="font-bold text-slate-200" :class="engineMode === 'enhanced' ? 'text-emerald-400' : 'text-slate-400'" x-text="engineMode.toUpperCase()"></span>
                </div>
                <div class="border-r border-slate-800 pr-1">
                    <span class="text-slate-550 dark:text-slate-500 block text-[8px] font-black">DISPATCH:</span>
                    <span class="font-bold" 
                          :class="{
                              'text-amber-400 animate-pulse': debugDispatch === 'DISPATCHED',
                              'text-emerald-400': debugDispatch === 'SUCCESS',
                              'text-red-400': debugDispatch === 'FAILED',
                              'text-slate-400': debugDispatch === 'PENDING'
                          }" x-text="debugDispatch"></span>
                </div>
                <div class="border-r border-slate-800 pr-1">
                    <span class="text-slate-550 dark:text-slate-500 block text-[8px] font-black">LOOKUP:</span>
                    <span class="font-bold" 
                          :class="{
                              'text-emerald-400': debugLookup === 'FOUND',
                              'text-red-400': debugLookup === 'NOT FOUND',
                              'text-slate-400': debugLookup === 'PENDING'
                          }" x-text="debugLookup"></span>
                </div>
                <div>
                    <span class="text-slate-550 dark:text-slate-500 block text-[8px] font-black">DUP BLOCK:</span>
                    <span class="font-bold" :class="debugDuplicateBlock === 'YES' ? 'text-red-500 font-black animate-pulse' : 'text-slate-400'" x-text="debugDuplicateBlock"></span>
                </div>
            </div>

            {{-- ── Camera Scanner (hidden by default) ── --}}
            <div id="scanner-container" class="hidden bg-black rounded-md overflow-hidden relative border border-slate-200 dark:border-slate-800 shadow-2xl z-20" wire:ignore>
                <div id="reader" style="width: 100%;"></div>
                <div class="absolute bottom-3 left-1/2 -translate-x-1/2 bg-black/60 backdrop-blur-md text-white px-4 py-1 rounded-full text-[9px] font-black uppercase tracking-widest flex items-center gap-2 z-10 pointer-events-none">
                    <span class="material-symbols-outlined text-xs text-yellow-400">info</span>
                    Move camera slowly for focus
                </div>
                <button type="button" onclick="stopScanner()" class="absolute top-3 right-3 bg-red-650 text-white px-4 h-11 text-xs font-black rounded-md shadow-lg z-50 hover:bg-red-700 flex items-center gap-2 transition-all">
                    <span class="material-symbols-outlined text-sm">close</span> Cancel Scan
                </button>
            </div>

            {{-- ── ITEM AREA  (Found / Not-Found / Waiting) ──── --}}
            @if($currentItem)
                {{-- FOUND STATE: Detail card + image --}}
                <div class="grid grid-cols-12 gap-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md p-sm shadow-sm relative overflow-hidden border-l-4 border-l-green-600">
                    <div class="absolute top-2 right-2">
                        <span class="bg-green-50 dark:bg-emerald-950/20 text-green-700 dark:text-emerald-455 text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded">Identity Verified</span>
                    </div>
                    
                    {{-- Image Column --}}
                    <div class="col-span-3 shrink-0 bg-slate-50 dark:bg-slate-800 rounded-md overflow-hidden border border-slate-100 dark:border-slate-850 flex items-center justify-center p-1 w-20 h-20">
                        <img alt="Product Preview"
                             class="w-full h-full object-cover rounded-md"
                             src="{{ $currentItem->images->where('is_primary', true)->first() ? asset('storage/' . $currentItem->images->where('is_primary', true)->first()->path) : asset('images/placeholders/item.svg') }}"/>
                    </div>

                    {{-- Detail Column --}}
                    <div class="col-span-9 flex flex-col justify-center gap-1.5 pl-2">
                        <h2 class="text-xs font-black tracking-tight text-on-surface leading-tight pr-24">{{ $currentItem->item->name }}</h2>
                        <p class="text-slate-400 font-mono-scannable text-[9px] tracking-wider uppercase">ERP: {{ $currentItem->erp_code }}</p>
                        
                        {{-- Supplier selector --}}
                        <div class="flex items-center gap-2">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest shrink-0">Supplier:</label>
                            <div class="relative flex-1">
                                <select wire:key="stock-in-supplier-select" wire:model="supplier_id" class="w-full h-9 pl-3 pr-8 bg-slate-50 border border-slate-200 dark:border-slate-855 rounded-md font-bold text-on-surface focus:ring-1 focus:ring-green-500/20 focus:border-green-500 text-xs py-1 transition-all">
                                    <option value="">— Select Supplier —</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                                <span class="material-symbols-outlined absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-sm">keyboard_arrow_down</span>
                            </div>
                        </div>
                    </div>
                </div>

            @elseif($isNewItem)
                {{-- NOT-FOUND STATE: registration --}}
                <div class="bg-white dark:bg-slate-900 rounded-md p-md border border-dashed border-slate-300 dark:border-slate-800 shadow-sm">
                    <div class="flex items-center gap-3 mb-sm text-slate-800 dark:text-slate-200">
                        <span class="material-symbols-outlined text-2xl text-amber-500" style="font-variation-settings: 'FILL' 1;">error</span>
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider">Barcode Not Found</h3>
                            <p class="text-[10px] text-slate-400 mt-0.5">Code: <span class="bg-amber-100 dark:bg-amber-950/20 text-amber-700 px-1.5 rounded font-mono text-[9px] font-bold">{{ $barcode }}</span></p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-sm">
                        <div class="space-y-sm">
                            <div>
                                <label class="text-[9px] font-bold text-slate-455 uppercase tracking-widest mb-1 block">ERP Code</label>
                                <input wire:model="erpCode" class="w-full h-11 px-4 py-2.5 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md font-medium focus:ring-2 focus:ring-green-500/20 focus:border-green-500 text-sm text-on-surface" placeholder="e.g. 5.10.880.XXX" type="text"/>
                                @error('erpCode') <p class="text-[9px] text-error font-bold mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-[9px] font-bold text-slate-455 uppercase tracking-widest mb-1 block">Item Name</label>
                                <input wire:model="itemName" class="w-full h-11 px-4 py-2.5 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md font-medium focus:ring-2 focus:ring-green-500/20 focus:border-green-500 text-sm text-on-surface" placeholder="Full descriptive name" type="text"/>
                                @error('itemName') <p class="text-[9px] text-error font-bold mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="flex flex-col justify-end gap-2">
                            <button wire:click="createNewItem" class="h-11 w-full bg-green-600 text-white font-black rounded-md hover:bg-green-700 transition-colors uppercase tracking-widest text-xs">Save &amp; Continue</button>
                            <button wire:click="$set('isNewItem', false)" class="h-11 w-full bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-800 text-slate-655 dark:text-slate-400 font-bold rounded-md hover:bg-slate-200 transition-colors uppercase tracking-widest text-[10px]">Cancel</button>
                        </div>
                    </div>
                </div>

            @else
                {{-- WAITING STATE --}}
                <div class="rounded-md border-2 border-dashed border-slate-200 dark:border-slate-800/80 py-8 flex flex-col items-center justify-center opacity-40">
                    <span class="material-symbols-outlined text-4xl text-slate-300">barcode_scanner</span>
                    <p class="text-[10px] font-black text-slate-400 mt-2 uppercase tracking-widest">Waiting for inbound scans…</p>
                </div>
            @endif

            {{-- ── QTY & BIN ROW (only when item is loaded) ─── --}}
            @if($currentItem)
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md p-sm shadow-sm grid grid-cols-1 md:grid-cols-2 gap-sm items-center">
                {{-- Quantity control --}}
                <div class="flex items-center justify-between border-b md:border-b-0 md:border-r border-slate-100 dark:border-slate-800 pb-sm md:pb-0 md:pr-sm">
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest shrink-0">Received Qty:</label>
                    <div class="flex items-center gap-2">
                        <button wire:click="$set('qty', {{ $qty > 1 ? $qty - 1 : 1 }})"
                                class="w-9 h-9 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-750 flex items-center justify-center hover:bg-slate-100 dark:hover:bg-slate-700 transition-all active:scale-90 font-bold">
                            -
                        </button>
                        <span class="text-xl font-black text-emerald-600 w-12 text-center leading-none font-mono-scannable">{{ $qty }}</span>
                        <button wire:click="$set('qty', {{ (int)$qty + 1 }})"
                                class="w-9 h-9 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-750 flex items-center justify-center hover:bg-slate-100 dark:hover:bg-slate-700 transition-all active:scale-90 font-bold">
                            +
                        </button>
                    </div>
                </div>

                {{-- Bin selection --}}
                <div class="bg-white dark:bg-slate-900 rounded-md {{ $errors->has('bin_id') ? 'border-error' : '' }}">
                    <div class="relative flex items-center">
                        <span class="material-symbols-outlined absolute left-2.5 text-green-600 text-lg">warehouse</span>
                        <select wire:key="stock-in-bin-select" wire:model="binCode" class="w-full h-9 pl-8 pr-8 bg-slate-50 border border-slate-200 dark:border-slate-855 rounded-md font-bold text-on-surface focus:ring-1 focus:ring-green-500/20 focus:border-green-500 text-xs py-1 transition-all">
                            <option value="">— Select Target Bin —</option>
                            @foreach($bins as $bin)
                                <option value="{{ $bin->code }}">{{ $bin->code }} (Current: {{ $bin->current_qty }} / {{ $bin->max_capacity }} Pcs)</option>
                            @endforeach
                        </select>
                        <span class="material-symbols-outlined absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-sm">keyboard_arrow_down</span>
                    </div>
                    @error('bin_id') <p class="text-[9px] text-error font-bold mt-1 ml-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Add to list button --}}
            <button wire:click="addToCart"
                    class="w-full bg-emerald-600 hover:bg-emerald-700 text-white h-11 rounded-md font-black text-xs uppercase tracking-widest flex items-center justify-center gap-2 shadow-md hover:scale-[1.01] active:scale-[0.99] transition-all">
                <span class="material-symbols-outlined text-lg" style="font-variation-settings: 'FILL' 1;">add_circle</span>
                ADD TO RECEIVING LIST
            </button>
            @endif
        </div>{{-- /LEFT PANEL --}}

        {{-- ══════════════════════════════════════════════
             RIGHT PANEL  (5 columns) — Batch Manifest
        ══════════════════════════════════════════════════ --}}
        <div class="col-span-12 lg:col-span-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md p-md shadow-sm flex flex-col relative" style="max-height: calc(100vh - 88px);">
            
            <!-- ⚡ LAST SCANNED MOMENTUM PANEL (Alpine Overlay) -->
            <div x-show="showMomentum" 
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="absolute inset-0 bg-slate-900 text-white z-50 flex flex-col items-center justify-center p-md text-center rounded-md"
                 style="display: none;">
                <div class="w-16 h-16 rounded-lg bg-slate-800 border border-slate-700 overflow-hidden flex items-center justify-center p-1 mb-sm shadow-md">
                    <img :src="momentumData.photo" alt="Item Image" class="w-full h-full object-cover rounded-md" />
                </div>
                <div class="text-emerald-400 text-[10px] font-black uppercase tracking-widest flex items-center gap-1 justify-center">
                    <span class="material-symbols-outlined text-sm">check_circle</span> ADDED TO INBOUND DRAFT
                </div>
                <h4 class="text-xs font-black mt-1 leading-tight text-white px-sm" x-text="momentumData.name"></h4>
                <p class="text-[9px] font-mono mt-0.5 text-slate-400" x-text="'SKU: ' + momentumData.sku"></p>
                
                <div class="mt-md grid grid-cols-2 gap-sm w-full border-t border-slate-800 pt-md text-center px-sm">
                    <div>
                        <div class="text-[8px] font-black uppercase tracking-widest text-slate-500">Qty Intake</div>
                        <div class="text-xs font-black text-emerald-400" x-text="'+' + momentumData.qty"></div>
                    </div>
                    <div>
                        <div class="text-[8px] font-black uppercase tracking-widest text-slate-500">Intake Target Bin</div>
                        <div class="text-xs font-black text-white" x-text="momentumData.bin"></div>
                    </div>
                </div>
            </div>

            {{-- Header --}}
            <div class="flex items-center justify-between mb-sm border-b border-slate-100 dark:border-slate-800 pb-sm">
                <div>
                    <h3 class="text-xs font-black uppercase tracking-tight text-on-surface">Receiving Batch</h3>
                    <p class="text-[9px] font-bold text-slate-400 mt-0.5 uppercase tracking-widest">Ref: {{ $reference ?: 'B-' . date('Y-md') }}</p>
                </div>
                <span class="bg-slate-100 dark:bg-slate-805 px-2 py-0.5 rounded text-[10px] font-black text-primary uppercase tracking-widest">{{ count($cart) }} Items</span>
            </div>

            {{-- Scrollable list --}}
            <div class="flex-1 overflow-y-auto space-y-1.5 pr-1 custom-scroll">
                @forelse($cart as $key => $item)
                    <div class="bg-slate-50 dark:bg-slate-805 p-2 rounded-md border border-slate-200 dark:border-slate-800/80 relative border-l-4 border-l-emerald-600 transition-all">
                        <div class="flex justify-between items-center">
                            <div class="flex-1 min-w-0">
                                <p class="font-black text-xs text-on-surface tracking-tight truncate leading-tight pr-1">{{ $item['name'] }}</p>
                                <p class="text-[9px] font-mono-scannable text-slate-400 uppercase mt-0.5">{{ $item['bin_name'] }}{{ $item['supplier_name'] !== 'N/A' ? ' • ' . $item['supplier_name'] : '' }}</p>
                            </div>
                            <div class="flex items-center gap-2.5 ml-3 shrink-0">
                                <div class="text-right">
                                    <p class="text-xs font-black text-emerald-600 leading-none">x{{ number_format($item['qty']) }}</p>
                                    <p class="text-[8px] font-bold text-slate-400 uppercase tracking-tighter text-right">UNITS</p>
                                </div>
                                <button wire:click="removeFromCart('{{ $key }}')" class="material-symbols-outlined text-slate-350 hover:text-red-500 transition-colors text-[18px] cursor-pointer outline-none">delete</button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="flex-1 flex flex-col items-center justify-center py-10 opacity-30">
                        <span class="material-symbols-outlined text-2xl text-slate-300">move_to_inbox</span>
                        <p class="text-[9px] font-black text-slate-400 mt-1 uppercase tracking-widest">Waiting for scans…</p>
                    </div>
                @endforelse
            </div>

            {{-- Grand total footer --}}
            @if(count($cart) > 0)
            <div class="mt-sm pt-sm border-t border-slate-200 dark:border-slate-800">
                <div class="flex justify-between items-end">
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Grand Total Qty</p>
                    <p class="text-lg font-black tracking-tighter text-on-surface leading-none">{{ number_format(collect($cart)->sum('qty')) }}</p>
                </div>
            </div>
            @endif
        </div>{{-- /RIGHT PANEL --}}

    </div>

    {{-- =============================================
         STICKY BOTTOM BAR  (desktop md+)
    ================================================ --}}
    <div class="fixed bottom-0 left-0 lg:left-[84px] right-0 bg-white/95 dark:bg-slate-900/95 backdrop-blur-xl px-md py-sm flex items-center justify-between shadow-md z-50 border-t border-slate-200 dark:border-slate-800">
        {{-- Left actions --}}
        <div class="flex gap-sm">
            <button class="flex items-center justify-center gap-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 h-11 px-4 rounded-md font-bold text-xs uppercase tracking-wide border border-slate-200 dark:border-slate-800 active:scale-95 transition-all">
                <span class="material-symbols-outlined text-lg">print</span>
                Print Labels
            </button>
            <div class="flex items-center gap-2 bg-slate-50 dark:bg-slate-800 rounded-md px-3 border border-slate-200 dark:border-slate-800 shadow-sm text-xs">
                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Ref PO:</span>
                <input wire:model.blur="reference" class="bg-transparent border-none text-xs font-bold w-32 focus:ring-0 text-slate-800 dark:text-slate-200 placeholder:text-slate-400" placeholder="e.g. PO-49219"/>
            </div>
        </div>

        {{-- Right: session info + submit --}}
        <div class="flex gap-md items-center">
            <div class="text-right hidden xl:block">
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Operator Session</p>
                <p class="text-xs font-black text-on-surface" x-text="activeReceiptCode ? activeReceiptCode : 'Terminal 01 • Active'"></p>
            </div>

            @if(count($cart) > 0)
                <button wire:click="submit"
                        class="green-action-gradient text-white h-11 px-6 rounded-md font-black text-xs uppercase tracking-wider shadow-md hover:scale-[1.02] active:scale-[0.98] transition-all flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-lg">publish</span>
                    SUBMIT STOCK IN
                </button>
            @else
                <button class="bg-slate-100 dark:bg-slate-800 text-slate-400 h-11 px-6 rounded-md font-black text-xs uppercase tracking-wider cursor-not-allowed opacity-50 flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-lg">publish</span>
                    SUBMIT STOCK IN
                </button>
            @endif
        </div>
    </div>

    {{-- =============================================
         ⚠️ SINGLE ACTIVE SESSION RESUMPTION MODAL
    ================================================ --}}
    @if($showResumeModal)
    <div class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/40 backdrop-blur-sm"
         x-data="{}"
         x-init="setTimeout(() => { document.getElementById('btn-resume-session')?.focus(); }, 150)"
         @keydown.window.enter.prevent="$wire.resumeSession()"
         @keydown.window.escape.prevent="$wire.discardSession()">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-2xl w-[calc(100vw-32px)] sm:w-[460px] sm:min-w-[420px] sm:max-w-[520px] flex-shrink-0 text-center relative z-50">
            <div class="w-12 h-12 bg-amber-50 dark:bg-amber-950/20 text-amber-500 rounded-full flex items-center justify-center mx-auto mb-4">
                <span class="material-symbols-outlined text-2xl font-bold" style="font-variation-settings: 'FILL' 1;">warning</span>
            </div>
            <h3 class="text-xs font-black uppercase tracking-widest text-slate-850 dark:text-white leading-tight">Receiving Session Exists</h3>
            <p class="text-[10px] text-slate-400 mt-2 font-semibold">
                Resume previous inbound session<br>
                or discard and start fresh?
            </p>
            <div class="mt-6 flex flex-col sm:flex-row gap-3 w-full">
                <button wire:click="resumeSession" id="btn-resume-session" type="button"
                        class="flex-1 h-11 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-xs font-black uppercase tracking-wider shadow-md active:scale-95 transition-all flex items-center justify-center outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 cursor-pointer">
                    Resume Session
                </button>
                <button wire:click="discardSession" type="button"
                        class="flex-1 h-11 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-800 text-slate-655 dark:text-slate-400 rounded-md text-[10px] font-bold uppercase tracking-widest active:scale-95 transition-all flex items-center justify-center outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 cursor-pointer">
                    Start Fresh
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- =============================================
         JS: audio beep + focus helpers
    ================================================ --}}
    <script>
        // 📊 Operational Client-Side Scanner Telemetry
        window.__WMS_SCANNER_DEBUG = {
            avgScanTimeMs: 0,
            scanCount: 0,
            totalScanTimeMs: 0,
            parserFailures: 0,
            duplicateBlocks: 0,
            invalidFormatRejects: 0,
            autofocusRecoveries: 0,
            logScan(duration) {
                this.scanCount++;
                this.totalScanTimeMs += duration;
                this.avgScanTimeMs = Math.round(this.totalScanTimeMs / this.scanCount);
                console.log("[WMS INBOUND SCANNER TELEMETRY]", this);
            }
        };

        const registerScannerEngine = () => {
            Alpine.data('scannerEngine', () => ({
                engineMode: localStorage.getItem('wms_scanner_engine') || 'enhanced',
                volume: localStorage.getItem('wms_scanner_volume') || 'normal',
                barcodeText: '',
                isFocused: true,
                flashSuccess: false,
                flashError: false,
                invalidFormatMessage: '',
                showMomentum: false,

                // Temporary debug telemetry fields
                debugLastInput: '',
                debugDispatch: 'PENDING',
                debugLookup: 'PENDING',
                debugDuplicateBlock: 'NO',

                momentumData: {
                    name: '',
                    sku: '',
                    qty: 0,
                    photo: '',
                    bin: ''
                },
                focusCooldown: false,
                lastInputTime: 0,
                lastScan: {
                    barcode: '',
                    qty: 0,
                    timestamp: 0
                },
                audioCtx: null,
                momentumTimer: null,
                activeReceiptCode: '{{ $activeReceipt ? $activeReceipt->receipt_code : "" }}',

                tabId: 'TAB_' + Math.random().toString(36).substring(2, 9).toUpperCase(),
                governanceStatus: 'ACTIVE',
                activeOwnerTabId: '',
                heartbeatInterval: null,
                watchdogInterval: null,
                terminalId: localStorage.getItem('wms_terminal_id') || 'SPAREPART-DESK-A',

                claimOwnership() {
                    const activeKey = 'wms_active_in';
                    const timeKey = 'wms_time_in';
                    const ownerNameKey = 'wms_owner_name_in';
                    const now = Date.now();

                    const activeTab = localStorage.getItem(activeKey);
                    const lastHeartbeat = parseInt(localStorage.getItem(timeKey) || '0', 10);

                    if (activeTab && activeTab !== this.tabId && (now - lastHeartbeat) < 10000) {
                        // Another tab is active and healthy (under 10s takeover threshold)
                        this.switchToMonitorMode();
                    } else {
                        // Claim ownership
                        localStorage.setItem(activeKey, this.tabId);
                        localStorage.setItem(timeKey, now.toString());
                        localStorage.setItem(ownerNameKey, '{{ auth()->user()->name }}');
                        this.switchToActiveMode();
                    }
                },

                sendHeartbeat() {
                    const activeKey = 'wms_active_in';
                    const timeKey = 'wms_time_in';

                    if (localStorage.getItem(activeKey) === this.tabId) {
                        localStorage.setItem(timeKey, Date.now().toString());
                    }
                },

                evaluateTabHealth() {
                    const activeKey = 'wms_active_in';
                    const timeKey = 'wms_time_in';
                    const ownerNameKey = 'wms_owner_name_in';
                    const now = Date.now();

                    const activeTab = localStorage.getItem(activeKey);
                    const lastHeartbeat = parseInt(localStorage.getItem(timeKey) || '0', 10);
                    const elapsed = now - lastHeartbeat;

                    this.activeOwnerTabId = activeTab || 'NONE';

                    if (activeTab !== this.tabId) {
                        if (elapsed < 5000) {
                            this.governanceStatus = 'MONITOR';
                        } else if (elapsed >= 5000 && elapsed < 10000) {
                            this.governanceStatus = 'UNSTABLE';
                        } else {
                            this.governanceStatus = 'TAKEOVER_ELIGIBLE';
                        }
                    } else {
                        this.governanceStatus = 'ACTIVE';
                    }
                },

                forceTakeover() {
                    const activeKey = 'wms_active_in';
                    const timeKey = 'wms_time_in';
                    const ownerNameKey = 'wms_owner_name_in';
                    const prevOwner = localStorage.getItem(ownerNameKey) || 'Unknown Operator';

                    localStorage.setItem(activeKey, this.tabId);
                    localStorage.setItem(timeKey, Date.now().toString());
                    localStorage.setItem(ownerNameKey, '{{ auth()->user()->name }}');
                    
                    this.switchToActiveMode();

                    // Log takeover override to the database
                    @this.call('logTakeover', prevOwner, '{{ auth()->user()->name }}', this.terminalId);
                },

                switchToMonitorMode() {
                    this.governanceStatus = 'MONITOR';
                    const inputEl = document.getElementById('barcode-input');
                    if (inputEl) inputEl.disabled = true;
                },

                switchToActiveMode() {
                    this.governanceStatus = 'ACTIVE';
                    const inputEl = document.getElementById('barcode-input');
                    if (inputEl) {
                        inputEl.disabled = false;
                        this.forceFocus();
                    }
                },

                init() {
                    console.log("ALPINE ENGINE INITIALIZED");
                    console.log("[WMS Inbound Scanner] Bootstrapped in " + this.engineMode.toUpperCase() + " mode.");
                    
                    // Initialize Tab Governance heartbeats
                    this.claimOwnership();
                    this.heartbeatInterval = setInterval(() => { this.sendHeartbeat(); }, 2000);
                    this.watchdogInterval = setInterval(() => { this.evaluateTabHealth(); }, 2000);

                    // Listen for localStorage changes on active tab updates
                    window.addEventListener('storage', (e) => {
                        if (e.key === 'wms_active_in' && e.newValue !== this.tabId) {
                            this.switchToMonitorMode();
                        }
                    });
                    
                    // iOS user interaction triggers to unlock AudioContext
                    const unlockAudio = () => {
                        this.bootstrapAudio();
                        document.removeEventListener('click', unlockAudio);
                        document.removeEventListener('touchstart', unlockAudio);
                    };
                    document.addEventListener('click', unlockAudio);
                    document.addEventListener('touchstart', unlockAudio);

                    // Focus tracking
                    const inputEl = document.getElementById('barcode-input');
                    if (inputEl) {
                        inputEl.addEventListener('focus', () => { this.isFocused = true; });
                        inputEl.addEventListener('blur', () => { this.isFocused = false; });
                    }

                    // Livewire Integration Listeners
                    window.addEventListener('scan-success', (e) => {
                        const data = e.detail[0] || e.detail;
                        this.triggerSuccess(data);
                    });

                    window.addEventListener('scan-failed', (e) => {
                        const data = e.detail[0] || e.detail;
                        this.triggerError(data.message || 'Scan failed');
                    });

                    window.addEventListener('focus-barcode-input', () => {
                        this.forceFocus();
                    });
                },

                toggleEngine() {
                    this.engineMode = this.engineMode === 'enhanced' ? 'legacy' : 'enhanced';
                    localStorage.setItem('wms_scanner_engine', this.engineMode);
                    this.playAudio('success');
                    if (typeof Notyf !== 'undefined') {
                        new Notyf().open({
                            type: 'info',
                            message: 'Inbound Scanner switched to ' + this.engineMode.toUpperCase(),
                            background: this.engineMode === 'enhanced' ? '#059669' : '#475569'
                        });
                    }
                },

                setVolume(mode) {
                    this.volume = mode;
                    localStorage.setItem('wms_scanner_volume', mode);
                    this.playAudio('success');
                },

                bootstrapAudio() {
                    if (this.audioCtx) return;
                    try {
                        const AudioCtx = window.AudioContext || window.webkitAudioContext;
                        if (AudioCtx) {
                            this.audioCtx = new AudioCtx();
                        }
                    } catch (e) {
                        console.warn("Web Audio API not supported", e);
                    }
                },

                playAudio(type) {
                    if (this.volume === 'mute') return;
                    this.bootstrapAudio();
                    if (!this.audioCtx) return;

                    if (this.audioCtx.state === 'suspended') {
                        this.audioCtx.resume();
                    }

                    const vol = this.volume === 'low' ? 0.02 : 0.08;

                    if (type === 'success') {
                        const osc = this.audioCtx.createOscillator();
                        const gain = this.audioCtx.createGain();
                        osc.connect(gain);
                        gain.connect(this.audioCtx.destination);
                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(880, this.audioCtx.currentTime);
                        gain.gain.setValueAtTime(vol, this.audioCtx.currentTime);
                        gain.gain.exponentialRampToValueAtTime(0.001, this.audioCtx.currentTime + 0.05);
                        osc.start();
                        osc.stop(this.audioCtx.currentTime + 0.05);
                    } else if (type === 'error') {
                        const playBuzz = (delay) => {
                            const osc = this.audioCtx.createOscillator();
                            const gain = this.audioCtx.createGain();
                            osc.connect(gain);
                            gain.connect(this.audioCtx.destination);
                            osc.type = 'triangle';
                            osc.frequency.setValueAtTime(180, this.audioCtx.currentTime + delay);
                            gain.gain.setValueAtTime(vol * 1.5, this.audioCtx.currentTime + delay);
                            gain.gain.exponentialRampToValueAtTime(0.001, this.audioCtx.currentTime + delay + 0.06);
                            osc.start(this.audioCtx.currentTime + delay);
                            osc.stop(this.audioCtx.currentTime + delay + 0.06);
                        };
                        playBuzz(0);
                        playBuzz(0.09);
                    }
                },

                handleScanInput() {
                    console.log("SCAN ENTER DETECTED");
                    const raw = this.barcodeText.trim();
                    console.log("[WMS STOCK IN] Raw scan input registered:", raw);
                    if (!raw) return;

                    // Update debug telemetry states
                    this.debugLastInput = raw;
                    this.debugDuplicateBlock = 'NO';
                    this.debugDispatch = 'PENDING';
                    this.debugLookup = 'PENDING';

                    // Strict Inbound Regex Parser: BARCODE*QTY
                    let barcodeVal = raw;
                    let qtyVal = 1;

                    if (raw.includes('*')) {
                        const match = raw.match(/^([a-zA-Z0-9_-]+)\*(\d+)$/);
                        if (!match) {
                            console.warn("[WMS STOCK IN] Enhanced parsing failed (invalid * separator structure):", raw);
                            this.triggerInvalidFormat();
                            return;
                        }
                        barcodeVal = match[1];
                        qtyVal = parseInt(match[2], 10);
                    }

                    console.log("[WMS STOCK IN] Parsed values - Barcode:", barcodeVal, "Qty:", qtyVal);

                    if (!barcodeVal || qtyVal <= 0 || isNaN(qtyVal)) {
                        console.warn("[WMS STOCK IN] Parsed values validation failed:", { barcodeVal, qtyVal });
                        this.triggerInvalidFormat();
                        return;
                    }

                    // Duplicate guard check (400ms threshold)
                    const now = Date.now();
                    if (barcodeVal === this.lastScan.barcode && 
                        qtyVal === this.lastScan.qty && 
                        (now - this.lastScan.timestamp) < 400) {
                        
                        console.warn("[WMS STOCK IN] Duplicate scan blocked by temporal duplicate block (400ms):", barcodeVal);
                        this.debugDuplicateBlock = 'YES';
                        window.__WMS_SCANNER_DEBUG.duplicateBlocks++;
                        this.playAudio('error');
                        this.barcodeText = '';
                        return;
                    }

                    this.lastScan = {
                        barcode: barcodeVal,
                        qty: qtyVal,
                        timestamp: now
                    };

                    this.barcodeText = ''; // Clear DOM immediately

                    // Direct backend invocation bypassing the custom dispatch bus
                    const start = Date.now();
                    console.log("[WMS STOCK IN] Executing direct Livewire call: submitScan(" + barcodeVal + ", " + qtyVal + ")");
                    this.debugDispatch = 'DISPATCHED';
                    
                    @this.call('submitScan', barcodeVal, qtyVal);
                    
                    window.__WMS_SCANNER_DEBUG.logScan(Date.now() - start);
                },

                triggerInvalidFormat() {
                    this.invalidFormatMessage = '❌ INVALID FORMAT - Use BARCODE*QTY (e.g. 89912345*10)';
                    console.warn("[WMS STOCK IN] Scanner rejected input format:", this.barcodeText);
                    this.debugDispatch = 'FAILED';
                    this.debugLookup = 'PENDING';
                    window.__WMS_SCANNER_DEBUG.invalidFormatRejects++;
                    this.playAudio('error');
                    this.barcodeText = '';
                    
                    setTimeout(() => {
                        this.invalidFormatMessage = '';
                    }, 2000);
                },

                triggerSuccess(data) {
                    console.log("[WMS STOCK IN] Successfully processed scan in backend:", data);
                    this.debugDispatch = 'SUCCESS';
                    this.debugLookup = 'FOUND';
                    this.playAudio('success');
                    
                    this.flashSuccess = true;
                    setTimeout(() => { this.flashSuccess = false; }, 150);

                    if (navigator.vibrate) navigator.vibrate(60);

                    this.momentumData = {
                        name: data.name,
                        sku: data.sku,
                        qty: data.qty,
                        photo: data.photo,
                        bin: data.bin
                    };

                    this.showMomentum = true;

                    if (this.momentumTimer) clearTimeout(this.momentumTimer);
                    this.momentumTimer = setTimeout(() => {
                        this.showMomentum = false;
                    }, 1500);

                    this.forceFocus();
                },

                triggerError(message) {
                    console.error("[WMS STOCK IN] Backend processing failed for scan:", message);
                    this.debugDispatch = 'FAILED';
                    this.debugLookup = 'NOT FOUND';
                    this.playAudio('error');

                    this.flashError = true;
                    setTimeout(() => { this.flashError = false; }, 150);

                    if (navigator.vibrate) navigator.vibrate([80, 50, 80]);

                    if (typeof Notyf !== 'undefined') {
                        new Notyf().error(message);
                    }

                    this.forceFocus();
                },

                recoverFocus() {
                    if (this.focusCooldown) return;

                    const active = document.activeElement;
                    if (active && (
                        active.tagName === 'SELECT' || 
                        (active.tagName === 'INPUT' && active.id !== 'barcode-input') || 
                        active.tagName === 'TEXTAREA'
                    )) {
                        return; // Retain active dropdown cursor select
                    }

                    this.forceFocus();
                },

                forceFocus() {
                    const inputEl = document.getElementById('barcode-input');
                    if (inputEl) {
                        this.focusCooldown = true;
                        inputEl.focus();
                        inputEl.select();
                        
                        setTimeout(() => {
                            this.focusCooldown = false;
                        }, 200);
                    }
            }));
        };

        if (window.Alpine) {
            registerScannerEngine();
        } else {
            document.addEventListener('alpine:init', registerScannerEngine);
        }

        // Camera Scanner Helpers
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
                    window.stopScanner();
                    @this.dispatch('barcode-scanned', { barcode: decodedText, qty: 1 });
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

        // 🔌 Industrial Hard Vanilla JS Keydown Fallback Listener (Delegated at document level for maximum morph durability)
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && e.target && e.target.id === 'barcode-input') {
                const inputEl = e.target;
                // Check if Alpine has handled this. If Alpine was successfully bootstrapped and x-data scannerEngine is running,
                // we let handleScanInput() do its direct invocation. Otherwise, we fallback to hard vanilla Livewire trigger.
                const alpineRunning = !!(inputEl.__x || (window.Alpine && window.Alpine.discover && window.Alpine.discover(inputEl)));
                
                if (!alpineRunning) {
                    e.preventDefault();
                    console.log("SCAN ENTER DETECTED - [VANILLA HARD FALLBACK ACTIVE]");
                    const raw = inputEl.value.trim();
                    if (raw) {
                        // Find closest Livewire component
                        const lwEl = inputEl.closest('[wire\\:id]');
                        if (lwEl) {
                            const lwId = lwEl.getAttribute('wire:id');
                            const lwComponent = window.Livewire.find(lwId);
                            if (lwComponent) {
                                console.log("[VANILLA FALLBACK] Direct submitScan call via Livewire context for raw barcode:", raw);
                                
                                // Parse standard shorthand format (BARCODE*QTY)
                                let barcodeVal = raw;
                                let qtyVal = 1;
                                if (raw.includes('*')) {
                                    const match = raw.match(/^([a-zA-Z0-9_-]+)\*(\d+)$/);
                                    if (match) {
                                        barcodeVal = match[1];
                                        qtyVal = parseInt(match[2], 10);
                                    }
                                }
                                
                                lwComponent.call('submitScan', barcodeVal, qtyVal);
                                inputEl.value = '';
                            }
                        }
                    }
                }
            }
        });
    </script>
</div>
