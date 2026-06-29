<div class="w-full flex flex-col min-h-screen {{ $isCompactMode || $isErpTransferView ? 'p-2 bg-slate-50' : 'p-6 bg-slate-50' }}" x-data="{ showToast: false, toastMsg: '' }">
    
    <!-- Toast Notification Banner -->
    <div x-show="showToast" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-4 right-4 z-50 bg-slate-900 text-emerald-400 font-mono text-[11px] font-bold px-4 py-2.5 rounded-lg shadow-xl border border-emerald-500/20 flex items-center gap-2"
         style="display: none;">
         <span class="material-symbols-outlined text-sm">check_circle</span>
         <span x-text="toastMsg"></span>
    </div>

    <!-- Page Header (Only show if not in ultra-dense ERP mode) -->
    @if(!$isErpTransferView)
    <div class="flex items-center justify-between mb-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-black uppercase tracking-widest text-slate-400 bg-slate-200/50 px-2 py-0.5 rounded font-mono">Bridge</span>
                <span class="text-xs font-black uppercase tracking-widest text-green-700 bg-green-100 px-2 py-0.5 rounded font-mono">System of Operation</span>
            </div>
            <h1 class="text-xl font-black text-slate-900 uppercase tracking-tight mt-1">Stock Out Reports (BKB)</h1>
            <p class="text-xs text-slate-500 font-medium">Daily BKB transfer assistant to VB6 ERP. Validate, format, and copy records securely.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('reports.stock-in') }}" class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-200 hover:bg-slate-300/80 text-slate-700 rounded-lg text-[11px] font-black uppercase tracking-wider transition-all">
                <span class="material-symbols-outlined text-base">input</span>
                Stock In Report
            </a>
            <button wire:click="toggleCompactMode" class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-200 hover:bg-slate-300/80 text-slate-700 rounded-lg text-[11px] font-black uppercase tracking-wider transition-all">
                <span class="material-symbols-outlined text-base">{{ $isCompactMode ? 'fullscreen_exit' : 'density_medium' }}</span>
                {{ $isCompactMode ? 'Standard Spacing' : 'Dense Spacing' }}
            </button>
        </div>
    </div>
    @endif

    <!-- READ-ONLY KPI METRICS HEADER -->
    @if($reportGenerated && $globalTotal > 0)
    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm mb-4 flex flex-wrap items-center gap-8">
        <div>
            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block font-mono">Completed Items</span>
            <span class="text-base font-mono font-black text-slate-800">
                {{ $globalTotal - $globalRemaining }} / {{ $globalTotal }} Items 
                <span class="text-xs text-emerald-600 font-bold font-sans">({{ $globalTotal > 0 ? round((($globalTotal - $globalRemaining) / $globalTotal) * 100) : 0 }}%)</span>
            </span>
        </div>
        <div class="h-8 w-px bg-slate-200 hidden sm:block"></div>
        <div>
            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block font-mono">Remaining Items</span>
            <span class="text-base font-mono font-black text-rose-600">{{ $globalRemaining }} Items</span>
        </div>
    </div>
    @endif

    <!-- STICKY FILTER BAR -->
    <div class="bg-white border border-slate-200 rounded-xl p-3 shadow-sm mb-4 sticky top-[44px] z-30">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
            <!-- Date Filter -->
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Start Date</label>
                <input type="date" wire:model.live="startDate" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-mono font-bold text-slate-800 focus:outline-none focus:border-green-600">
            </div>
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">End Date</label>
                <input type="date" wire:model.live="endDate" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-mono font-bold text-slate-800 focus:outline-none focus:border-green-600">
            </div>

            <!-- Department Filter -->
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Department</label>
                <select wire:model.live="departmentId" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-bold text-slate-800 focus:outline-none focus:border-green-600">
                    <option value="">ALL DEPARTMENTS</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- PIC Filter -->
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">PIC (Recipient)</label>
                <select wire:model.live="picId" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-bold text-slate-800 focus:outline-none focus:border-green-600">
                    <option value="">ALL PICs</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Transaction Code -->
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Transaction Code</label>
                <input type="text" wire:model.live="code" placeholder="OUT-..." class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-mono font-bold text-slate-800 focus:outline-none focus:border-green-600">
            </div>

            <!-- Transfer Status -->
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">ERP Sync Status</label>
                <select wire:model.live="erpTransferStatus" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-bold text-slate-800 focus:outline-none focus:border-green-600">
                    <option value="">ALL STATES</option>
                    <option value="NOT_STARTED">❌ NOT STARTED (PENDING)</option>
                    <option value="COMPLETED">✅ COMPLETED (TRANSFERRED)</option>
                </select>
            </div>
        </div>

        <!-- Preset Filters Row -->
        <div class="flex items-center justify-between mt-3 pt-3 border-t border-slate-100">
            <div class="flex items-center gap-1.5">
                <button wire:click="setDatePreset('today')" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200/80 text-slate-700 rounded-md text-[10px] font-black uppercase tracking-wider transition-all font-mono font-bold">Today</button>
                <button wire:click="setDatePreset('yesterday')" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200/80 text-slate-700 rounded-md text-[10px] font-black uppercase tracking-wider transition-all font-mono font-bold">Yesterday</button>
                <button wire:click="setDatePreset('this_week')" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200/80 text-slate-700 rounded-md text-[10px] font-black uppercase tracking-wider transition-all font-mono font-bold">This Week</button>
                <button wire:click="setDatePreset('this_month')" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200/80 text-slate-700 rounded-md text-[10px] font-black uppercase tracking-wider transition-all font-mono font-bold">This Month</button>
            </div>

            <div class="flex items-center gap-2">
                @if($reportGenerated)
                    <!-- EXPORT CENTER -->
                    {{-- Hidden temporarily for item-level ERP workflow stabilization --}}
                    {{--
                    <button wire:click="toggleErpTransferView" class="flex items-center gap-1 px-3 py-1 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-[10px] font-black uppercase tracking-wider transition-all shadow-sm">
                        <span class="material-symbols-outlined text-sm">screenshare</span>
                        {{ $isErpTransferView ? 'Close ERP View' : 'ERP Transfer View Mode' }}
                    </button>
                    --}}
                    <a href="{{ route('reports.stock-out.csv', request()->query()) }}" class="flex items-center gap-1 px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[10px] font-black uppercase tracking-wider transition-all shadow-sm">
                        <span class="material-symbols-outlined text-sm">download</span>
                        Export CSV Flat
                    </a>
                    <a href="{{ route('reports.stock-out.preview', request()->query()) }}" class="flex items-center gap-1 px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-[10px] font-black uppercase tracking-wider transition-all shadow-sm">
                        <span class="material-symbols-outlined text-sm">visibility</span>
                        View Digital Receipt
                    </a>
                @else
                    <button wire:click="generateReport" 
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50"
                            class="flex items-center gap-1.5 px-4 py-2 bg-green-700 hover:bg-green-800 text-white rounded-lg text-[10px] font-black uppercase tracking-wider transition-all shadow-md hover:shadow-lg active:scale-95">
                        <span class="material-symbols-outlined text-xs">play_arrow</span>
                        Generate Report
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- FLASH MESSAGE -->
    @if(session()->has('message'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold px-4 py-2.5 rounded-lg mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-base">check_circle</span>
            {{ session('message') }}
        </div>
    @endif
    @if(session()->has('warning'))
        <div class="bg-amber-50 border border-amber-200 text-amber-800 text-xs font-bold px-4 py-2.5 rounded-lg mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-base">warning</span>
            {{ session('warning') }}
        </div>
    @endif

    @if(!$reportGenerated)
        <div class="w-full bg-white border border-slate-200 rounded-xl p-6 shadow-sm flex items-center gap-4 text-left">
            <div class="w-12 h-12 bg-slate-100 text-slate-650 rounded-full flex items-center justify-center shrink-0 shadow-inner">
                <span class="material-symbols-outlined text-2xl" style="font-variation-settings: 'FILL' 1;">analytics</span>
            </div>
            <div class="space-y-0.5">
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest font-mono">No Report Generated</h3>
                <p class="text-xs text-slate-500 font-medium">Select filters then press Generate Report.</p>
                <div class="flex items-center gap-2 mt-1.5 text-[10px] font-mono font-bold text-slate-500">
                    <span><span class="text-slate-400">RECOMMENDED:</span> 7–30 days</span>
                    <span class="text-slate-300">•</span>
                    <span><span class="text-red-500">MAXIMUM:</span> 45 days</span>
                </div>
            </div>
        </div>
    @else
        <!-- MULTI-MONITOR ERP TRANSFER VIEW COMPONENT -->
        @if($isErpTransferView)
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 mb-4 text-slate-100 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <h2 class="text-xs font-black uppercase tracking-widest font-mono text-emerald-400">VB6 ERP Dual-Monitor Entry Assistant</h2>
                </div>
                <button wire:click="toggleErpTransferView" class="text-slate-400 hover:text-slate-200 text-[10px] font-black uppercase tracking-widest bg-slate-800 px-2.5 py-1 rounded">
                    EXIT ERP MODE
                </button>
            </div>
            <p class="text-[10px] text-slate-400 font-mono mb-4 leading-relaxed bg-slate-950 p-2.5 rounded border border-slate-850">
                🚀 **RHYTHM ASSISTANT:** Set WMS browser on Left Monitor, VB6 ERP on Right Monitor. Monospace codes are padded for instant typing. Use the clipboard mode buttons below to copy columns formatted for ERP entry without mouse touch.
            </p>
        </div>
        @endif

    <!-- TRANSACTIONS CONTAINER -->
    <div class="space-y-6">
        @forelse($groupedTransactions as $deptName => $txs)
            @php
                $firstTx = $txs->first();
                $deptId = $firstTx->department_id;
                $itemsPayload = $txs->flatMap(fn($t) => $t->items->map(fn($item) => [
                    'code' => preg_replace('/^[0-9]+\./', '', $item->erp_code_snapshot ?? $item->variant->erp_code ?? ''),
                    'name' => $item->item_name_snapshot ?? $item->variant->item->name ?? '',
                    'qty' => $item->qty,
                    'unit' => $item->unit_snapshot ?? $item->variant->unit ?? 'PCS'
                ]))->toArray();
            @endphp

            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden" 
                 x-data="{ 
                    copiedFull: false, 
                    copiedCompact: false
                 }">
                
                <!-- Group Department Header -->
                <div class="bg-slate-100 border-b border-slate-200 px-4 py-2.5 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-green-600 text-white flex items-center justify-center font-bold text-xs shadow-sm">
                            <span class="material-symbols-outlined text-base">corporate_fare</span>
                        </div>
                        <div>
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Department Closed Batch</span>
                            <div class="flex items-center gap-2 mt-0.5">
                                <h2 class="text-xs font-black text-slate-800 uppercase tracking-tight leading-none">{{ $deptName }}</h2>
                                <span class="inline-flex items-center px-1.5 py-0.5 bg-slate-200 border border-slate-300 text-slate-700 text-[8px] font-black font-mono rounded">
                                    Remaining: {{ $txs->sum('remaining_items_count') }} Items
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Suggester & Copy Toolbar -->
                    <div class="flex items-center gap-2 flex-wrap md:flex-nowrap">
                        <!-- suggestions -->
                        @if($deptId)
                            <div class="flex items-center bg-white border border-slate-200 rounded-lg px-2 py-1 shadow-sm gap-1.5">
                                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 font-mono">BKB Suggester</span>
                                <input type="text" wire:model.defer="suggestedBkbRefs.{{ $deptId }}" class="bg-slate-50 border border-slate-200 rounded px-1.5 py-0.5 text-[10px] font-mono font-bold text-slate-800 w-[170px] focus:outline-none">
                            </div>
                        @endif

                        <!-- Copy Actions (Hidden temporarily for ERP item workflow stabilization) -->
                        {{--
                        <div class="flex items-center gap-1">
                            <button @click="if (copyErpLines('full', {{ e(json_encode($itemsPayload)) }})) { copiedFull = true; setTimeout(() => copiedFull = false, 1500); showToast = true; toastMsg = '✓ Copied successfully'; setTimeout(() => showToast = false, 2500); } else { showToast = true; toastMsg = '✗ Clipboard copy failed'; setTimeout(() => showToast = false, 2500); }" 
                                    class="flex items-center gap-1 px-2.5 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg text-[9px] font-black uppercase tracking-wider transition-all font-mono font-bold">
                                <span class="material-symbols-outlined text-xs">content_copy</span>
                                <span x-text="copiedFull ? 'COPIED!' : 'FULL COPY'"></span>
                            </button>
                            <button @click="if (copyErpLines('compact', {{ e(json_encode($itemsPayload)) }})) { copiedCompact = true; setTimeout(() => copiedCompact = false, 1500); showToast = true; toastMsg = '✓ Copied successfully'; setTimeout(() => showToast = false, 2500); } else { showToast = true; toastMsg = '✗ Clipboard copy failed'; setTimeout(() => showToast = false, 2500); }" 
                                    class="flex items-center gap-1 px-2.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-[9px] font-black uppercase tracking-wider transition-all font-mono font-bold">
                                <span class="material-symbols-outlined text-xs">keyboard</span>
                                <span x-text="copiedCompact ? 'COPIED!' : 'COMPACT COPY'"></span>
                            </button>
                        </div>
                        --}}

                        <!-- Mark as Closed -->
                        @if($deptId)
                            <button wire:click="completeDepartmentBatch({{ $deptId }})" 
                                    class="flex items-center gap-1 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[9px] font-black uppercase tracking-wider transition-all shadow-sm">
                                <span class="material-symbols-outlined text-xs">check_circle</span>
                                Close Department BKB
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Transaction Rows Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[9px] font-black uppercase tracking-widest text-slate-400 font-mono">
                                <th class="py-2 px-3 w-8">
                                    <input type="checkbox" class="rounded border-slate-350 text-green-600 focus:ring-green-600">
                                </th>
                                <th class="py-2 px-3">Transaction Code</th>
                                <th class="py-2 px-3">ERP Code</th>
                                <th class="py-2 px-3">Item Name</th>
                                <th class="py-2 px-3 text-right">Qty</th>
                                <th class="py-2 px-3">Unit</th>
                                <th class="py-2 px-3">PIC</th>
                                <th class="py-2 px-3">Reference (BKB)</th>
                                <th class="py-2 px-3">ERP Status</th>
                                <th class="py-2 px-3">ERP Readiness Validation</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-150">
                            @foreach($txs as $tx)
                                @foreach($tx->items as $item)
                                    @php
                                        $rawErpCode = $item->erp_code_snapshot ?? $item->variant->erp_code ?? null;
                                        $erpCode = $rawErpCode ? preg_replace('/^[0-9]+\./', '', $rawErpCode) : null;
                                        $deptExists = (bool) $tx->department_id;
                                        $whScopeExists = (bool) $tx->warehouse_id;
                                        $hasWarnings = !$rawErpCode || !$deptExists || !$whScopeExists;
                                    @endphp
                                    <tr class="hover:bg-slate-50/80 transition-colors {{ $isCompactMode ? 'text-[10px] py-1' : 'text-xs py-2' }}">
                                        <td class="px-3 {{ $isCompactMode ? 'py-1' : 'py-1.5' }}">
                                            <input type="checkbox" wire:model.live="selectedItemIds" value="{{ $item->id }}" class="rounded border-slate-350 text-green-600 focus:ring-green-600">
                                        </td>
                                        <td class="px-3 {{ $isCompactMode ? 'py-1' : 'py-1.5' }} font-mono text-slate-700">
                                            <div class="font-bold">{{ $tx->code }}</div>
                                            <div class="text-[8px] font-black text-slate-400 uppercase tracking-tight">Remaining: {{ $tx->remaining_items_count }} / {{ $tx->total_items_count }}</div>
                                        </td>
                                        <td class="px-3 {{ $isCompactMode ? 'py-1' : 'py-1.5' }} font-mono font-black text-slate-900 bg-slate-50/50 text-[11px] select-all">{{ $erpCode ?: 'N/A' }}</td>
                                        <td class="px-3 {{ $isCompactMode ? 'py-1' : 'py-1.5' }} text-slate-700 font-bold select-all">{{ $item->item_name_snapshot ?? $item->variant->item->name ?? 'N/A' }}</td>
                                        <td class="px-3 {{ $isCompactMode ? 'py-1' : 'py-1.5' }} font-mono font-black text-slate-950 text-right select-all">{{ $item->qty }}</td>
                                        <td class="px-3 {{ $isCompactMode ? 'py-1' : 'py-1.5' }} font-mono text-slate-550 font-black">{{ $item->unit_snapshot ?? $item->variant->unit ?? 'PCS' }}</td>
                                        <td class="px-3 {{ $isCompactMode ? 'py-1' : 'py-1.5' }} font-inter text-slate-600 font-bold">{{ $tx->user->name ?? 'N/A' }}</td>
                                        <td class="px-3 {{ $isCompactMode ? 'py-1' : 'py-1.5' }} font-mono text-[10px] text-slate-650 font-bold">{{ $tx->reference ?: 'WMS PENDING' }}</td>
                                        <td class="px-3 {{ $isCompactMode ? 'py-1' : 'py-1.5' }}">
                                            @if($item->erp_transfer_status === \App\Models\StockTransactionItem::ERP_COMPLETED)
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-emerald-100 text-emerald-800 text-[9px] font-black uppercase tracking-wider rounded font-mono">
                                                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                                                    COMPLETED
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-slate-100 text-slate-600 text-[9px] font-black uppercase tracking-wider rounded font-mono">
                                                    <span class="w-1.5 h-1.5 bg-slate-400 rounded-full"></span>
                                                    NOT STARTED
                                                </span>
                                            @endif
                                        </td>
                                        
                                        <!-- ERP Readiness Validation Column -->
                                        <td class="px-3 {{ $isCompactMode ? 'py-1' : 'py-1.5' }}">
                                            @if(!$hasWarnings)
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-green-100 text-green-800 text-[9px] font-black uppercase tracking-wider rounded font-mono">
                                                    <span class="material-symbols-outlined text-[10px]">check_circle</span>
                                                    READY TO SYNC
                                                </span>
                                            @else
                                                <div class="flex flex-col gap-0.5">
                                                    @if(!$rawErpCode)
                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-red-100 text-red-800 text-[8px] font-black uppercase tracking-wider rounded font-mono w-fit">
                                                            ⚠ Missing ERP Code
                                                        </span>
                                                    @endif
                                                    @if(!$deptExists)
                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-amber-100 text-amber-800 text-[8px] font-black uppercase tracking-wider rounded font-mono w-fit">
                                                            ⚠ Missing Dept Mapping
                                                        </span>
                                                    @endif
                                                    @if(!$whScopeExists)
                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-amber-100 text-amber-800 text-[8px] font-black uppercase tracking-wider rounded font-mono w-fit">
                                                            ⚠ Missing Warehouse Scope
                                                        </span>
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Footer trace summary -->
                @php
                    $lastTx = $txs->first();
                @endphp
                @if($lastTx && $lastTx->erp_transfer_status === 'COMPLETED' && $lastTx->transferredBy)
                    <div class="bg-slate-50/50 border-t border-slate-100 px-4 py-2 text-[9px] font-mono text-slate-500 flex items-center justify-between">
                        <span>🔒 TRACE: Confirmed by **{{ $lastTx->transferredBy->name }}**</span>
                        <span>Transferred At: **{{ $lastTx->transferred_at ? $lastTx->transferred_at->format('Y-m-d H:i:s') : 'N/A' }}**</span>
                    </div>
                @endif
            </div>
        @empty
            <div class="bg-white border border-slate-200 rounded-xl p-8 text-center">
                <span class="material-symbols-outlined text-4xl text-slate-300 mb-2">find_in_page</span>
                <h3 class="text-sm font-black text-slate-700 uppercase tracking-wider mb-1">No Daily Transactions Found</h3>
                <p class="text-xs text-slate-500">No confirmed completed WMS stock outs match your selected filter criteria.</p>
            </div>
        @endforelse
    </div>

    <!-- BULK STATE ACTION FLOATING CONTROLS (Only visible if rows are checked) -->
    @if(!empty($selectedItemIds))
        <div class="fixed bottom-6 left-1/2 transform -translate-x-1/2 bg-slate-900 border border-slate-800 text-white rounded-xl shadow-2xl px-5 py-3 z-40 flex items-center gap-4">
            <span class="text-[10px] font-mono font-bold text-emerald-400">{{ count($selectedItemIds) }} items selected</span>
            <div class="h-4 w-px bg-slate-800"></div>
            <div class="flex items-center gap-1.5">
                <button wire:click="updateStatusBulk('{{ \App\Models\StockTransactionItem::ERP_NOT_STARTED }}')" class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-[9px] font-black uppercase tracking-wider transition-all font-mono">
                    Reset Pending
                </button>
                <button wire:click="updateStatusBulk('{{ \App\Models\StockTransactionItem::ERP_COMPLETED }}')" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[9px] font-black uppercase tracking-wider transition-all font-mono">
                    Confirm Transferred
                </button>
            </div>
        </div>
    @endif
    @endif
</div>

    <!-- Client-side High-Performance Monospace ERP Clipboard Copier -->
    <script>
        function copyErpLines(mode, items) {
            let lines = [];
            items.forEach(item => {
                let c = item.code || '';
                let q = item.qty || '0';
                let n = item.name || '';
                let u = item.unit || 'PCS';
                let b = item.bin || '';
                if (mode === 'full') {
                    let line = c.padEnd(16, ' ') + '    ' + n.padEnd(32, ' ') + '    ' + q + ' ' + u;
                    if (b) {
                        line += '    ' + b;
                    }
                    lines.push(line);
                } else {
                    lines.push(c.padEnd(16, ' ') + '    ' + q);
                }
            });
            let text = lines.join('\n');
            
            if (navigator.clipboard && navigator.clipboard.writeText) {
                try {
                    navigator.clipboard.writeText(text);
                    return true;
                } catch (e) {
                    console.error('navigator.clipboard failed', e);
                }
            }
            
            // Insecure HTTP LAN Fallback
            let textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.opacity = "0";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            let success = false;
            try {
                success = document.execCommand('copy');
            } catch (err) {
                console.error('Fallback copy failed', err);
            }
            document.body.removeChild(textArea);
            return success;
        }
    </script>
