<div class="pt-[52px] px-md pb-md min-h-screen bg-slate-50/30" x-data="scannerEngine()" @click="recoverFocus()">

    <!-- Industrial Overlay Confirmation Flashes -->
    <div x-show="flashSuccess" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 pointer-events-none border-[12px] border-emerald-500/40 z-[9999]" style="display: none;"></div>
    <div x-show="flashError" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 pointer-events-none border-[12px] border-red-500/40 z-[9999]" style="display: none;"></div>

    @if($isSubmitted)
    {{-- ══════════════════════════════════════════
         SUCCESS / PRINT SCREEN
    ══════════════════════════════════════════════ --}}
    <div class="max-w-2xl mx-auto mt-6 text-center space-y-md animate-in fade-in zoom-in duration-500">
        <div class="w-16 h-16 bg-emerald-100 dark:bg-emerald-950/20 text-emerald-600 rounded-full flex items-center justify-center mx-auto shadow-md">
            <span class="material-symbols-outlined text-4xl" style="font-variation-settings: 'FILL' 1;">check_circle</span>
        </div>
        
        <div class="space-y-1">
            <h1 class="text-2xl font-black text-slate-900 dark:text-slate-100 tracking-tight">Transaction Confirmed!</h1>
            <p class="text-slate-550 dark:text-slate-400 font-bold uppercase tracking-widest text-[10px]">Code: <span class="text-primary">{{ $lastTransactionCode }}</span></p>
        </div>

        <div class="grid grid-cols-2 gap-sm">
            <button onclick="window.print()" class="h-11 bg-primary hover:bg-primary-fixed-variant text-white rounded-md font-black text-xs uppercase tracking-widest shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-lg">print</span>
                PRINT RECEIPT
            </button>
            <button wire:click="resetSession" class="h-11 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800 rounded-md font-black text-xs uppercase tracking-widest shadow-sm hover:bg-slate-50 dark:hover:bg-slate-800 transition-all flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-lg">add_box</span>
                NEW SESSION
            </button>
        </div>

        {{-- Mini Receipt Preview for screen --}}
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md p-md text-left shadow-sm">
            <div class="flex justify-between items-start mb-md border-b border-slate-100 dark:border-slate-800 pb-sm">
                <h2 class="font-black uppercase tracking-tighter text-sm text-slate-800 dark:text-slate-200">Transaction Summary</h2>
                <span class="text-[10px] font-mono text-slate-400">{{ now()->format('d M Y H:i') }}</span>
            </div>
            <div class="space-y-sm">
                @php
                    $submittedTrx = \App\Models\StockTransaction::with(['items', 'department', 'user'])->find($lastTransactionId);
                @endphp
                @if($submittedTrx)
                     <div class="flex justify-between text-xs">
                        <span class="text-slate-400 font-bold uppercase tracking-widest">Department</span>
                        <span class="font-black text-slate-900 dark:text-slate-250">{{ $submittedTrx->department->name }}</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-slate-400 font-bold uppercase tracking-widest">PIC</span>
                        <span class="font-black text-slate-900 dark:text-slate-250">{{ $submittedTrx->user->name }}</span>
                    </div>
                    <div class="border-t border-dashed border-slate-200 dark:border-slate-800 pt-sm mt-sm space-y-sm">
                        @foreach($submittedTrx->items as $item)
                        <div class="flex justify-between text-xs">
                            <span class="font-bold text-slate-650 dark:text-slate-350 truncate flex-1 pr-4">{{ $item->item_name_snapshot }}</span>
                            <span class="font-black text-slate-900 dark:text-slate-200 shrink-0">x{{ $item->qty }}</span>
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
    
    <!-- 🎛️ SCANNER ENGINE status control widget -->
    <div class="max-w-7xl mx-auto mb-xs flex flex-wrap items-center justify-between gap-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md py-1.5 px-md text-xs shadow-sm">
        <div class="flex items-center gap-sm">
            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Scanner Engine:</span>
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

    {{-- ── 1. SESSION HEADER (DEPT / PIC / REF) ── --}}
    <section class="max-w-7xl mx-auto mb-sm animate-in slide-in-from-top-4 duration-500">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md py-1.5 px-md shadow-sm">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-md items-center">
                
                <!-- Destination Dept — Alpine searchable combobox -->
                <div class="flex items-center gap-2 w-full">
                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest shrink-0 italic">01. Dept:</span>
                    <div class="relative flex-1"
                         x-data="wmsCombobox({
                             options: @js($departments->map(fn($d) => ['id' => $d->id, 'label' => $d->name . ' (' . $d->code . ')', 'search' => strtolower($d->name . ' ' . $d->code)])->values()),
                             initialId: {{ $deptId ? (int)$deptId : 'null' }},
                             initialLabel: '{{ $deptId && ($selDept = $departments->firstWhere('id', $deptId)) ? addslashes($selDept->name . ' (' . $selDept->code . ')') : '' }}',
                             placeholder: 'Search department...',
                             wireField: 'deptId'
                         })"
                         @click.outside="close()">
                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 material-symbols-outlined text-primary text-[16px] z-10 pointer-events-none">corporate_fare</span>

                        <!-- Text input -->
                        <input
                            type="text"
                            x-ref="input"
                            x-model="query"
                            @focus="open()"
                            @input="open()"
                            @keydown.arrow-down.prevent="focusOption(activeIndex + 1)"
                            @keydown.arrow-up.prevent="focusOption(activeIndex - 1)"
                            @keydown.enter.prevent="selectActive()"
                            @keydown.escape="close()"
                            :placeholder="selected ? selectedLabel : placeholder"
                            :class="selected ? 'text-slate-800 dark:text-slate-100 font-black' : 'text-slate-400'"
                            class="w-full h-9 pl-8 pr-7 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-md focus:ring-1 focus:ring-primary/20 focus:border-primary font-bold transition-all shadow-sm text-xs"
                            autocomplete="off"
                        />

                        <!-- Clear button -->
                        <button type="button" x-show="selected" @click.stop="clear()" tabindex="-1"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-350 hover:text-slate-600 dark:hover:text-slate-300 transition-colors"
                            style="display:none;">
                            <span class="material-symbols-outlined text-[14px]">close</span>
                        </button>

                        <!-- Dropdown -->
                        <ul x-show="isOpen && filtered.length > 0"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute left-0 right-0 top-10 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-md shadow-xl z-50 max-h-52 overflow-y-auto"
                            style="display:none;">
                            <template x-for="(opt, idx) in filtered" :key="opt.id">
                                <li @click="select(opt)"
                                    @mouseenter="activeIndex = idx"
                                    :class="activeIndex === idx ? 'bg-primary/10 text-primary' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800'"
                                    class="px-3 py-2 text-xs font-bold cursor-pointer transition-colors flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[13px] opacity-50">corporate_fare</span>
                                    <span x-text="opt.label"></span>
                                </li>
                            </template>
                        </ul>

                        <!-- No results -->
                        <div x-show="isOpen && query.length > 0 && filtered.length === 0"
                             class="absolute left-0 right-0 top-10 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-md shadow-xl z-50 px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest"
                             style="display:none;">No match found</div>
                    </div>
                </div>

                <!-- Recipient PIC — Alpine searchable combobox -->
                <div class="flex items-center gap-2 w-full">
                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest shrink-0 italic">02. PIC:</span>
                    {{-- wire:key forces Alpine to re-init when department changes and Livewire re-renders the PIC list --}}
                    <div class="relative flex-1"
                         wire:key="pic-combobox-{{ $deptId }}"
                         x-data="wmsCombobox({
                             options: @js(collect($availablePics)->map(fn($p) => ['id' => $p->id, 'label' => $p->name, 'search' => strtolower($p->name)])->values()),
                             initialId: {{ $picId ? (int)$picId : 'null' }},
                             initialLabel: '{{ $picId && ($selPic = collect($availablePics)->firstWhere('id', $picId)) ? addslashes($selPic->name) : '' }}',
                             placeholder: 'Search PIC...',
                             wireField: 'picId',
                             disabled: {{ collect($availablePics)->isEmpty() ? 'true' : 'false' }}
                         })"
                         @click.outside="close()">
                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 material-symbols-outlined text-primary text-[16px] z-10 pointer-events-none">person</span>

                        <!-- Text input -->
                        <input
                            type="text"
                            x-ref="input"
                            x-model="query"
                            @focus="open()"
                            @input="open()"
                            @keydown.arrow-down.prevent="focusOption(activeIndex + 1)"
                            @keydown.arrow-up.prevent="focusOption(activeIndex - 1)"
                            @keydown.enter.prevent="selectActive()"
                            @keydown.escape="close()"
                            :placeholder="selected ? selectedLabel : placeholder"
                            :class="selected ? 'text-slate-800 dark:text-slate-100 font-black' : 'text-slate-400'"
                            :disabled="disabled"
                            class="w-full h-9 pl-8 pr-7 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-md focus:ring-1 focus:ring-primary/20 focus:border-primary font-bold transition-all shadow-sm text-xs disabled:opacity-50 disabled:cursor-not-allowed"
                            autocomplete="off"
                        />

                        <!-- Clear button -->
                        <button type="button" x-show="selected && !disabled" @click.stop="clear()" tabindex="-1"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-350 hover:text-slate-600 dark:hover:text-slate-300 transition-colors"
                            style="display:none;">
                            <span class="material-symbols-outlined text-[14px]">close</span>
                        </button>

                        <!-- Dropdown -->
                        <ul x-show="isOpen && filtered.length > 0"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute left-0 right-0 top-10 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-md shadow-xl z-50 max-h-52 overflow-y-auto"
                            style="display:none;">
                            <template x-for="(opt, idx) in filtered" :key="opt.id">
                                <li @click="select(opt)"
                                    @mouseenter="activeIndex = idx"
                                    :class="activeIndex === idx ? 'bg-primary/10 text-primary' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800'"
                                    class="px-3 py-2 text-xs font-bold cursor-pointer transition-colors flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[13px] opacity-50">person</span>
                                    <span x-text="opt.label"></span>
                                </li>
                            </template>
                        </ul>

                        <!-- No results -->
                        <div x-show="isOpen && query.length > 0 && filtered.length === 0"
                             class="absolute left-0 right-0 top-10 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-md shadow-xl z-50 px-3 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest"
                             style="display:none;">No PIC found</div>
                    </div>
                </div>

                <!-- Machine / Work Order Reference -->
                <div class="flex items-center gap-2 w-full">
                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest shrink-0 italic">03. Ref:</span>
                    <div class="relative flex-1">
                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 material-symbols-outlined text-primary text-[16px]">manufacturing</span>
                        <input wire:model="reference" placeholder="e.g. MCH-012, WO-492" class="w-full h-9 pl-8 pr-2 bg-slate-50 border border-slate-200 dark:border-slate-850 rounded-md focus:ring-1 focus:ring-primary/20 focus:border-primary font-bold text-slate-700 dark:text-slate-200 transition-all shadow-sm text-xs py-1" type="text"/>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <div class="grid grid-cols-12 gap-md max-w-[1600px] mx-auto">
        {{-- ── 2. LEFT PANEL: SCAN AREA (8 Cols on Desktop) ── --}}
        <section class="col-span-12 lg:col-span-7 xl:col-span-8 space-y-md min-w-0">

            @if($message)
            <div class="{{ $messageType === 'success' ? 'bg-emerald-55/10 border-emerald-500' : 'bg-red-55/10 border-red-500' }} border-l-4 p-sm rounded-md flex items-center gap-sm shadow-sm animate-in fade-in slide-in-from-left-2 transition-all">
                <span class="material-symbols-outlined {{ $messageType === 'success' ? 'text-emerald-500' : 'text-red-500' }} text-lg" style="font-variation-settings: 'FILL' 1;">
                    {{ $messageType === 'success' ? 'check_circle' : 'error' }}
                </span>
                <p class="text-xs font-bold text-slate-850 dark:text-slate-100">{{ $message }}</p>
            </div>
            @endif

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md p-sm shadow-sm ready-to-scan-glow">
                <div class="flex items-center gap-sm">
                    <div class="flex-1 relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-emerald-600 text-lg material-symbols-outlined">barcode_scanner</span>
                        <input
                            x-model="barcodeText"
                            @keydown.enter.prevent="handleScanInput()"
                            id="barcode-input"
                            autofocus
                            class="w-full h-11 pl-10 pr-4 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-850 rounded-md focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 text-sm font-black placeholder:text-slate-400 transition-all font-mono text-on-surface animate-pulse"
                            placeholder="READY TO SCAN PHYSICAL BARCODE..."
                            type="text"/>
                        <div x-show="invalidFormatMessage" x-transition.opacity.duration.150ms class="absolute left-0 right-0 top-[48px] bg-red-500 text-white rounded-md text-[10px] font-black uppercase tracking-widest text-center py-1 z-20 shadow-md" x-text="invalidFormatMessage" style="display: none;"></div>
                    </div>
                    <div class="flex items-center gap-sm shrink-0">
                        <button wire:click="submitScan" class="bg-emerald-600 hover:bg-emerald-700 text-white w-11 h-11 rounded-md shadow-md flex items-center justify-center active:scale-95 transition-all">
                            <span class="material-symbols-outlined text-lg">keyboard_return</span>
                        </button>
                        <button onclick="startScanner()" type="button" class="bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 w-11 h-11 rounded-md flex items-center justify-center active:scale-95 transition-all outline-none">
                            <span class="material-symbols-outlined text-lg font-black">photo_camera</span>
                        </button>
                    </div>
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

            {{-- Camera Scanner Overlay --}}
            <div id="scanner-container" class="hidden mt-2 bg-black rounded-md overflow-hidden relative border border-slate-200 dark:border-slate-800 shadow-lg" wire:ignore>
                <div id="reader" style="width: 100%;"></div>
                <button type="button" onclick="stopScanner()" class="absolute top-3 right-3 bg-red-600 text-white px-4 h-11 text-xs font-black rounded-md shadow-lg z-50 hover:bg-red-700 flex items-center gap-2 transition-all">
                    <span class="material-symbols-outlined text-sm">close</span> CANCEL SCAN
                </button>
            </div>

            @if($currentItem)
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md shadow-sm overflow-hidden success-flash animate-in zoom-in-95 duration-300 flex flex-col sm:flex-row">
                <div class="sm:w-20 sm:h-20 shrink-0 bg-slate-100 dark:bg-slate-800 relative group overflow-hidden border-b sm:border-b-0 sm:border-r border-slate-200 dark:border-slate-800 flex items-center justify-center p-1">
                    <img alt="Product" class="w-full h-full object-cover rounded-md" src="{{ $currentItem->images->where('is_primary', true)->first() ? asset('storage/' . $currentItem->images->where('is_primary', true)->first()->path) : asset('images/placeholders/item.svg') }}"/>
                </div>
                <div class="flex-1 p-2 flex flex-col justify-between gap-sm">
                    <div class="flex justify-between items-start gap-sm">
                        <div class="min-w-0 flex-1">
                            <h2 class="text-xs font-black tracking-tight text-slate-900 dark:text-slate-100 leading-tight truncate">{{ $currentItem->item->name }}</h2>
                            <p class="text-[9px] font-mono-scannable text-primary uppercase mt-0.5 tracking-wider">{{ $currentItem->sku }}</p>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest block">Stock Bin</span>
                            <span class="text-xs font-mono-scannable text-slate-900 dark:text-slate-100 leading-none">{{ \App\Models\Bin::where('item_variant_id', $currentItem->id)->sum('current_qty') }} <span class="text-[9px] text-slate-400">{{ $currentItem->unit }}</span></span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-2 mt-1">
                        <div class="flex items-center gap-1 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-800 rounded-md p-0.5 shrink-0">
                            <button wire:click="$set('qty', {{ $qty > 1 ? $qty - 1 : 1 }})" class="w-8 h-8 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-750 text-primary rounded-md shadow-sm hover:translate-y-[-1px] active:translate-y-0 transition-all flex items-center justify-center font-bold text-sm">
                                -
                            </button>
                            <input wire:model="qty" class="w-12 text-center bg-transparent border-none focus:ring-0 text-xs font-black text-slate-900 dark:text-slate-100" type="number"/>
                            <button wire:click="$set('qty', {{ (int)$qty + 1 }})" class="w-8 h-8 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-750 text-primary rounded-md shadow-sm hover:translate-y-[-1px] active:translate-y-0 transition-all flex items-center justify-center font-bold text-sm">
                                +
                            </button>
                        </div>
                        <button wire:click="addToCart" class="h-9 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md font-black text-[10px] uppercase tracking-widest shadow-md flex items-center justify-center gap-2 transition-all">
                            <span class="material-symbols-outlined text-sm">add_shopping_cart</span>
                            COMMIT BATCH
                        </button>
                    </div>
                </div>
            </div>
            @endif
        </section>

        {{-- ── 3. RIGHT PANEL: CART BATCH (4 Cols on Desktop) ── --}}
        <aside class="col-span-12 lg:col-span-5 xl:col-span-4 flex flex-col bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md overflow-hidden shadow-sm sticky top-[60px] relative" style="max-height: calc(100vh - 76px);">
            
            <!-- ⚡ LAST SCANNED MOMENTUM PANEL (Alpine Overlay) -->
            <div x-show="showMomentum" 
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="absolute inset-0 bg-slate-900 text-white z-50 flex flex-col items-center justify-center p-md text-center"
                 style="display: none;">
                <div class="w-16 h-16 rounded-lg bg-slate-800 border border-slate-700 overflow-hidden flex items-center justify-center p-1 mb-sm shadow-md">
                    <img :src="momentumData.photo" alt="Item Image" class="w-full h-full object-cover rounded-md" />
                </div>
                <div class="text-emerald-400 text-[10px] font-black uppercase tracking-widest flex items-center gap-1 justify-center">
                    <span class="material-symbols-outlined text-sm">check_circle</span> ADDED TO BATCH
                </div>
                <h4 class="text-xs font-black mt-1 leading-tight text-white px-sm" x-text="momentumData.name"></h4>
                <p class="text-[9px] font-mono mt-0.5 text-slate-400" x-text="'SKU: ' + momentumData.sku"></p>
                
                <div class="mt-md grid grid-cols-3 gap-sm w-full border-t border-slate-800 pt-md text-center px-sm">
                    <div>
                        <div class="text-[8px] font-black uppercase tracking-widest text-slate-500">Qty Added</div>
                        <div class="text-xs font-black text-emerald-400" x-text="'+' + momentumData.qty"></div>
                    </div>
                    <div>
                        <div class="text-[8px] font-black uppercase tracking-widest text-slate-500">Remaining</div>
                        <div class="text-xs font-black text-white" x-text="momentumData.remaining + ' ' + momentumData.unit"></div>
                    </div>
                    <div>
                        <div class="text-[8px] font-black uppercase tracking-widest text-slate-500">Primary Bin</div>
                        <div class="text-xs font-black text-emerald-450" x-text="momentumData.bin"></div>
                    </div>
                </div>
            </div>

            <div class="px-md py-sm border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50 dark:bg-slate-900/50 shrink-0">
                <div>
                    <h3 class="font-black text-xs uppercase tracking-tighter text-slate-850 dark:text-white">Active Batch</h3>
                    <p class="text-[9px] text-slate-400 font-black uppercase tracking-widest">Pending Movement</p>
                </div>
                <span class="bg-primary text-white font-black text-[9px] px-2 py-0.5 rounded tracking-widest uppercase">{{ count($cart) }} Rows</span>
            </div>

            <div class="flex-1 overflow-y-auto p-sm space-y-1.5 custom-scroll">
                @forelse($cart as $index => $item)
                <div class="bg-slate-50 dark:bg-slate-800/40 group p-sm rounded-md border border-slate-200 dark:border-slate-800/60 hover:border-emerald-600/20 transition-all flex items-center justify-between gap-sm relative">
                    <div class="min-w-0 flex-1">
                        <div class="font-black text-xs text-slate-850 dark:text-slate-200 leading-tight truncate">{{ $item['name'] }}</div>
                        <div class="text-[9px] font-mono-scannable text-slate-400 uppercase mt-0.5 tracking-wider">{{ $item['barcode'] }}</div>
                    </div>
                    <div class="text-right shrink-0 flex items-center gap-2">
                        <div class="text-right">
                            <div class="mb-1">
                                <input 
                                    type="number" 
                                    value="{{ $item['qty'] }}" 
                                    wire:blur="updateCartQty({{ $index }}, $event.target.value)"
                                    @keydown.enter="$el.blur()"
                                    @focus="$el.select()"
                                    class="w-16 h-7 text-center bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded focus:ring-1 focus:ring-emerald-500 font-mono text-xs font-bold text-slate-900 dark:text-slate-100"
                                    min="1"
                                />
                            </div>
                            <div class="text-[8px] font-mono text-slate-400">SKU: {{ $item['erp_code'] }}</div>
                        </div>
                        <button wire:click="removeFromCart({{ $index }})" class="material-symbols-outlined text-slate-350 hover:text-red-500 transition-colors text-[18px] outline-none">close</button>
                    </div>
                </div>
                @empty
                <div class="flex-1 flex flex-col items-center justify-center py-6 opacity-30">
                    <span class="material-symbols-outlined text-2xl mb-1 text-slate-400">shopping_basket</span>
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Cart Empty</p>
                </div>
                @endforelse
            </div>

            <div class="p-sm bg-slate-50 dark:bg-slate-900/50 border-t border-slate-200 dark:border-slate-800 space-y-sm shrink-0">
                <div class="flex justify-between items-center px-1">
                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Total Qty</span>
                    <span class="text-md font-black text-slate-900 dark:text-white">{{ collect($cart)->sum('qty') }} Units</span>
                </div>
                <button wire:click="submit"
                        class="w-full bg-emerald-600 hover:bg-emerald-700 text-white h-11 rounded-md font-black text-xs uppercase tracking-widest flex items-center justify-center gap-2 shadow-md hover:brightness-105 active:scale-95 transition-all disabled:opacity-35 disabled:pointer-events-none"
                        @if(empty($cart)) disabled @endif>
                    <span class="material-symbols-outlined text-lg" style="font-variation-settings: 'FILL' 1;">publish</span>
                    SUBMIT TRANSACTION
                </button>
            </div>
        </aside>
    </div>
    @endif

    {{-- ── PRINT RECEIPT AREA (HIDDEN) ── --}}
    <div id="print-area" class="hidden print:block fixed inset-0 bg-white z-[9999] p-8">
        @if($isSubmitted && $submittedTrx)
        <div class="max-w-[28rem] mx-auto border-2 border-black p-6 font-mono text-sm space-y-6">
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
            console.log("[WMS SCANNER TELEMETRY]", this);
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
                bin: '',
                remaining: 0,
                unit: ''
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

            tabId: 'TAB_' + Math.random().toString(36).substring(2, 9).toUpperCase(),
            governanceStatus: 'ACTIVE',
            activeOwnerTabId: '',
            heartbeatInterval: null,
            watchdogInterval: null,
            terminalId: localStorage.getItem('wms_terminal_id') || 'SPAREPART-DESK-A',

            claimOwnership() {
                const activeKey = 'wms_active_out';
                const timeKey = 'wms_time_out';
                const ownerNameKey = 'wms_owner_name_out';
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
                const activeKey = 'wms_active_out';
                const timeKey = 'wms_time_out';

                if (localStorage.getItem(activeKey) === this.tabId) {
                    localStorage.setItem(timeKey, Date.now().toString());
                }
            },

            evaluateTabHealth() {
                const activeKey = 'wms_active_out';
                const timeKey = 'wms_time_out';
                const ownerNameKey = 'wms_owner_name_out';
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
                const activeKey = 'wms_active_out';
                const timeKey = 'wms_time_out';
                const ownerNameKey = 'wms_owner_name_out';
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
                console.log("[WMS Scanner Engine] Bootstrapped in " + this.engineMode.toUpperCase() + " mode.");
                
                // Initialize Tab Governance heartbeats
                this.claimOwnership();
                this.heartbeatInterval = setInterval(() => { this.sendHeartbeat(); }, 2000);
                this.watchdogInterval = setInterval(() => { this.evaluateTabHealth(); }, 2000);

                // Listen for localStorage changes on active tab updates
                window.addEventListener('storage', (e) => {
                    if (e.key === 'wms_active_out' && e.newValue !== this.tabId) {
                        this.switchToMonitorMode();
                    }
                });
                
                // Audio Bootstrap Listeners for iOS Safari
                const unlockAudio = () => {
                    this.bootstrapAudio();
                    document.removeEventListener('click', unlockAudio);
                    document.removeEventListener('touchstart', unlockAudio);
                };
                document.addEventListener('click', unlockAudio);
                document.addEventListener('touchstart', unlockAudio);

                // Focus Tracking
                const inputEl = document.getElementById('barcode-input');
                if (inputEl) {
                    inputEl.addEventListener('focus', () => { this.isFocused = true; });
                    inputEl.addEventListener('blur', () => { this.isFocused = false; });
                }

                // Livewire Event Integration
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
                    const n = new Notyf();
                    n.open({
                        type: 'info',
                        message: 'Scanner Engine switched to ' + this.engineMode.toUpperCase(),
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

                // Resume suspended AudioContext (Safari security constraint)
                if (this.audioCtx.state === 'suspended') {
                    this.audioCtx.resume();
                }

                const vol = this.volume === 'low' ? 0.02 : 0.08;

                if (type === 'success') {
                    // Soft, short 880Hz confirmation beep (50ms duration)
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
                    // Non-alarm double buzz (180Hz) under 200ms total
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
                    playBuzz(0.09); // Second pulse after 90ms
                }
            },

            handleScanInput() {
                console.log("SCAN ENTER DETECTED");
                const raw = this.barcodeText.trim();
                console.log("[WMS SCANNER] Raw scan input registered:", raw);
                if (!raw) return;

                // Update debug telemetry states
                this.debugLastInput = raw;
                this.debugDuplicateBlock = 'NO';
                this.debugDispatch = 'PENDING';
                this.debugLookup = 'PENDING';

                // Strict Regex Parser: Enforce BARCODE*QTY
                let barcodeVal = raw;
                let qtyVal = 1;

                if (raw.includes('*')) {
                    const match = raw.match(/^([a-zA-Z0-9.\-_]+)\*(\d+)$/);
                    if (!match) {
                        console.warn("[WMS SCANNER] Enhanced parsing failed (invalid * separator structure):", raw);
                        this.triggerInvalidFormat();
                        return;
                    }
                    barcodeVal = match[1];
                    qtyVal = parseInt(match[2], 10);
                }

                console.log("[WMS SCANNER] Parsed values - Barcode:", barcodeVal, "Qty:", qtyVal);

                if (!barcodeVal || qtyVal <= 0 || isNaN(qtyVal)) {
                    console.warn("[WMS SCANNER] Parsed values validation failed:", { barcodeVal, qtyVal });
                    this.triggerInvalidFormat();
                    return;
                }

                // Duplicate Guard Prevention (400ms window)
                const now = Date.now();
                if (barcodeVal === this.lastScan.barcode && 
                    qtyVal === this.lastScan.qty && 
                    (now - this.lastScan.timestamp) < 400) {
                    
                    console.warn("[WMS SCANNER] Duplicate scan blocked by temporal duplicate block (400ms):", barcodeVal);
                    this.debugDuplicateBlock = 'YES';
                    window.__WMS_SCANNER_DEBUG.duplicateBlocks++;
                    this.playAudio('error');
                    this.barcodeText = '';
                    return;
                }

                // Update duplicate cache
                this.lastScan = {
                    barcode: barcodeVal,
                    qty: qtyVal,
                    timestamp: now
                };

                this.barcodeText = ''; // Clear input immediately!

                // Direct backend invocation bypassing the custom dispatch bus
                const start = Date.now();
                console.log("[WMS SCANNER] Executing direct Livewire call: submitScan(" + barcodeVal + ", " + qtyVal + ")");
                this.debugDispatch = 'DISPATCHED';
                
                @this.call('submitScan', barcodeVal, qtyVal);
                
                window.__WMS_SCANNER_DEBUG.logScan(Date.now() - start);
            },

            triggerInvalidFormat() {
                this.invalidFormatMessage = '❌ INVALID FORMAT - Use BARCODE*QTY (e.g. 89912345*10)';
                console.warn("[WMS SCANNER] Scanner rejected input format:", this.barcodeText);
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
                console.log("[WMS SCANNER] Successfully processed scan in backend:", data);
                this.debugDispatch = 'SUCCESS';
                this.debugLookup = 'FOUND';
                this.playAudio('success');
                
                // Blinking Overlay Success Trigger
                this.flashSuccess = true;
                setTimeout(() => { this.flashSuccess = false; }, 150);

                if (navigator.vibrate) navigator.vibrate(60);

                // Populate Last Scanned Momentum Panel
                this.momentumData = {
                    name: data.name,
                    sku: data.sku,
                    qty: data.qty,
                    photo: data.photo,
                    bin: data.bin,
                    remaining: data.remaining,
                    unit: data.unit
                };

                this.showMomentum = true;

                if (this.momentumTimer) clearTimeout(this.momentumTimer);
                this.momentumTimer = setTimeout(() => {
                    this.showMomentum = false;
                }, 1500);

                this.forceFocus();
            },

            triggerError(message) {
                console.error("[WMS SCANNER] Backend processing failed for scan:", message);
                this.debugDispatch = 'FAILED';
                this.debugLookup = 'NOT FOUND';
                this.playAudio('error');

                // Blinking Overlay Error Trigger
                this.flashError = true;
                setTimeout(() => { this.flashError = false; }, 150);

                if (navigator.vibrate) navigator.vibrate([80, 50, 80]);

                if (typeof Notyf !== 'undefined') {
                    new Notyf().error(message);
                }

                this.forceFocus();
            },

            recoverFocus() {
                // Focus Cooldown Protection to prevent browser blur loops
                if (this.focusCooldown) return;

                // Ensure focus is not inside any reference or dropdown metadata
                const active = document.activeElement;
                if (active && (
                    active.tagName === 'SELECT' || 
                    (active.tagName === 'INPUT' && active.id !== 'barcode-input') || 
                    active.tagName === 'TEXTAREA'
                )) {
                    return; // Retain active session metadata selection
                }

                this.forceFocus();
            },

            forceFocus() {
                const inputEl = document.getElementById('barcode-input');
                if (inputEl) {
                    this.focusCooldown = true;
                    inputEl.focus();
                    inputEl.select();
                    
                    // Activate Cooldown for 200ms
                    setTimeout(() => {
                        this.focusCooldown = false;
                    }, 200);
                }
            }
        }));
    };

    // ─────────────────────────────────────────────────────────────────────────
    // WMS Searchable Combobox — shared Alpine data factory
    // Used by Department and PIC fields on the Scan page.
    // Communicates back to Livewire via @this.set() preserving wire:model.live
    // behavior without adding any new JS dependency.
    // ─────────────────────────────────────────────────────────────────────────
    const registerWmsCombobox = () => {
        Alpine.data('wmsCombobox', (config) => ({
            // ── state ──
            options:       config.options      || [],
            placeholder:   config.placeholder  || 'Search...',
            wireField:     config.wireField     || '',
            disabled:      config.disabled      || false,
            selected:      config.initialId    !== null && config.initialId !== undefined ? config.initialId : null,
            selectedLabel: config.initialLabel || '',
            query:         '',
            isOpen:        false,
            activeIndex:   -1,

            // ── computed ──
            get filtered() {
                const q = this.query.trim().toLowerCase();
                if (!q) return this.options;
                return this.options.filter(opt => opt.search.includes(q));
            },

            // ── lifecycle ──
            init() {
                // When query is cleared and nothing is selected, show placeholder
                this.$watch('query', (val) => {
                    if (val === '' && this.selected) {
                        // User wiped text — treat as clear intent only on blur
                        // (we restore label on close() if no new selection)
                    }
                });
            },

            // ── methods ──
            open() {
                if (this.disabled) return;
                this.isOpen = true;
                this.activeIndex = -1;
            },

            close() {
                this.isOpen = false;
                // Restore display label if user typed but didn't pick
                this.query = '';
            },

            select(opt) {
                this.selected      = opt.id;
                this.selectedLabel = opt.label;
                this.query         = '';
                this.isOpen        = false;
                this.activeIndex   = -1;
                // Propagate to Livewire — equivalent of wire:model.live
                if (this.wireField) {
                    @this.set(this.wireField, opt.id, true); // true = defer=false → immediate
                }
                // Return scanner focus after a tick so Livewire processes the set
                setTimeout(() => {
                    const barcode = document.getElementById('barcode-input');
                    if (barcode) barcode.focus();
                }, 80);
            },

            clear() {
                this.selected      = null;
                this.selectedLabel = '';
                this.query         = '';
                this.isOpen        = false;
                if (this.wireField) {
                    @this.set(this.wireField, '', true);
                }
            },

            focusOption(idx) {
                const len = this.filtered.length;
                if (len === 0) return;
                this.activeIndex = Math.max(0, Math.min(idx, len - 1));
            },

            selectActive() {
                if (this.activeIndex >= 0 && this.filtered[this.activeIndex]) {
                    this.select(this.filtered[this.activeIndex]);
                }
            }
        }));
    };

    if (window.Alpine) {
        registerScannerEngine();
        registerWmsCombobox();
    } else {
        document.addEventListener('alpine:init', () => {
            registerScannerEngine();
            registerWmsCombobox();
        });
    }

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
                                const match = raw.match(/^([a-zA-Z0-9.\-_]+)\*(\d+)$/);
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
