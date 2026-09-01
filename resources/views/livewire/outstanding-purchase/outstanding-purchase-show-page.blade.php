<div class="p-md pt-14 min-h-screen bg-slate-100/30">
    <!-- Header Navigation -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-sm mb-md bg-white dark:bg-slate-900 p-md rounded-md border border-slate-200 dark:border-slate-800 shadow-sm">
        <div class="flex items-center gap-3">
            <a href="{{ route('outstanding-purchases') }}" class="w-9 h-9 bg-slate-50 hover:bg-slate-100 border border-slate-200 dark:border-slate-800 rounded-md flex items-center justify-center text-slate-550 transition-all active:scale-95">
                <span class="material-symbols-outlined text-lg">arrow_back</span>
            </a>
            <div>
                <h2 class="text-sm font-black text-slate-900 tracking-tighter uppercase leading-none">PO: {{ $order->po_number }}</h2>
                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-1">Outstanding Purchase Order Detail</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <!-- PO status badge -->
            @if($order->is_archived)
                <span class="text-slate-500 text-[8px] font-black uppercase tracking-wider px-2.5 py-1 rounded border border-slate-200 bg-slate-50">
                    Archived
                </span>
            @endif
            @if($order->status === \App\Models\OutstandingPurchaseOrder::STATUS_PENDING)
                <span class="text-slate-500 text-[8px] font-black uppercase tracking-wider px-2.5 py-1 rounded border border-slate-200 bg-slate-50">
                    Pending
                </span>
            @elseif($order->status === \App\Models\OutstandingPurchaseOrder::STATUS_PARTIAL)
                <span class="text-amber-700 text-[8px] font-black uppercase tracking-wider px-2.5 py-1 rounded border border-amber-200 bg-amber-50">
                    Partial
                </span>
            @elseif($order->status === \App\Models\OutstandingPurchaseOrder::STATUS_CLOSED)
                <span class="text-emerald-700 text-[8px] font-black uppercase tracking-wider px-2.5 py-1 rounded border border-emerald-200 bg-emerald-50">
                    Closed / Received
                </span>
            @endif

            <!-- Readiness badge -->
            @if($order->receiving_readiness === \App\Models\OutstandingPurchaseOrder::READINESS_READY)
                <span class="bg-green-600 text-white text-[10px] font-black uppercase tracking-widest px-3.5 py-1.5 rounded shadow-sm border border-green-700">
                    Ready
                </span>
            @else
                <span class="bg-red-650 text-white text-[10px] font-black uppercase tracking-widest px-3.5 py-1.5 rounded shadow-sm border border-red-700">
                    Needs Catalog
                </span>
            @endif

            {{-- ============================================================
                 PRIMARY OPERATOR ACTION — Derived from WMS state, not ERP.
                 ERP_BEHIND is NEVER a reason to show START RECEIVING.
                 ============================================================ --}}
            @if($operatorAction === 'FULLY_RECEIVED')
                {{-- Constraint #5: pending=0 + completed sessions = FULLY RECEIVED --}}
                <div class="flex flex-col items-end gap-1">
                    <div class="h-11 px-5 text-[10px] font-black text-emerald-800 bg-emerald-50 border border-emerald-300 rounded-md shadow-sm flex items-center justify-center gap-1.5">
                        <span class="material-symbols-outlined text-sm text-emerald-600">inventory</span>
                        FULLY RECEIVED IN WMS
                    </div>
                    @if($order->erp_sync_status === 'ERP_BEHIND')
                        <span class="text-[8px] font-black text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded flex items-center gap-1">
                            <span class="material-symbols-outlined text-[9px]">sync_problem</span>
                            ERP SYNC BEHIND — WMS received ahead
                        </span>
                    @endif
                </div>

            @elseif($operatorAction === 'PARTIAL_RECEIVED')
                {{-- Constraint #6: partial WMS receiving --}}
                <div class="flex flex-col items-end gap-1">
                    @if($order->receiving_readiness === \App\Models\OutstandingPurchaseOrder::READINESS_READY)
                        <button wire:click="startReceivingSession" class="h-11 px-5 text-[10px] font-black text-white bg-amber-600 hover:bg-amber-700 rounded-md shadow-md shadow-amber-200 active:scale-95 transition-all flex items-center justify-center gap-1">
                            <span class="material-symbols-outlined text-sm">play_circle</span>
                            CONTINUE RECEIVING
                        </button>
                    @else
                        <button disabled class="h-11 px-5 text-[10px] font-black text-slate-400 bg-slate-100 border border-slate-200 rounded-md cursor-not-allowed flex items-center justify-center gap-1">
                            <span class="material-symbols-outlined text-sm">lock</span>
                            COMPLETE ITEM CATALOG FIRST
                        </button>
                    @endif
                    <span class="text-[8px] font-black text-slate-500 uppercase tracking-wider">
                        WMS Received: {{ number_format($wmsTotalReceived, 2) }} &bull; Pending: {{ number_format($wmsTotalPending, 2) }}
                    </span>
                </div>

            @elseif($operatorAction === 'RESUME_DRAFT')
                {{-- Constraint: DRAFT session — resume --}}
                <button wire:click="startReceivingSession" class="h-11 px-5 text-[10px] font-black text-white bg-blue-600 hover:bg-blue-700 rounded-md shadow-md shadow-blue-200 active:scale-95 transition-all flex items-center justify-center gap-1">
                    <span class="material-symbols-outlined text-sm">edit_note</span>
                    RESUME RECEIVING
                </button>

            @elseif($operatorAction === 'RESUME_REVIEW')
                {{-- READY_REVIEW — resume for admin review --}}
                <button wire:click="startReceivingSession" class="h-11 px-5 text-[10px] font-black text-white bg-purple-600 hover:bg-purple-700 rounded-md shadow-md shadow-purple-200 active:scale-95 transition-all flex items-center justify-center gap-1">
                    <span class="material-symbols-outlined text-sm">rate_review</span>
                    RESUME / REVIEW RECEIVING
                </button>

            @elseif($operatorAction === 'VIEW_FINALIZE')
                {{-- Constraint #8: REVIEWED session — redirect only, never create new --}}
                @if($activeSession)
                    <a href="{{ route('receiving.session', $activeSession->id) }}" class="h-11 px-5 text-[10px] font-black text-white bg-purple-700 hover:bg-purple-800 rounded-md shadow-md shadow-purple-200 active:scale-95 transition-all flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-sm">task</span>
                        VIEW / FINALIZE SESSION
                    </a>
                @endif

            @elseif($operatorAction === 'STALE_DRAFT_FULLY_RECEIVED')
                {{-- Orphan DRAFT/READY_REVIEW session on a fully-received PO — pre-fix data --}}
                @if($activeSession)
                    <div class="flex flex-col items-end gap-1">
                        <a href="{{ route('receiving.session', $activeSession->id) }}" class="h-11 px-5 text-[10px] font-black text-white bg-slate-600 hover:bg-slate-700 rounded-md shadow-md active:scale-95 transition-all flex items-center justify-center gap-1">
                            <span class="material-symbols-outlined text-sm">folder_open</span>
                            VIEW SESSION #{{ $activeSession->id }}
                        </a>
                        <span class="text-[8px] font-black text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded">
                            WMS already fully received — this session may be stale
                        </span>
                    </div>
                @endif

            @else
                {{-- Constraint #7: No session, positive pending — START RECEIVING --}}
                <div class="relative group">
                    @if($order->receiving_readiness === \App\Models\OutstandingPurchaseOrder::READINESS_READY)
                        <button wire:click="startReceivingSession" class="h-11 px-5 text-[10px] font-black text-white bg-green-600 hover:bg-green-700 rounded-md shadow-md shadow-green-200 active:scale-95 transition-all flex items-center justify-center gap-1">
                            <span class="material-symbols-outlined text-sm">play_circle</span>
                            READY FOR RECEIVING
                        </button>
                    @else
                        <button disabled class="h-11 px-5 text-[10px] font-black text-slate-400 bg-slate-100 border border-slate-200 rounded-md cursor-not-allowed flex items-center justify-center gap-1">
                            <span class="material-symbols-outlined text-sm">lock</span>
                            COMPLETE ITEM CATALOG FIRST
                        </button>
                        <div class="absolute bottom-full right-0 mb-2 hidden group-hover:block w-64 bg-slate-900 text-white text-[9px] font-black uppercase tracking-widest p-2.5 rounded shadow-xl text-center z-50">
                            {{ $order->catalog_missing_count }} item catalog {{ $order->catalog_missing_count > 1 ? 'records are' : 'record is' }} still missing.
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Flash Error --}}
    @if(session()->has('error'))
        <div class="mb-md p-md bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 rounded-md text-xs font-bold uppercase tracking-wider">
            {{ session('error') }}
        </div>
    @endif

    {{-- Active Session Banner — REVIEWED state alert --}}
    @if($activeSession && $activeSession->status === \App\Models\ReceivingSession::STATUS_REVIEWED)
        <div class="mb-md p-4 bg-purple-50 dark:bg-purple-950/20 border border-purple-300 dark:border-purple-800 rounded-md flex items-start gap-3">
            <span class="material-symbols-outlined text-purple-600 text-xl mt-0.5">pending_actions</span>
            <div>
                <p class="text-[10px] font-black text-purple-900 dark:text-purple-200 uppercase tracking-wider">Session #{{ $activeSession->id }} — Reviewed, Awaiting Final Commit</p>
                <p class="text-[9px] font-medium text-purple-700 dark:text-purple-400 mt-1">
                    This PO has an active receiving session in REVIEWED state. Signatures are optional.
                    Click <strong>VIEW / FINALIZE SESSION</strong> to proceed to the final commit or download the A4 form.
                    No new receiving session will be created for this PO until this one is finalized.
                </p>
            </div>
        </div>
    @elseif($activeSession && $activeSession->status === \App\Models\ReceivingSession::STATUS_READY_REVIEW)
        <div class="mb-md p-4 bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800 rounded-md flex items-start gap-3">
            <span class="material-symbols-outlined text-blue-600 text-xl mt-0.5">rate_review</span>
            <div>
                <p class="text-[10px] font-black text-blue-900 dark:text-blue-200 uppercase tracking-wider">Session #{{ $activeSession->id }} — Physical Checking Complete, Awaiting Review</p>
                <p class="text-[9px] font-medium text-blue-700 dark:text-blue-400 mt-1">
                    The checker has completed physical inspection. An admin must now review and confirm the session.
                </p>
            </div>
        </div>
    @elseif($operatorAction === 'STALE_DRAFT_FULLY_RECEIVED')
        <div class="mb-md p-4 bg-amber-50 dark:bg-amber-950/20 border border-amber-300 dark:border-amber-800 rounded-md flex items-start gap-3">
            <span class="material-symbols-outlined text-amber-600 text-xl mt-0.5">warning</span>
            <div>
                <p class="text-[10px] font-black text-amber-900 dark:text-amber-200 uppercase tracking-wider">WMS Fully Received — Stale Session Exists</p>
                <p class="text-[9px] font-medium text-amber-700 dark:text-amber-400 mt-1">
                    All {{ number_format($wmsTotalReceived, 2) }} units have been received in WMS.
                    However, session #{{ $activeSession->id }} ({{ $activeSession->status }}) exists from before finalization.
                    No new receiving session can be created. Please review and close the existing session.
                </p>
            </div>
        </div>
    @elseif($isFullyReceivedInWms)
        <div class="mb-md p-4 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800 rounded-md flex items-start gap-3">
            <span class="material-symbols-outlined text-emerald-600 text-xl mt-0.5">inventory</span>
            <div>
                <p class="text-[10px] font-black text-emerald-900 dark:text-emerald-200 uppercase tracking-wider">All Items Fully Received in WMS</p>
                <p class="text-[9px] font-medium text-emerald-700 dark:text-emerald-400 mt-1">
                    WMS Received: <strong>{{ number_format($wmsTotalReceived, 2) }}</strong>. Pending WMS Quantity: <strong>0</strong>.
                    @if($order->erp_sync_status === 'ERP_BEHIND')
                        ERP may still show outstanding quantities because VB6 ERP is not real-time. This is expected — WMS state is authoritative.
                    @endif
                </p>
            </div>
        </div>
    @endif


    <div class="grid grid-cols-12 gap-md">
        <!-- PO Header Info Card -->
        <div class="col-span-12 lg:col-span-4 space-y-md">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md p-md shadow-sm">
                <h3 class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800 pb-2 mb-3">Order Metadata</h3>
                
                <div class="space-y-3">
                    <div>
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">Supplier Snapshot</span>
                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200 block">{{ $order->supplier_name_snapshot }}</span>
                        @if($order->supplier_code_snapshot)
                            <span class="text-[9px] bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-800 text-slate-550 dark:text-slate-400 px-1.5 py-0.5 rounded font-mono font-bold mt-1 inline-block">Code: {{ $order->supplier_code_snapshot }}</span>
                        @endif
                    </div>

                    @if($order->department_name)
                    <div class="border-t border-slate-100 dark:border-slate-800 pt-3">
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">Dept. Pemesan</span>
                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200 block">{{ $order->department_name }}</span>
                    </div>
                    @endif
                    
                    <div class="grid grid-cols-2 gap-sm border-t border-slate-100 dark:border-slate-800 pt-3">
                        <div>
                            <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">PO Date</span>
                            <span class="text-xs font-mono font-bold text-slate-800 dark:text-slate-200">{{ $order->po_date ? $order->po_date->format('Y-m-d') : '-' }}</span>
                        </div>
                        <div>
                            <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">Expected Date</span>
                            <span class="text-xs font-mono font-bold text-slate-800 dark:text-slate-200">{{ $order->expected_date ? $order->expected_date->format('Y-m-d') : '-' }}</span>
                        </div>
                    </div>

                    {{-- WMS Receiving Summary --}}
                    <div class="border-t border-slate-100 dark:border-slate-800 pt-3">
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">WMS Receiving State</span>
                        <div class="mt-1 space-y-1">
                            <div class="flex justify-between text-[9px]">
                                <span class="font-bold text-slate-500">Total Ordered</span>
                                <span class="font-black font-mono text-slate-700">{{ number_format($order->items->sum('ordered_qty'), 2) }}</span>
                            </div>
                            <div class="flex justify-between text-[9px]">
                                <span class="font-bold text-slate-500">WMS Received</span>
                                <span class="font-black font-mono {{ $wmsTotalReceived > 0 ? 'text-emerald-700' : 'text-slate-500' }}">{{ number_format($wmsTotalReceived, 2) }}</span>
                            </div>
                            <div class="flex justify-between text-[9px]">
                                <span class="font-bold text-slate-500">WMS Pending</span>
                                <span class="font-black font-mono {{ $wmsTotalPending > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ number_format($wmsTotalPending, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 dark:border-slate-800 pt-3">
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">ERP Reconciliation</span>
                        <div class="flex items-center gap-2 mt-1">
                            @if($order->erp_sync_status === 'ERP_BEHIND')
                                <span class="text-[9px] font-black text-amber-800 bg-amber-50 border border-amber-300 px-2 py-0.5 rounded inline-flex items-center gap-1">
                                    <span class="material-symbols-outlined text-xs">sync_problem</span>
                                    ERP SYNC: BEHIND (WMS Received Ahead)
                                </span>
                            @else
                                <span class="text-[9px] font-black text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded inline-flex items-center gap-1">
                                    <span class="material-symbols-outlined text-xs">check_circle</span>
                                    ERP SYNC: IN SYNC
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="border-t border-slate-100 dark:border-slate-800 pt-3">
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">ERP Document Reference</span>
                        <span class="text-xs font-mono font-bold text-slate-800 dark:text-slate-200">{{ $order->document_reference ?: 'N/A' }}</span>
                    </div>

                    <div class="border-t border-slate-100 dark:border-slate-800 pt-3">
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">Import Channel</span>
                        <span class="text-[9px] font-black text-slate-655 dark:text-slate-400 uppercase bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-850 px-2 py-0.5 rounded inline-block mt-0.5">
                            {{ $order->source }}
                        </span>
                        <span class="text-[9px] text-slate-455 block mt-1 font-medium">Imported at: {{ $order->imported_at ? $order->imported_at->timezone('Asia/Jakarta')->format('Y-m-d H:i') : '-' }} WIB</span>
                    </div>

                    @if($order->remarks)
                    <div class="border-t border-slate-100 dark:border-slate-800 pt-3">
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">Remarks / Notes</span>
                        <p class="text-[10px] font-bold text-slate-500 uppercase mt-1 tracking-wide leading-relaxed">{{ $order->remarks }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Catalog Validation Metrics Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md p-md shadow-sm">
                <h3 class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800 pb-2 mb-3">Catalog Validation Metrics</h3>
                
                @php
                    $matched = $order->catalog_matched_count;
                    $total = $order->total_line_count;
                    $percent = $total > 0 ? round(($matched / $total) * 100) : 0;
                @endphp
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">Catalog Coverage</span>
                            <span class="text-lg font-black text-slate-800 dark:text-slate-200 font-mono">{{ $matched }} / {{ $total }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">Percentage</span>
                            <span class="text-lg font-black text-green-600 dark:text-green-400 font-mono">{{ $percent }}%</span>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2 overflow-hidden border border-slate-200/50">
                        <div class="bg-green-600 h-2 rounded-full transition-all duration-500" style="width: {{ $percent }}%"></div>
                    </div>

                    <div class="grid grid-cols-2 gap-sm border-t border-slate-100 dark:border-slate-800 pt-3">
                        <div class="bg-green-50/50 dark:bg-green-950/10 p-2 rounded border border-green-100/50 text-center">
                            <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">Matched</span>
                            <span class="text-md font-black text-green-700 dark:text-green-455 font-mono">{{ $matched }}</span>
                        </div>
                        <div class="p-2 rounded text-center {{ $order->catalog_missing_count > 0 ? 'bg-red-50/50 dark:bg-red-950/10 border border-red-100/50' : 'bg-slate-50 dark:bg-slate-800' }}">
                            <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">Missing</span>
                            <span class="text-md font-black font-mono {{ $order->catalog_missing_count > 0 ? 'text-red-700 dark:text-red-455' : 'text-slate-500' }}">{{ $order->catalog_missing_count }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PO Line Items Card -->
        <div class="col-span-12 lg:col-span-8 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md p-md shadow-sm flex flex-col">
            <h3 class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800 pb-2 mb-3">Line Items ({{ $order->items->count() }})</h3>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-805/50 border-b border-slate-200 dark:border-slate-850">
                            <th class="px-3 py-2.5 text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest text-center font-mono">Line</th>
                            <th class="px-3 py-2.5 text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest font-mono">ERP Code</th>
                            <th class="px-3 py-2.5 text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest">Item Name Snapshot</th>
                            <th class="px-3 py-2.5 text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest text-right">Ordered</th>
                            <th class="px-3 py-2.5 text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest text-right">WMS Rec.</th>
                            <th class="px-3 py-2.5 text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest text-right">Pending</th>
                            <th class="px-3 py-2.5 text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest text-center">UoM</th>
                            <th class="px-3 py-2.5 text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest text-center">Catalog</th>
                            <th class="px-3 py-2.5 text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest text-center">Matched Variant</th>
                            <th class="px-3 py-2.5 text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($order->items as $item)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-805/30 transition-colors">
                                <td class="px-3 py-3.5 text-xs text-center font-mono font-bold text-slate-400">
                                    {{ $item->line_number ?: '-' }}
                                </td>
                                <td class="px-3 py-3.5 text-xs font-mono font-black text-slate-900 dark:text-white">
                                    {{ $item->erp_code }}
                                </td>
                                <td class="px-3 py-3.5 text-xs font-bold text-slate-700 dark:text-slate-350 pr-4">
                                    {{ $item->item_name_snapshot }}
                                    @if($item->department_name)
                                        <span class="text-[8px] bg-slate-100 dark:bg-slate-800 text-slate-550 dark:text-slate-400 px-1 py-0.5 rounded font-mono ml-1">Dept: {{ $item->department_name }}</span>
                                    @endif
                                    @if($item->erp_sync_status === 'ERP_BEHIND')
                                        <div class="text-[8px] font-black text-amber-700 mt-0.5 flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[10px]">sync_problem</span>
                                            ERP Lagging: ERP Rec: {{ (float)$item->erp_received_qty }}, WMS Rec: {{ (float)$item->received_qty }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-3.5 text-xs font-mono text-slate-500 text-right">
                                    {{ (float)$item->ordered_qty == (int)$item->ordered_qty ? number_format($item->ordered_qty) : number_format($item->ordered_qty, 2) }}
                                </td>
                                <td class="px-3 py-3.5 text-xs font-mono font-bold text-right {{ (float)$item->received_qty > 0 ? 'text-emerald-700' : 'text-slate-400' }}">
                                    {{ (float)$item->received_qty == (int)$item->received_qty ? number_format($item->received_qty) : number_format($item->received_qty, 2) }}
                                </td>
                                <td class="px-3 py-3.5 text-xs font-mono font-bold text-right {{ (float)$item->pending_qty > 0 ? 'text-amber-700' : 'text-slate-400' }}">
                                    {{ (float)$item->pending_qty == (int)$item->pending_qty ? number_format($item->pending_qty) : number_format($item->pending_qty, 2) }}
                                </td>
                                <td class="px-3 py-3.5 text-xs text-center text-slate-655 dark:text-slate-400 font-bold uppercase font-mono">
                                    {{ $item->unit }}
                                </td>
                                
                                <!-- Catalog Validation Indicator -->
                                <td class="px-3 py-3.5 text-center">
                                    @if($item->isCatalogMatched())
                                        <span class="text-green-600 dark:text-green-400 text-[10px] font-black uppercase tracking-wider flex items-center justify-center gap-0.5">
                                            <span class="material-symbols-outlined text-sm">check_circle</span>
                                            Matched
                                        </span>
                                    @else
                                        <span class="text-red-600 dark:text-red-400 text-[10px] font-black uppercase tracking-wider flex items-center justify-center gap-0.5">
                                            <span class="material-symbols-outlined text-sm animate-pulse">warning</span>
                                            Needs Catalog
                                        </span>
                                    @endif
                                </td>

                                <!-- Matched Variant WMS Code -->
                                <td class="px-3 py-3.5 text-center">
                                    @if($item->isCatalogMatched())
                                        <span class="font-mono text-xs font-black bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded text-slate-700 dark:text-slate-300">
                                            {{ $item->variant->erp_code }}
                                        </span>
                                    @else
                                        <span class="text-[9px] font-black text-red-500 uppercase tracking-widest">
                                            Needs Catalog
                                        </span>
                                    @endif
                                </td>

                                <!-- Action Shortcut link -->
                                <td class="px-3 py-3.5 text-center">
                                    @if(!$item->isCatalogMatched())
                                        <a href="{{ route('items.create', [
                                            'legacy_product_code' => $item->erp_code,
                                            'item_name' => $item->item_name_snapshot,
                                            'unit' => $item->unit,
                                            'return_url' => request()->fullUrl()
                                        ]) }}" class="px-2.5 py-1 text-[9px] font-black bg-slate-900 hover:bg-slate-800 text-white rounded uppercase tracking-wider transition-all inline-flex items-center gap-1 shadow-sm active:scale-95">
                                            <span class="material-symbols-outlined text-xs">add_box</span>
                                            CREATE CATALOG
                                        </a>
                                    @else
                                        <span class="text-slate-305 dark:text-slate-700 text-xs">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if(isset($completedSessions) && $completedSessions->isNotEmpty())
        <!-- Completed Receiving Sessions History -->
        <div class="mt-6 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md p-md shadow-sm">
            <h3 class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800 pb-2 mb-4">
                Completed Receiving Sessions &amp; Inspection History ({{ $completedSessions->count() }})
            </h3>

            <div class="space-y-3">
                @foreach($completedSessions as $cs)
                    <div class="border border-slate-200 dark:border-slate-800 rounded p-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-3 bg-slate-50/50 dark:bg-slate-850/50">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-black font-mono text-slate-900 dark:text-white">Session #{{ $cs->id }}</span>
                                <span class="text-[8px] font-black text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded uppercase">
                                    COMPLETED
                                </span>
                            </div>
                            <div class="text-[9px] text-slate-500 font-medium">
                                Received at: {{ $cs->completed_at ? $cs->completed_at->timezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB' : '-' }} 
                                &bull; Finalized by: <span class="font-bold text-slate-700 dark:text-slate-300">{{ $cs->completedBy->name ?? ($cs->creator->name ?? 'Staff') }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <a href="{{ route('receiving.session', $cs->id) }}" class="h-9 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[9px] font-black uppercase tracking-wider rounded inline-flex items-center gap-1 transition-all">
                                <span class="material-symbols-outlined text-xs">visibility</span>
                                View Session
                            </a>
                            <a href="{{ route('receiving.session.pdf', $cs->id) }}" target="_blank" class="h-9 px-3 bg-slate-900 hover:bg-slate-800 text-white text-[9px] font-black uppercase tracking-wider rounded inline-flex items-center gap-1 transition-all shadow-sm">
                                <span class="material-symbols-outlined text-xs">picture_as_pdf</span>
                                A4 PDF Form
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
