<div class="p-4 pt-14 receiving-page-container min-h-screen bg-slate-100/40 dark:bg-slate-950 text-slate-800 dark:text-slate-100" 
     x-data="{ 
         showNotification: false, 
         notificationMessage: '', 
         notificationType: 'success',
         showAdjustTerima: {}
     }"
     x-on:message-dispatched.window="
         showNotification = true; 
         notificationMessage = $event.detail.message; 
         notificationType = $event.detail.type; 
         setTimeout(() => { showNotification = false; }, 4000);
     }">

    <!-- Load Signature Pad from CDN -->
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>

    <!-- Toast Notification Banner -->
    <div x-show="showNotification" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed receiving-toast-container left-4 right-4 z-50 p-4 rounded-lg shadow-xl flex items-center justify-between border uppercase tracking-wider text-xs font-black"
         x-bind:class="{
             'bg-green-50 border-green-200 text-green-800 dark:bg-green-950 dark:border-green-850 dark:text-green-200': notificationType === 'success',
             'bg-red-50 border-red-200 text-red-800 dark:bg-red-950 dark:border-red-850 dark:text-red-200': notificationType === 'error'
         }"
         style="display: none;">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-base" x-text="notificationType === 'success' ? 'check_circle' : 'error'"></span>
            <span x-text="notificationMessage"></span>
        </div>
        <button type="button" @click="showNotification = false" class="text-slate-400 hover:text-slate-600">
            <span class="material-symbols-outlined text-sm">close</span>
        </button>
    </div>

    <!-- Header info / Back button -->
    <div class="mb-4 flex items-center justify-between bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div class="flex items-center gap-3">
            <a href="{{ route('outstanding-purchases.show', $session->outstanding_purchase_order_id) }}" 
               class="w-10 h-10 bg-slate-50 hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-750 border border-slate-200 dark:border-slate-700 rounded-lg flex items-center justify-center text-slate-600 dark:text-slate-300 transition-all active:scale-95">
                <span class="material-symbols-outlined text-xl">arrow_back</span>
            </a>
            <div>
                <h2 class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none">Receiving Session #{{ $session->id }}</h2>
                <h1 class="text-sm font-black text-slate-900 dark:text-white tracking-tight uppercase mt-1">PO: {{ $session->outstandingPurchaseOrder->po_number }}</h1>
            </div>
        </div>
        <div>
            @if($session->status === \App\Models\ReceivingSession::STATUS_DRAFT)
                <span class="bg-amber-50 text-amber-800 border border-amber-200 text-[9px] font-black uppercase tracking-widest px-2.5 py-1 rounded-md">
                    1. Physical Checking
                </span>
            @elseif($session->status === \App\Models\ReceivingSession::STATUS_READY_REVIEW)
                <span class="bg-blue-50 text-blue-800 border border-blue-200 text-[9px] font-black uppercase tracking-widest px-2.5 py-1 rounded-md">
                    2. Ready Review
                </span>
            @elseif($session->status === \App\Models\ReceivingSession::STATUS_REVIEWED)
                <span class="bg-purple-50 text-purple-800 border border-purple-200 text-[9px] font-black uppercase tracking-widest px-2.5 py-1 rounded-md">
                    3. Final Review & Sign
                </span>
            @elseif($session->status === \App\Models\ReceivingSession::STATUS_COMPLETED)
                <span class="bg-emerald-50 text-emerald-800 border border-emerald-200 text-[9px] font-black uppercase tracking-widest px-2.5 py-1 rounded-md">
                    4. Completed
                </span>
            @endif
        </div>
    </div>

    <!-- Check for missing bins in active warehouse -->
    @php
        $missingLocation = false;
        foreach($items as $checkItem) {
            if (!$checkItem->isRemoved()) {
                $hasBin = \App\Models\Bin::forActiveWarehouse()->where('item_variant_id', $checkItem->item_variant_id)->exists();
                if (!$hasBin) {
                    $missingLocation = true;
                    break;
                }
            }
        }
    @endphp

    <!-- ========================================================================= -->
    <!-- STATE 4: COMPLETED SCREEN (Immutable Final State & Full Read-Only Breakdown) -->
    <!-- ========================================================================= -->
    @if($session->status === \App\Models\ReceivingSession::STATUS_COMPLETED)
        
        <div class="space-y-4 max-w-3xl mx-auto">
            <!-- Completed Header Banner -->
            <div class="bg-emerald-50 dark:bg-emerald-950/40 border-2 border-emerald-500 rounded-2xl p-5 shadow-sm text-center">
                <div class="w-12 h-12 bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-2 border border-emerald-300">
                    <span class="material-symbols-outlined text-2xl font-black">task_alt</span>
                </div>
                <h2 class="text-sm sm:text-base font-black text-slate-900 dark:text-white uppercase tracking-wider">
                    RECEIVING COMPLETED
                </h2>
                <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-100/80 dark:bg-emerald-900/80 text-emerald-800 dark:text-emerald-200 text-[10px] font-black uppercase tracking-widest rounded-full mt-1.5">
                    <span class="material-symbols-outlined text-xs">lock</span> READ ONLY — RECEIVING FINALIZED
                </div>
                <p class="text-xs text-slate-500 font-bold mt-2 uppercase font-mono">PO: {{ $session->outstandingPurchaseOrder->po_number }} &bull; Sesi #{{ $session->id }}</p>
            </div>

            <!-- Metadata Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div>
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block">Vendor</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ $session->outstandingPurchaseOrder->supplier_name_snapshot }}</span>
                    </div>
                    <div>
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block">Warehouse</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ $session->warehouse->name ?? 'N/A' }}</span>
                    </div>
                    <div>
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block">Operator / Checker</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ $session->creator->name ?? 'N/A' }}</span>
                    </div>
                    <div>
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block">Finalized Date</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ $session->completed_at ? $session->completed_at->timezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB' : '-' }}</span>
                    </div>
                </div>
            </div>

            <!-- Summary Counters -->
            <div class="grid grid-cols-3 gap-2">
                <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 text-center shadow-sm">
                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">Verified Items</span>
                    <span class="text-sm font-black text-emerald-600 font-mono">{{ $session->verifiedLines }}</span>
                </div>
                <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 text-center shadow-sm">
                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">Removed Items</span>
                    <span class="text-sm font-black text-red-500 font-mono">{{ $session->removedLines }}</span>
                </div>
                <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 text-center shadow-sm">
                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">Total PO Lines</span>
                    <span class="text-sm font-black text-slate-600 font-mono">{{ $session->totalLines }}</span>
                </div>
            </div>

            <!-- Read-Only Line Items List -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
                <div class="p-3.5 bg-slate-50 dark:bg-slate-850 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center">
                    <span class="text-[10px] font-black text-slate-600 dark:text-slate-300 uppercase tracking-wider">Verified Receiving Items ({{ $items->count() }})</span>
                    <span class="text-[9px] font-bold text-emerald-700 bg-emerald-100 dark:bg-emerald-950 px-2 py-0.5 rounded uppercase">Mutasi In Confirmed</span>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($items as $item)
                        @php
                            $qtyDatang = $item->qty_datang !== null ? (float)$item->qty_datang : (float)$item->expected_qty;
                            $qtyTerima = (float)$item->received_qty;
                            $expected = (float)$item->expected_qty;
                        @endphp
                        <div class="p-4 flex flex-col gap-2">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="text-xs font-black text-slate-900 dark:text-white leading-tight">
                                        {{ $item->outstandingPurchaseOrderItem->item_name_snapshot }}
                                    </h4>
                                    <span class="text-[9px] font-mono text-slate-400 font-bold">
                                        {{ $item->outstandingPurchaseOrderItem->erp_code }}
                                    </span>
                                </div>
                                <div>
                                    @if($item->isRemoved())
                                        <span class="bg-red-50 text-red-700 border border-red-200 text-[8px] font-black px-2 py-0.5 rounded uppercase flex items-center gap-1">
                                            <span class="material-symbols-outlined text-xs">delete</span> REMOVED ({{ $item->removed_reason }})
                                        </span>
                                    @else
                                        <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-[8px] font-black px-2 py-0.5 rounded uppercase flex items-center gap-1">
                                            <span class="material-symbols-outlined text-xs">check_circle</span> VERIFIED ({{ $item->check_result ?: 'OK' }})
                                        </span>
                                    @endif
                                </div>
                            </div>

                            @if(!$item->isRemoved())
                                <div class="grid grid-cols-3 gap-2 bg-slate-50 dark:bg-slate-800/40 p-2.5 rounded-lg text-center font-mono">
                                    <div>
                                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">Expected</span>
                                        <span class="text-xs font-bold text-slate-600 dark:text-slate-300">{{ (float)$expected == (int)$expected ? (int)$expected : number_format($expected, 2) }} {{ $item->outstandingPurchaseOrderItem->unit }}</span>
                                    </div>
                                    <div>
                                        <span class="text-[8px] font-black text-blue-500 uppercase tracking-wider block">Qty Datang</span>
                                        <span class="text-xs font-black text-blue-700 dark:text-blue-400">{{ (float)$qtyDatang == (int)$qtyDatang ? (int)$qtyDatang : number_format($qtyDatang, 2) }} {{ $item->outstandingPurchaseOrderItem->unit }}</span>
                                    </div>
                                    <div>
                                        <span class="text-[8px] font-black text-emerald-500 uppercase tracking-wider block">Qty Terima</span>
                                        <span class="text-xs font-black text-emerald-700 dark:text-emerald-400">{{ (float)$qtyTerima == (int)$qtyTerima ? (int)$qtyTerima : number_format($qtyTerima, 2) }} {{ $item->outstandingPurchaseOrderItem->unit }}</span>
                                    </div>
                                </div>
                                @if($item->check_notes)
                                    <div class="text-[10px] text-slate-500 font-medium italic bg-slate-50 dark:bg-slate-800/30 p-2 rounded border border-slate-100 dark:border-slate-800">
                                        Catatan Pengecekan: {{ $item->check_notes }}
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Read-Only Signatures Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-3">Digital Signatures Record</h4>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <!-- 1. DISERAHKAN OLEH -->
                    <div class="border border-slate-200 dark:border-slate-800 rounded-lg p-3 flex flex-col items-center justify-between text-center min-h-[140px] bg-slate-50/50 dark:bg-slate-850/50">
                        <span class="text-[9px] font-black text-slate-500 uppercase tracking-wider block mb-1">Diserahkan Oleh</span>
                        <span class="text-[8px] text-slate-400 font-bold mb-2">( Vendor / Sopir )</span>
                        @if($diserahkanSig && Storage::disk('public')->exists($diserahkanSig->signature_path))
                            <div class="w-full flex flex-col items-center">
                                <img src="{{ 'data:image/png;base64,' . base64_encode(Storage::disk('public')->get($diserahkanSig->signature_path)) }}" class="max-h-[50px] max-w-[110px] object-contain bg-white border rounded p-1 mb-1.5" />
                                <span class="text-[8px] text-slate-500 font-bold block">Signed: {{ $diserahkanSig->signed_at ? $diserahkanSig->signed_at->format('d/m/Y H:i') : '-' }}</span>
                            </div>
                        @else
                            <span class="text-[9px] text-slate-400 italic my-auto">Signed on physical F4 print</span>
                        @endif
                    </div>

                    <!-- 2. DITERIMA/DICEK OLEH -->
                    <div class="border border-slate-200 dark:border-slate-800 rounded-lg p-3 flex flex-col items-center justify-between text-center min-h-[140px] bg-slate-50/50 dark:bg-slate-850/50">
                        <span class="text-[9px] font-black text-slate-500 uppercase tracking-wider block mb-1">Diterima / Dicek Oleh</span>
                        <span class="text-[8px] text-slate-400 font-bold mb-2">( {{ $session->creator->name ?? 'Checker' }} )</span>
                        @if($diterimaSig && Storage::disk('public')->exists($diterimaSig->signature_path))
                            <div class="w-full flex flex-col items-center">
                                <img src="{{ 'data:image/png;base64,' . base64_encode(Storage::disk('public')->get($diterimaSig->signature_path)) }}" class="max-h-[50px] max-w-[110px] object-contain bg-white border rounded p-1 mb-1.5" />
                                <span class="text-[8px] text-slate-500 font-bold block">Signed: {{ $diterimaSig->signed_at ? $diterimaSig->signed_at->format('d/m/Y H:i') : '-' }}</span>
                            </div>
                        @else
                            <span class="text-[9px] text-slate-400 italic my-auto">Signed on physical F4 print</span>
                        @endif
                    </div>

                    <!-- 3. BAG. GUDANG -->
                    <div class="border border-slate-200 dark:border-slate-800 rounded-lg p-3 flex flex-col items-center justify-between text-center min-h-[140px] bg-slate-50/50 dark:bg-slate-850/50">
                        <span class="text-[9px] font-black text-slate-500 uppercase tracking-wider block mb-1">Bag. Gudang</span>
                        <span class="text-[8px] text-slate-400 font-bold mb-2">( {{ $session->reviewedBy->name ?? 'Staff Gudang' }} )</span>
                        @if($gudangSig && Storage::disk('public')->exists($gudangSig->signature_path))
                            <div class="w-full flex flex-col items-center">
                                <img src="{{ 'data:image/png;base64,' . base64_encode(Storage::disk('public')->get($gudangSig->signature_path)) }}" class="max-h-[50px] max-w-[110px] object-contain bg-white border rounded p-1 mb-1.5" />
                                <span class="text-[8px] text-slate-500 font-bold block">Signed: {{ $gudangSig->signed_at ? $gudangSig->signed_at->format('d/m/Y H:i') : '-' }}</span>
                            </div>
                        @else
                            <span class="text-[9px] text-slate-400 italic my-auto">Signed on physical F4 print</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Session Remarks if any -->
            @if($session->remarks)
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block mb-1">Session Remarks</span>
                    <p class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $session->remarks }}</p>
                </div>
            @endif

            <!-- Navigation Actions -->
            <div class="flex flex-col sm:flex-row gap-3 pt-2">
                <a href="{{ route('receiving.session.pdf', $session->id) }}" 
                   target="_blank"
                   class="flex-1 h-12 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black tracking-widest uppercase flex items-center justify-center gap-2 shadow-md shadow-emerald-200 dark:shadow-none transition-all active:scale-95">
                    <span class="material-symbols-outlined text-base">picture_as_pdf</span>
                    F4 — BUKTI PENGECEKAN BARANG DATANG
                </a>
                <a href="{{ route('outstanding-purchases') }}" 
                   class="flex-1 h-12 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-750 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-black tracking-widest uppercase flex items-center justify-center gap-1.5 transition-all active:scale-95">
                    <span class="material-symbols-outlined text-base">list_alt</span>
                    Back to Outstanding Purchases
                </a>
            </div>
        </div>

    <!-- ========================================================================= -->
    <!-- STATE 2: READY_REVIEW SCREEN (Review & Verification Confirmation) -->
    <!-- ========================================================================= -->
    @elseif($session->status === \App\Models\ReceivingSession::STATUS_READY_REVIEW)

        <div class="space-y-4">
            <!-- Review Banner -->
            <div class="bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 rounded-xl p-4 flex items-center gap-3">
                <span class="material-symbols-outlined text-2xl text-blue-600">rate_review</span>
                <div>
                    <h3 class="text-xs font-black text-blue-900 dark:text-blue-200 uppercase tracking-wider">RECEIVING REVIEW</h3>
                    <p class="text-[11px] text-blue-700 dark:text-blue-300">Please review all physical checking quantities below before confirming.</p>
                </div>
            </div>

            <!-- PO Metadata Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block">Vendor</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ $session->outstandingPurchaseOrder->supplier_name_snapshot }}</span>
                    </div>
                    <div>
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block">PO Date</span>
                        <span class="font-mono font-bold text-slate-800 dark:text-slate-200">{{ $session->outstandingPurchaseOrder->po_date ? $session->outstandingPurchaseOrder->po_date->format('Y-m-d') : '-' }}</span>
                    </div>
                </div>
            </div>

            <!-- Summary Counters -->
            <div class="grid grid-cols-3 gap-2">
                <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 text-center shadow-sm">
                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">Verified Items</span>
                    <span class="text-sm font-black text-emerald-600 font-mono">{{ $session->verifiedLines }}</span>
                </div>
                <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 text-center shadow-sm">
                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">Removed Items</span>
                    <span class="text-sm font-black text-red-500 font-mono">{{ $session->removedLines }}</span>
                </div>
                <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 text-center shadow-sm">
                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">Pending Items</span>
                    <span class="text-sm font-black text-slate-600 font-mono">{{ $session->pendingLines }}</span>
                </div>
            </div>

            <!-- Compact Review Items Table -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
                <div class="p-3.5 bg-slate-50 dark:bg-slate-850 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center">
                    <span class="text-[10px] font-black text-slate-500 uppercase tracking-wider font-mono">Items Review Table</span>
                    <a href="{{ route('receiving.session.pdf', $session->id) }}" target="_blank" class="text-[9px] font-black text-blue-600 hover:text-blue-700 uppercase tracking-wider inline-flex items-center gap-1">
                        <span class="material-symbols-outlined text-xs">picture_as_pdf</span> Preview F4
                    </a>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($items as $item)
                        @php
                            $qtyDatang = $item->qty_datang !== null ? (float)$item->qty_datang : (float)$item->expected_qty;
                            $qtyTerima = (float)$item->received_qty;
                            $expected = (float)$item->expected_qty;
                        @endphp
                        <div class="p-3.5 flex flex-col gap-2">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="text-xs font-black text-slate-900 dark:text-white leading-tight">
                                        {{ $item->outstandingPurchaseOrderItem->item_name_snapshot }}
                                    </h4>
                                    <span class="text-[9px] font-mono text-slate-400 font-bold">
                                        {{ $item->outstandingPurchaseOrderItem->erp_code }}
                                    </span>
                                </div>
                                <div>
                                    @if($item->isRemoved())
                                        <span class="bg-red-50 text-red-700 border border-red-200 text-[8px] font-black px-2 py-0.5 rounded uppercase">
                                            REMOVED ({{ $item->removed_reason }})
                                        </span>
                                    @else
                                        <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-[8px] font-black px-2 py-0.5 rounded uppercase">
                                            {{ $item->check_result ?: 'OK' }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            @if(!$item->isRemoved())
                                <div class="grid grid-cols-3 gap-2 bg-slate-50 dark:bg-slate-800/40 p-2 rounded-lg text-center font-mono">
                                    <div>
                                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">Expected</span>
                                        <span class="text-xs font-bold text-slate-600 dark:text-slate-300">{{ (float)$expected == (int)$expected ? (int)$expected : number_format($expected, 2) }} {{ $item->outstandingPurchaseOrderItem->unit }}</span>
                                    </div>
                                    <div>
                                        <span class="text-[8px] font-black text-blue-500 uppercase tracking-wider block">Qty Datang</span>
                                        <span class="text-xs font-black text-blue-700 dark:text-blue-400">{{ (float)$qtyDatang == (int)$qtyDatang ? (int)$qtyDatang : number_format($qtyDatang, 2) }} {{ $item->outstandingPurchaseOrderItem->unit }}</span>
                                    </div>
                                    <div>
                                        <span class="text-[8px] font-black text-emerald-500 uppercase tracking-wider block">Qty Terima</span>
                                        <span class="text-xs font-black text-emerald-700 dark:text-emerald-400">{{ (float)$qtyTerima == (int)$qtyTerima ? (int)$qtyTerima : number_format($qtyTerima, 2) }} {{ $item->outstandingPurchaseOrderItem->unit }}</span>
                                    </div>
                                </div>
                                @if($item->check_notes)
                                    <div class="text-[10px] text-slate-500 font-medium italic">
                                        Catatan: {{ $item->check_notes }}
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            @if($session->remarks)
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3.5 shadow-sm">
                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block mb-1">Session Remarks</span>
                    <p class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $session->remarks }}</p>
                </div>
            @endif
        </div>

    <!-- ========================================================================= -->
    <!-- STATE 3: REVIEWED SCREEN (Final Review + Optional Signatures + Commit) -->
    <!-- ========================================================================= -->
    @elseif($session->status === \App\Models\ReceivingSession::STATUS_REVIEWED)

        <div class="space-y-4">
            <!-- Reviewed Status Banner -->
            <div class="bg-purple-50 dark:bg-purple-950/40 border border-purple-200 dark:border-purple-800 rounded-xl p-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-2xl text-purple-600">verified</span>
                    <div>
                        <h3 class="text-xs font-black text-purple-900 dark:text-purple-200 uppercase tracking-wider">CHECKING REVIEWED & LOCKED</h3>
                        <p class="text-[11px] text-purple-700 dark:text-purple-300">Ready for optional signatures and Final Commit.</p>
                    </div>
                </div>
                <a href="{{ route('receiving.session.pdf', $session->id) }}" target="_blank" class="h-9 px-3 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-[9px] font-black uppercase tracking-wider flex items-center gap-1 shadow-sm transition-all">
                    <span class="material-symbols-outlined text-xs">picture_as_pdf</span>
                    PREVIEW F4
                </a>
            </div>

            <!-- DIGITAL SIGNATURE WORKFLOW (Optional) -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between mb-4 border-b border-slate-100 dark:border-slate-800 pb-2">
                    <div>
                        <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-wider">Digital Signatures</h3>
                        <span class="text-[9px] text-slate-400 font-bold block">Optional — can be signed on paper F4 after printing</span>
                    </div>
                    <span class="text-[8px] font-black text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded uppercase tracking-wider">
                        Optional
                    </span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <!-- 1. DISERAHKAN OLEH -->
                    <div class="border border-slate-200 dark:border-slate-800 rounded-lg p-3 flex flex-col items-center justify-between text-center min-h-[150px] bg-slate-50/50 dark:bg-slate-850/50">
                        <span class="text-[9px] font-black text-slate-500 uppercase tracking-wider block mb-1">Diserahkan Oleh</span>
                        <span class="text-[8px] text-slate-400 font-bold mb-2">( Vendor / Sopir )</span>
                        @if($diserahkanSig)
                            <div class="w-full flex flex-col items-center">
                                <img src="{{ 'data:image/png;base64,' . base64_encode(Storage::disk('public')->get($diserahkanSig->signature_path)) }}" class="max-h-[50px] max-w-[110px] object-contain bg-white border rounded p-1 mb-1.5" />
                                <span class="text-[8px] text-slate-500 font-bold block mb-1">Signed: {{ $diserahkanSig->signed_at->format('d/m H:i') }}</span>
                                <button type="button" wire:click="clearSignature('DISERAHKAN_OLEH')" class="text-red-600 hover:text-red-700 text-[8px] font-black uppercase tracking-wider">Clear Signature</button>
                            </div>
                        @else
                            <button type="button" @click="$dispatch('open-sign-modal', { role: 'DISERAHKAN_OLEH' })" class="w-full h-10 border border-dashed border-slate-300 hover:border-slate-400 hover:bg-white dark:hover:bg-slate-800 rounded-lg text-[9px] font-black tracking-wider uppercase text-slate-600 dark:text-slate-300 transition-all flex items-center justify-center gap-1">
                                <span class="material-symbols-outlined text-xs">edit_square</span> Sign (Optional)
                            </button>
                        @endif
                    </div>

                    <!-- 2. DITERIMA/DICEK OLEH -->
                    <div class="border border-slate-200 dark:border-slate-800 rounded-lg p-3 flex flex-col items-center justify-between text-center min-h-[150px] bg-slate-50/50 dark:bg-slate-850/50">
                        <span class="text-[9px] font-black text-slate-500 uppercase tracking-wider block mb-1">Diterima / Dicek Oleh</span>
                        <span class="text-[8px] text-slate-400 font-bold mb-2">( {{ $session->creator->name ?? 'Checker' }} )</span>
                        @if($diterimaSig)
                            <div class="w-full flex flex-col items-center">
                                <img src="{{ 'data:image/png;base64,' . base64_encode(Storage::disk('public')->get($diterimaSig->signature_path)) }}" class="max-h-[50px] max-w-[110px] object-contain bg-white border rounded p-1 mb-1.5" />
                                <span class="text-[8px] text-slate-500 font-bold block mb-1">Signed: {{ $diterimaSig->signed_at->format('d/m H:i') }}</span>
                                <button type="button" wire:click="clearSignature('DITERIMA_OLEH')" class="text-red-600 hover:text-red-700 text-[8px] font-black uppercase tracking-wider">Clear Signature</button>
                            </div>
                        @else
                            <button type="button" @click="$dispatch('open-sign-modal', { role: 'DITERIMA_OLEH' })" class="w-full h-10 border border-dashed border-slate-300 hover:border-slate-400 hover:bg-white dark:hover:bg-slate-800 rounded-lg text-[9px] font-black tracking-wider uppercase text-slate-600 dark:text-slate-300 transition-all flex items-center justify-center gap-1">
                                <span class="material-symbols-outlined text-xs">edit_square</span> Sign (Optional)
                            </button>
                        @endif
                    </div>

                    <!-- 3. BAG. GUDANG -->
                    <div class="border border-slate-200 dark:border-slate-800 rounded-lg p-3 flex flex-col items-center justify-between text-center min-h-[150px] bg-slate-50/50 dark:bg-slate-850/50">
                        <span class="text-[9px] font-black text-slate-500 uppercase tracking-wider block mb-1">Bag. Gudang</span>
                        <span class="text-[8px] text-slate-400 font-bold mb-2">( {{ $session->reviewedBy->name ?? 'Staff Gudang' }} )</span>
                        @if($gudangSig)
                            <div class="w-full flex flex-col items-center">
                                <img src="{{ 'data:image/png;base64,' . base64_encode(Storage::disk('public')->get($gudangSig->signature_path)) }}" class="max-h-[50px] max-w-[110px] object-contain bg-white border rounded p-1 mb-1.5" />
                                <span class="text-[8px] text-slate-500 font-bold block mb-1">Signed: {{ $gudangSig->signed_at->format('d/m H:i') }}</span>
                                <button type="button" wire:click="clearSignature('BAG_GUDANG')" class="text-red-600 hover:text-red-700 text-[8px] font-black uppercase tracking-wider">Clear Signature</button>
                            </div>
                        @else
                            <button type="button" @click="$dispatch('open-sign-modal', { role: 'BAG_GUDANG' })" class="w-full h-10 border border-dashed border-slate-300 hover:border-slate-400 hover:bg-white dark:hover:bg-slate-800 rounded-lg text-[9px] font-black tracking-wider uppercase text-slate-600 dark:text-slate-300 transition-all flex items-center justify-center gap-1">
                                <span class="material-symbols-outlined text-xs">edit_square</span> Sign (Optional)
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Items Summary Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-3">Verified Items to Commit ({{ $session->verifiedLines }})</h4>
                <div class="space-y-2">
                    @foreach($items as $item)
                        @if(!$item->isRemoved())
                            <div class="p-2.5 bg-slate-50 dark:bg-slate-850 rounded-lg flex justify-between items-center text-xs">
                                <div>
                                    <div class="font-bold text-slate-800 dark:text-slate-200">{{ $item->outstandingPurchaseOrderItem->item_name_snapshot }}</div>
                                    <div class="text-[9px] font-mono text-slate-400">{{ $item->outstandingPurchaseOrderItem->erp_code }}</div>
                                </div>
                                <div class="text-right font-mono font-black text-emerald-600">
                                    {{ (float)$item->received_qty == (int)$item->received_qty ? (int)$item->received_qty : number_format($item->received_qty, 2) }} {{ $item->outstandingPurchaseOrderItem->unit }}
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

    <!-- ========================================================================= -->
    <!-- STATE 1: DRAFT SCREEN (Physical Checking Cards) -->
    <!-- ========================================================================= -->
    @else

        <!-- Progress Dashboard -->
        <div class="mb-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Checking Progress</span>
                <span class="text-xs font-black font-mono text-emerald-600">
                    {{ $session->verifiedLines + $session->removedLines }} / {{ $session->totalLines }} Checked
                </span>
            </div>
            
            <!-- Progress Bar -->
            <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2 overflow-hidden border border-slate-200/50 mb-3">
                <div class="bg-emerald-600 h-2 rounded-full transition-all duration-300" style="width: {{ $session->completionPercentage }}%"></div>
            </div>

            <div class="grid grid-cols-3 gap-2">
                <div class="p-2.5 bg-slate-50 dark:bg-slate-850 rounded-lg border border-slate-100 dark:border-slate-800 text-center">
                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">Verified</span>
                    <span class="text-xs font-black text-emerald-600 font-mono">{{ $session->verifiedLines }}</span>
                </div>
                <div class="p-2.5 bg-slate-50 dark:bg-slate-850 rounded-lg border border-slate-100 dark:border-slate-800 text-center">
                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">Removed</span>
                    <span class="text-xs font-black text-red-500 font-mono">{{ $session->removedLines }}</span>
                </div>
                <div class="p-2.5 bg-slate-50 dark:bg-slate-850 rounded-lg border border-slate-100 dark:border-slate-800 text-center">
                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">Pending</span>
                    <span class="text-xs font-black text-slate-600 font-mono">{{ $session->pendingLines }}</span>
                </div>
            </div>
        </div>

        <!-- Line items list -->
        <div class="space-y-3 mb-4">
            <h3 class="text-[9px] font-black text-slate-400 uppercase tracking-widest px-1">Physical Checking Items ({{ $items->count() }})</h3>

            @foreach($items as $item)
                @php
                    $isPending = $item->isPending();
                    $isVerified = $item->isVerified();
                    $isRemoved = $item->isRemoved();
                    
                    // Check if bin is assigned in active warehouse
                    $hasBin = \App\Models\Bin::forActiveWarehouse()->where('item_variant_id', $item->item_variant_id)->exists();
                    $qtyDatangVal = $item->qty_datang !== null ? (float)$item->qty_datang : (float)$item->expected_qty;
                    $qtyTerimaVal = (float)$item->received_qty;
                @endphp
                <div class="bg-white dark:bg-slate-900 border rounded-xl p-4 shadow-sm transition-all duration-200
                    @if($isVerified) border-l-4 border-l-emerald-600 border-slate-200 dark:border-slate-800
                    @elseif($isRemoved) border-l-4 border-l-red-500 border-slate-200 dark:border-slate-800 opacity-75
                    @else border-l-4 border-l-amber-500 border-slate-200 dark:border-slate-800
                    @endif">
                    
                    <!-- Item Header & Code -->
                    <div class="flex justify-between items-start mb-2">
                        <div class="pr-2">
                            <h4 class="text-xs font-black text-slate-900 dark:text-white leading-tight">
                                {{ $item->outstandingPurchaseOrderItem->item_name_snapshot }}
                            </h4>
                            <span class="text-[9px] bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 px-1.5 py-0.5 rounded font-mono font-bold mt-1 inline-block">
                                {{ $item->outstandingPurchaseOrderItem->erp_code }}
                            </span>
                        </div>

                        <!-- Status badge -->
                        <div>
                            @if($isVerified)
                                <span class="text-emerald-700 bg-emerald-50 border border-emerald-200 text-[8px] font-black uppercase tracking-wider px-2 py-0.5 rounded flex items-center gap-0.5">
                                    <span class="material-symbols-outlined text-[10px]">check_circle</span> Verified
                                </span>
                            @elseif($isRemoved)
                                <span class="text-red-700 bg-red-50 border border-red-200 text-[8px] font-black uppercase tracking-wider px-2 py-0.5 rounded flex items-center gap-0.5">
                                    <span class="material-symbols-outlined text-[10px]">delete</span> Removed
                                </span>
                            @else
                                <span class="text-amber-700 bg-amber-50 border border-amber-200 text-[8px] font-black uppercase tracking-wider px-2 py-0.5 rounded flex items-center gap-0.5">
                                    <span class="material-symbols-outlined text-[10px]">pending</span> Pending
                                </span>
                            @endif
                        </div>
                    </div>

                    @if($isRemoved)
                        <div class="my-2 bg-red-50/60 p-2.5 rounded-lg border border-red-100 text-[10px] text-red-800 font-bold uppercase tracking-wide">
                            <div>Reason: {{ $item->removed_reason }}</div>
                            @if($item->remarks)
                                <div class="mt-1 normal-case text-slate-600 font-normal">Notes: {{ $item->remarks }}</div>
                            @endif
                        </div>
                    @endif

                    @if(!$isRemoved)
                        <!-- Quantities Section (Simplified Quantity UX) -->
                        <div class="mt-3 border-t border-slate-100 dark:border-slate-800 pt-3 space-y-3">
                            <!-- 1. Expected Qty Reference -->
                            <div class="flex items-center justify-between bg-slate-50 dark:bg-slate-850 px-3 py-2 rounded-lg">
                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider">EXPECTED (PO)</span>
                                <span class="text-xs font-black font-mono text-slate-700 dark:text-slate-200">
                                    {{ (float)$item->expected_qty == (int)$item->expected_qty ? (int)$item->expected_qty : number_format($item->expected_qty, 2) }} {{ $item->outstandingPurchaseOrderItem->unit }}
                                </span>
                            </div>

                            <!-- 2. QTY DATANG (The ONLY active input required) -->
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <span class="text-[9px] font-black text-blue-600 dark:text-blue-400 uppercase tracking-wider block">QTY DATANG</span>
                                    <span class="text-[8px] text-slate-400 font-bold">Fisik Tiba</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    @if($isPending)
                                        <button type="button" 
                                                wire:click="decrementQtyDatang({{ $item->id }})" 
                                                class="w-11 h-11 bg-slate-100 hover:bg-slate-200 border border-slate-200 rounded-lg flex items-center justify-center font-black text-lg text-slate-700 active:scale-90 select-none">
                                            -
                                        </button>
                                        <div class="w-20 h-11 bg-white dark:bg-slate-800 border border-slate-300 rounded-lg flex items-center justify-center">
                                            <input type="number" 
                                                   step="any"
                                                   value="{{ $qtyDatangVal }}"
                                                   wire:change="setQtyDatangManual({{ $item->id }}, $event.target.value)"
                                                   class="w-full h-full text-center bg-transparent border-none focus:outline-none focus:ring-0 p-0 font-mono font-black text-sm text-slate-900 dark:text-white" />
                                        </div>
                                        <button type="button" 
                                                wire:click="incrementQtyDatang({{ $item->id }})" 
                                                class="w-11 h-11 bg-slate-100 hover:bg-slate-200 border border-slate-200 rounded-lg flex items-center justify-center font-black text-lg text-slate-700 active:scale-90 select-none">
                                            +
                                        </button>
                                    @else
                                        <span class="font-mono font-black text-xs px-3 py-2 bg-slate-50 border rounded-lg text-slate-800">
                                            {{ (float)$qtyDatangVal == (int)$qtyDatangVal ? (int)$qtyDatangVal : number_format($qtyDatangVal, 2) }} {{ $item->outstandingPurchaseOrderItem->unit }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <!-- 3. QTY TERIMA (Auto-Calculated Visual Display) -->
                            <div class="flex items-center justify-between gap-2 bg-emerald-50/50 dark:bg-emerald-950/20 p-2.5 rounded-lg border border-emerald-100 dark:border-emerald-900/30">
                                <div>
                                    <span class="text-[9px] font-black text-emerald-700 dark:text-emerald-400 uppercase tracking-wider flex items-center gap-1">
                                        QTY TERIMA
                                        <span class="text-[7px] font-black bg-emerald-200/80 text-emerald-800 px-1 py-0.2 rounded uppercase tracking-widest">AUTO</span>
                                    </span>
                                    <span class="text-[8px] text-slate-500 font-medium">Diterima Gudang</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-black text-xs text-emerald-800 dark:text-emerald-300">
                                        {{ (float)$qtyTerimaVal == (int)$qtyTerimaVal ? (int)$qtyTerimaVal : number_format($qtyTerimaVal, 2) }} {{ $item->outstandingPurchaseOrderItem->unit }}
                                    </span>

                                    <!-- If REJECT or RUSAK, provide optional adjustment toggle -->
                                    @if($isPending && in_array($item->check_result, ['REJECT', 'RUSAK']))
                                        <button type="button" 
                                                @click="showAdjustTerima[{{ $item->id }}] = !showAdjustTerima[{{ $item->id }}]"
                                                class="text-[8px] font-black text-amber-700 bg-amber-100 hover:bg-amber-200 px-1.5 py-0.5 rounded uppercase">
                                            Adjust
                                        </button>
                                    @endif
                                </div>
                            </div>

                            <!-- Optional QTY TERIMA stepper (only if manually adjusted for REJECT/RUSAK) -->
                            <div x-show="showAdjustTerima[{{ $item->id }}]" class="p-2 bg-amber-50 rounded-lg border border-amber-200 flex items-center justify-between" style="display: none;">
                                <span class="text-[8px] font-black text-amber-800 uppercase">Manual Qty Terima:</span>
                                <div class="flex items-center gap-1">
                                    <button type="button" wire:click="decrementQtyTerima({{ $item->id }})" class="w-8 h-8 bg-white border rounded font-black text-sm">-</button>
                                    <span class="w-12 text-center font-mono font-black text-xs">{{ $qtyTerimaVal }}</span>
                                    <button type="button" wire:click="incrementQtyTerima({{ $item->id }})" class="w-8 h-8 bg-white border rounded font-black text-sm">+</button>
                                </div>
                            </div>

                            <!-- 4. HASIL PENGECEKAN (OK / REJECT / RUSAK) -->
                            <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-2">HASIL PENGECEKAN</span>
                                @if($isPending)
                                    <div class="grid grid-cols-3 gap-2">
                                        <button type="button" 
                                                wire:click="setCheckResult({{ $item->id }}, 'OK')" 
                                                class="h-11 text-[10px] font-black tracking-wider uppercase rounded-lg transition-all active:scale-95 flex items-center justify-center gap-1
                                                {{ $item->check_result === 'OK' || empty($item->check_result) ? 'bg-emerald-600 text-white shadow-md shadow-emerald-200 ring-2 ring-emerald-600' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200' }}">
                                            <span class="material-symbols-outlined text-sm">check</span> OK
                                        </button>
                                        <button type="button" 
                                                wire:click="setCheckResult({{ $item->id }}, 'REJECT')" 
                                                class="h-11 text-[10px] font-black tracking-wider uppercase rounded-lg transition-all active:scale-95 flex items-center justify-center gap-1
                                                {{ $item->check_result === 'REJECT' ? 'bg-red-600 text-white shadow-md shadow-red-200 ring-2 ring-red-600' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200' }}">
                                            <span class="material-symbols-outlined text-sm">close</span> REJECT
                                        </button>
                                        <button type="button" 
                                                wire:click="setCheckResult({{ $item->id }}, 'RUSAK')" 
                                                class="h-11 text-[10px] font-black tracking-wider uppercase rounded-lg transition-all active:scale-95 flex items-center justify-center gap-1
                                                {{ $item->check_result === 'RUSAK' ? 'bg-amber-600 text-white shadow-md shadow-amber-200 ring-2 ring-amber-600' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200' }}">
                                            <span class="material-symbols-outlined text-sm">warning</span> RUSAK
                                        </button>
                                    </div>
                                @else
                                    <div>
                                        <span class="text-emerald-700 bg-emerald-50 border border-emerald-200 text-[9px] font-black px-2.5 py-1 rounded-md inline-flex items-center gap-1">
                                            <span class="material-symbols-outlined text-xs">verified</span> {{ $item->check_result ?: 'OK' }}
                                        </span>
                                    </div>
                                @endif
                            </div>

                            <!-- 5. CATATAN / CHECK NOTES -->
                            <div class="pt-1">
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-wider block mb-1">Catatan Pengecekan (Opsional)</label>
                                @if($isPending)
                                    <input type="text" 
                                           value="{{ $item->check_notes }}" 
                                           wire:change="setCheckNotes({{ $item->id }}, $event.target.value)"
                                           placeholder="Contoh: 1 pcs kemasan penyok / tipe sesuai..."
                                           class="w-full text-xs font-medium p-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg focus:outline-none focus:ring-1 focus:ring-slate-400 text-slate-800 dark:text-slate-200" />
                                @else
                                    <p class="text-xs text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-800 p-2 rounded-lg border border-slate-100 dark:border-slate-700 font-medium">
                                        {{ $item->check_notes ?: '-' }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Card Actions -->
                    <div class="flex gap-2 mt-4 pt-3 border-t border-slate-100 dark:border-slate-800">
                        @if($isPending)
                            <!-- Verify Button -->
                            <button type="button" 
                                    wire:click="verifyLine({{ $item->id }})" 
                                    class="flex-1 h-12 text-[11px] font-black tracking-widest rounded-lg transition-all active:scale-95 flex items-center justify-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white shadow-md shadow-emerald-200 dark:shadow-none">
                                <span class="material-symbols-outlined text-base">check_circle</span>
                                VERIFY ITEM
                            </button>

                            <!-- Remove Button -->
                            <button type="button" 
                                    wire:click="openRemoveModal({{ $item->id }})" 
                                    class="w-24 h-12 bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200 rounded-lg text-[10px] font-black tracking-widest transition-all active:scale-95 flex items-center justify-center gap-1">
                                <span class="material-symbols-outlined text-sm">delete</span>
                                REMOVE
                            </button>
                        @elseif($isVerified)
                            <!-- Verified indicator + Edit button -->
                            <div class="flex-1 flex items-center justify-between bg-emerald-50 dark:bg-emerald-950/30 p-2 rounded-lg border border-emerald-200 dark:border-emerald-800">
                                <span class="text-xs font-black text-emerald-700 dark:text-emerald-400 flex items-center gap-1.5 pl-2">
                                    <span class="material-symbols-outlined text-base">task_alt</span> VERIFIED
                                </span>
                                <button type="button" 
                                        wire:click="unverifyLine({{ $item->id }})" 
                                        class="px-3 py-1.5 bg-white dark:bg-slate-800 hover:bg-slate-100 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded text-[9px] font-black uppercase tracking-wider transition-all active:scale-95">
                                    Edit / Re-check
                                </button>
                            </div>
                        @elseif($isRemoved)
                            <button type="button" 
                                    wire:click="verifyLine({{ $item->id }})" 
                                    class="flex-1 h-12 text-[10px] font-black tracking-widest bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 rounded-lg transition-all active:scale-95 flex items-center justify-center gap-1.5">
                                <span class="material-symbols-outlined text-sm">undo</span>
                                RESTORE & VERIFY ITEM
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <!-- In-Page Checking Complete Action Banner (Appears when all items are verified/removed) -->
        @if($session->pendingLines === 0 && $session->verifiedLines > 0)
            <div class="my-6 bg-emerald-50 dark:bg-emerald-950/40 border-2 border-emerald-500 rounded-2xl p-5 text-center shadow-lg">
                <span class="material-symbols-outlined text-4xl text-emerald-600 font-bold mb-1">task_alt</span>
                <h4 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-wider">CHECKING COMPLETE</h4>
                <p class="text-xs text-slate-600 dark:text-slate-300 font-medium mb-4">All {{ $session->totalLines }} items have been verified. Continue to review and sign.</p>
                <button type="button" 
                        wire:click="completeChecking" 
                        class="w-full h-12 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs uppercase tracking-widest rounded-xl shadow-md flex items-center justify-center gap-2 transition-all active:scale-95">
                    <span>CONTINUE TO REVIEW</span>
                    <span class="material-symbols-outlined text-base">arrow_forward</span>
                </button>
            </div>
        @endif

        <!-- Session Remarks Textarea -->
        <div class="mb-6 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <label for="sessionRemarks" class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-2">Session Remarks / Notes</label>
            <textarea id="sessionRemarks" 
                      wire:model.defer="sessionRemarks" 
                      rows="2" 
                      class="w-full text-xs font-bold text-slate-700 dark:text-slate-200 p-2.5 border border-slate-200 dark:border-slate-700 rounded-lg focus:outline-none focus:ring-1 focus:ring-slate-400 bg-slate-50 dark:bg-slate-800" 
                      placeholder="Enter any general notes discovered during physical receiving..."></textarea>
        </div>

    @endif

    <!-- ========================================================================= -->
    <!-- STICKY BOTTOM ACTION BAR (Mobile Optimized) -->
    <!-- ========================================================================= -->
    @if($session->status !== \App\Models\ReceivingSession::STATUS_COMPLETED)
        <div class="receiving-bottom-action-bar bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-t border-slate-200 dark:border-slate-800 p-3 shadow-2xl">
            <div class="max-w-2xl mx-auto flex flex-col gap-2">
                @if($missingLocation)
                    <div class="bg-red-50 border border-red-200 text-red-800 text-[10px] font-black uppercase tracking-wider p-2 rounded text-center">
                        Cannot proceed: Bins must be mapped to all verification lines first.
                    </div>
                @endif

                <div class="flex gap-2">
                    @if($session->status === \App\Models\ReceivingSession::STATUS_DRAFT)
                        <button type="button" 
                                wire:click="saveDraft" 
                                class="w-32 h-12 text-[10px] font-black tracking-widest bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 rounded-xl transition-all active:scale-95 flex items-center justify-center gap-1">
                            <span class="material-symbols-outlined text-sm">save</span>
                            SAVE DRAFT
                        </button>

                        <button type="button" 
                                wire:click="completeChecking" 
                                @if($session->pendingLines > 0 || $session->verifiedLines === 0 || $missingLocation) disabled @endif
                                class="flex-1 h-12 text-[10px] font-black tracking-widest text-white rounded-xl transition-all active:scale-95 flex items-center justify-center gap-1.5 shadow-md
                                @if($session->pendingLines > 0 || $session->verifiedLines === 0 || $missingLocation) bg-slate-300 dark:bg-slate-700 shadow-none cursor-not-allowed
                                @else bg-emerald-600 hover:bg-emerald-700 shadow-emerald-200 dark:shadow-none
                                @endif">
                            <span>CONTINUE TO REVIEW</span>
                            <span class="material-symbols-outlined text-sm">arrow_forward</span>
                        </button>

                    @elseif($session->status === \App\Models\ReceivingSession::STATUS_READY_REVIEW)
                        <button type="button" 
                                wire:click="reviewAndConfirm" 
                                @if($missingLocation) disabled @endif
                                class="w-full h-12 text-[10px] font-black tracking-widest text-white rounded-xl transition-all active:scale-95 flex items-center justify-center gap-1.5 shadow-md
                                @if($missingLocation) bg-slate-300 dark:bg-slate-700 shadow-none cursor-not-allowed
                                @else bg-purple-600 hover:bg-purple-700 shadow-purple-200 dark:shadow-none
                                @endif">
                            <span class="material-symbols-outlined text-base">lock</span>
                            CONFIRM REVIEW & PROCEED TO SIGNATURE
                        </button>

                    @elseif($session->status === \App\Models\ReceivingSession::STATUS_REVIEWED)
                        <a href="{{ route('receiving.session.pdf', $session->id) }}" 
                           target="_blank"
                           class="w-32 h-12 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 rounded-xl text-[10px] font-black tracking-widest uppercase flex items-center justify-center gap-1 transition-all active:scale-95">
                            <span class="material-symbols-outlined text-sm">picture_as_pdf</span>
                            F4 PDF
                        </a>

                        <button type="button" 
                                wire:click="finalizeReceiving" 
                                @if($missingLocation) disabled @endif
                                class="flex-1 h-12 text-[10px] font-black tracking-widest text-white rounded-xl transition-all active:scale-95 flex items-center justify-center gap-1.5 shadow-md
                                @if($missingLocation) bg-slate-300 dark:bg-slate-700 shadow-none cursor-not-allowed
                                @else bg-emerald-600 hover:bg-emerald-700 shadow-emerald-200 dark:shadow-none
                                @endif">
                            <span class="material-symbols-outlined text-base">verified_user</span>
                            FINAL COMMIT (MUTASI STOCK)
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Removal Reason Selection Dialog Modal -->
    <div x-show="$wire.showRemoveModal" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
         style="display: none;">
        <div @click.outside="$wire.closeRemoveModal()" 
             class="w-full max-w-sm bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-2xl p-5 relative">
            
            <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest mb-3 pb-2 border-b border-slate-100 dark:border-slate-800">
                Mark Line as Removed
            </h3>

            <div class="space-y-4">
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-2">Select Reason</label>
                    <div class="space-y-2">
                        @foreach(['WRONG WAREHOUSE', 'IMPORTED BY MISTAKE', 'CANCELLED', 'OTHER'] as $reason)
                            <label class="flex items-center gap-2 p-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-850 cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-bold text-slate-800 dark:text-slate-200">
                                <input type="radio" wire:model="removeReason" value="{{ $reason }}" class="text-red-600 focus:ring-red-500">
                                <span>{{ $reason }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                @if($removeReason === 'OTHER')
                    <div>
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-1">Remarks / Explanation</label>
                        <textarea wire:model.defer="removeRemarks" rows="2" class="w-full text-xs font-bold text-slate-800 dark:text-slate-200 p-2 border border-slate-300 dark:border-slate-700 rounded-lg focus:outline-none focus:ring-1 focus:ring-slate-400 bg-slate-50 dark:bg-slate-800" placeholder="Provide reason details..."></textarea>
                        @error('removeRemarks') <span class="text-[9px] text-red-600 font-bold">{{ $message }}</span> @enderror
                    </div>
                @endif

                <div class="flex gap-2 pt-2">
                    <button type="button" wire:click="closeRemoveModal" class="flex-1 h-10 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-lg uppercase tracking-wider">
                        Cancel
                    </button>
                    <button type="button" wire:click="removeLine" class="flex-1 h-10 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-lg uppercase tracking-wider shadow-md shadow-red-200">
                        Confirm Remove
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- DIGITAL SIGNATURE CANVAS MODAL -->
    <div x-data="{ 
            modalOpen: false, 
            activeRole: '', 
            pad: null,
            initPad() {
                this.$nextTick(() => {
                    setTimeout(() => {
                        const canvas = this.$refs.canvas;
                        if (!canvas) return;

                        const rect = canvas.getBoundingClientRect();
                        const width = rect.width || canvas.offsetWidth || 320;
                        const height = rect.height || canvas.offsetHeight || 224;
                        const ratio = Math.max(window.devicePixelRatio || 1, 1);

                        canvas.width = Math.floor(width * ratio);
                        canvas.height = Math.floor(height * ratio);
                        
                        const ctx = canvas.getContext('2d');
                        ctx.scale(ratio, ratio);

                        if (this.pad) {
                            this.pad.off();
                        }

                        this.pad = new SignaturePad(canvas, {
                            backgroundColor: 'rgba(255, 255, 255, 0)',
                            penColor: 'rgb(15, 23, 42)',
                            minWidth: 1.5,
                            maxWidth: 3.5,
                            throttle: 0
                        });
                        this.pad.clear();
                    }, 50);
                });
            },
            clearPad() {
                if (this.pad) this.pad.clear();
            },
            submitSignature() {
                if (this.pad && !this.pad.isEmpty()) {
                    const dataUrl = this.pad.toDataURL('image/png');
                    $wire.saveSignature(this.activeRole, dataUrl);
                    this.modalOpen = false;
                } else {
                    alert('Please sign on the canvas before saving.');
                }
            }
         }"
         x-on:open-sign-modal.window="activeRole = $event.detail.role; modalOpen = true; initPad();"
         x-show="modalOpen" 
         class="fixed inset-0 z-[60] flex items-center justify-center p-3 sm:p-4 bg-slate-900/70 backdrop-blur-sm overflow-y-auto"
         style="display: none; padding-bottom: max(1rem, env(safe-area-inset-bottom, 0px));">
        
        <div @click.outside="modalOpen = false" 
             class="w-[94vw] sm:w-full max-w-lg bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xl p-4 sm:p-5 mx-auto my-auto flex flex-col max-h-[calc(100dvh-2rem)] overflow-y-auto box-border">
            
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3 mb-3 shrink-0">
                <div>
                    <h3 class="text-xs sm:text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest">Draw Signature</h3>
                    <span class="text-[10px] font-mono text-purple-600 font-bold uppercase" x-text="activeRole"></span>
                </div>
                <button type="button" @click="modalOpen = false" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400 hover:text-slate-600 transition-all active:scale-95">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>

            <div class="w-full border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-800/50 mb-4 relative touch-none select-none overflow-hidden shrink-0">
                <canvas x-ref="canvas" class="w-full h-56 sm:h-64 block rounded-xl cursor-crosshair touch-none"></canvas>
            </div>

            <div class="grid grid-cols-3 gap-2 w-full pt-1 shrink-0">
                <button type="button" 
                        @click="clearPad()" 
                        class="h-11 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-black uppercase tracking-wider transition-all active:scale-95 flex items-center justify-center">
                    Clear
                </button>
                <button type="button" 
                        @click="modalOpen = false" 
                        class="h-11 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-xl text-xs font-black uppercase tracking-wider transition-all active:scale-95 flex items-center justify-center">
                    Cancel
                </button>
                <button type="button" 
                        @click="submitSignature()" 
                        class="h-11 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-black uppercase tracking-wider shadow-md shadow-purple-200 dark:shadow-none transition-all active:scale-95 flex items-center justify-center">
                    Save
                </button>
            </div>
        </div>
    </div>
</div>
