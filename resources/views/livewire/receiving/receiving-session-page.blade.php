<div class="p-4 pt-14 pb-24 min-h-screen bg-slate-100/40" 
     x-data="{ 
         showNotification: false, 
         notificationMessage: '', 
         notificationType: 'success' 
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
         class="fixed bottom-4 left-4 right-4 z-50 p-4 rounded-md shadow-lg flex items-center justify-between border uppercase tracking-wider text-xs font-black"
         x-bind:class="{
             'bg-green-50 border-green-200 text-green-800 dark:bg-green-950 dark:border-green-850 dark:text-green-200': notificationType === 'success',
             'bg-red-50 border-red-200 text-red-800 dark:bg-red-955 dark:border-red-850 dark:text-green-200': notificationType === 'error'
         }"
         style="display: none;">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-md" x-text="notificationType === 'success' ? 'check_circle' : 'error'"></span>
            <span x-text="notificationMessage"></span>
        </div>
        <button type="button" @click="showNotification = false" class="text-slate-400 hover:text-slate-655">
            <span class="material-symbols-outlined text-sm">close</span>
        </button>
    </div>

    <!-- Header info / Back button -->
    <div class="mb-4 flex items-center justify-between bg-white dark:bg-slate-900 p-4 rounded-md border border-slate-200 dark:border-slate-800 shadow-sm">
        <div class="flex items-center gap-3">
            <a href="{{ route('outstanding-purchases.show', $session->outstanding_purchase_order_id) }}" 
               class="w-9 h-9 bg-slate-50 hover:bg-slate-100 border border-slate-200 dark:border-slate-800 rounded-md flex items-center justify-center text-slate-550 transition-all active:scale-95">
                <span class="material-symbols-outlined text-lg">arrow_back</span>
            </a>
            <div>
                <h2 class="text-xs font-black text-slate-400 uppercase tracking-widest leading-none">Receiving Session</h2>
                <h1 class="text-sm font-black text-slate-900 dark:text-white tracking-tighter uppercase mt-1">PO: {{ $session->outstandingPurchaseOrder->po_number }}</h1>
            </div>
        </div>
        <div>
            @if($session->status === \App\Models\ReceivingSession::STATUS_DRAFT)
                <span class="bg-amber-100 text-amber-800 text-[9px] font-black uppercase tracking-widest px-2.5 py-1 rounded border border-amber-200">
                    Draft
                </span>
            @elseif($session->status === \App\Models\ReceivingSession::STATUS_READY_REVIEW)
                <span class="bg-blue-100 text-blue-800 text-[9px] font-black uppercase tracking-widest px-2.5 py-1 rounded border border-blue-200">
                    Ready Review
                </span>
            @elseif($session->status === \App\Models\ReceivingSession::STATUS_REVIEWED)
                <span class="bg-purple-100 text-purple-800 text-[9px] font-black uppercase tracking-widest px-2.5 py-1 rounded border border-purple-200">
                    Reviewed
                </span>
            @elseif($session->status === \App\Models\ReceivingSession::STATUS_COMPLETED)
                <span class="bg-green-100 text-green-800 text-[9px] font-black uppercase tracking-widest px-2.5 py-1 rounded border border-green-200">
                    Completed
                </span>
            @else
                <span class="bg-slate-100 text-slate-800 text-[9px] font-black uppercase tracking-widest px-2.5 py-1 rounded border border-slate-200">
                    {{ $session->status }}
                </span>
            @endif
        </div>
    </div>

    <!-- MAIN UX SECTIONS BASED ON SESSION STATUS -->
    @if($session->status === \App\Models\ReceivingSession::STATUS_COMPLETED)
        
        <!-- RECEIVING COMPLETED SCREEN -->
        <div class="mb-6 bg-white dark:bg-slate-900 border border-green-200 dark:border-green-800 rounded-md p-6 shadow-md text-center max-w-md mx-auto">
            <div class="w-12 h-12 bg-green-50 text-green-600 rounded-full flex items-center justify-center mx-auto mb-4 border border-green-150">
                <span class="material-symbols-outlined text-2xl font-black">done_all</span>
            </div>
            
            <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-2">Receiving Completed</h2>
            <p class="text-xs text-slate-500 font-bold mb-5 uppercase">PO: {{ $session->outstandingPurchaseOrder->po_number }}</p>
            
            <div class="bg-slate-50 border border-slate-200/50 rounded-md p-4 mb-6 text-left space-y-3">
                <div class="flex justify-between items-center text-xs">
                    <span class="text-slate-400 font-black uppercase tracking-wider text-[10px]">Received Lines</span>
                    <span class="font-bold text-slate-800">{{ $session->verifiedLines }} / {{ $session->totalLines }} lines</span>
                </div>
                <div class="flex justify-between items-center text-xs border-t border-slate-200/50 pt-2">
                    <span class="text-slate-400 font-black uppercase tracking-wider text-[10px]">Stock Updated</span>
                    <span class="font-black text-green-600">YES</span>
                </div>
                <div class="flex justify-between items-center text-xs border-t border-slate-200/50 pt-2">
                    <span class="text-slate-400 font-black uppercase tracking-wider text-[10px]">Document</span>
                    <span class="font-black text-green-600">READY</span>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <a href="{{ route('receiving.session.pdf', $session->id) }}" 
                   target="_blank"
                   class="h-11 bg-green-600 hover:bg-green-700 text-white rounded-md text-xs font-black tracking-widest uppercase flex items-center justify-center gap-1.5 shadow-md shadow-green-100 transition-all active:scale-95">
                    <span class="material-symbols-outlined text-sm">visibility</span>
                    View PDF
                </a>
                <a href="{{ route('receiving.session.pdf', $session->id) }}" 
                   target="_blank"
                   class="h-11 bg-slate-900 hover:bg-slate-800 text-white rounded-md text-xs font-black tracking-widest uppercase flex items-center justify-center gap-1.5 shadow-md transition-all active:scale-95">
                    <span class="material-symbols-outlined text-sm">print</span>
                    Print PDF
                </a>
                <a href="{{ route('outstanding-purchases') }}" 
                   class="h-11 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 rounded-md text-xs font-black tracking-widest uppercase flex items-center justify-center gap-1 transition-all active:scale-95">
                    Back to Outstanding Purchases
                </a>
            </div>
        </div>

    @else

        <!-- PO Metadata summary -->
        <div class="mb-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md p-4 shadow-sm">
            <div class="grid grid-cols-2 gap-4 text-left">
                <div>
                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">Vendor</span>
                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ $session->outstandingPurchaseOrder->supplier_name_snapshot }}</span>
                </div>
                <div>
                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">PO Date</span>
                    <span class="text-xs font-mono font-bold text-slate-800 dark:text-slate-200">{{ $session->outstandingPurchaseOrder->po_date->format('Y-m-d') }}</span>
                </div>
            </div>
        </div>

        <!-- Progress Dashboard -->
        <div class="mb-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md p-4 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Verification Progress</span>
                <span class="text-xs font-black font-mono text-green-600">
                    {{ $session->verifiedLines + $session->removedLines }} / {{ $session->totalLines }} Processed
                </span>
            </div>
            
            <!-- Progress Bar -->
            <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2 overflow-hidden border border-slate-200/50 mb-3">
                <div class="bg-green-600 h-2 rounded-full transition-all duration-300" style="width: {{ $session->completionPercentage }}%"></div>
            </div>

            <div class="grid grid-cols-3 gap-2">
                <div class="p-2 bg-slate-50 dark:bg-slate-850 rounded border border-slate-100 dark:border-slate-800 text-center">
                    <span class="text-[8px] font-black text-slate-400 tracking-wider block">Verified</span>
                    <span class="text-xs font-black text-green-600 dark:text-green-400 font-mono">{{ $session->verifiedLines }}</span>
                </div>
                <div class="p-2 bg-slate-50 dark:bg-slate-850 rounded border border-slate-100 dark:border-slate-800 text-center">
                    <span class="text-[8px] font-black text-slate-400 tracking-wider block">Removed</span>
                    <span class="text-xs font-black text-red-655 dark:text-red-400 font-mono">{{ $session->removedLines }}</span>
                </div>
                <div class="p-2 bg-slate-50 dark:bg-slate-850 rounded border border-slate-100 dark:border-slate-800 text-center">
                    <span class="text-[8px] font-black text-slate-400 tracking-wider block">Pending</span>
                    <span class="text-xs font-black text-slate-500 font-mono">{{ $session->pendingLines }}</span>
                </div>
            </div>
        </div>

        <!-- Check for missing bins (Required by WMS Business Rules) -->
        @php $missingLocation = false; @endphp

        <!-- Line items list -->
        <div class="space-y-3 mb-6">
            <h3 class="text-[9px] font-black text-slate-400 uppercase tracking-widest px-1">PO Items List</h3>

            @foreach($items as $item)
                @php
                    $isPending = $item->isPending();
                    $isVerified = $item->isVerified();
                    $isRemoved = $item->isRemoved();
                    $isDraft = $session->status === \App\Models\ReceivingSession::STATUS_DRAFT;
                    
                    // Check if bin is assigned in active warehouse
                    $hasBin = \App\Models\Bin::forActiveWarehouse()->where('item_variant_id', $item->item_variant_id)->exists();
                    if (!$hasBin && !$isRemoved) {
                        $missingLocation = true;
                    }
                @endphp
                <div class="bg-white dark:bg-slate-900 border rounded-md p-4 shadow-sm transition-all duration-200
                    @if($isVerified) border-l-4 border-l-green-600 border-slate-200
                    @elseif($isRemoved) border-l-4 border-l-red-600 border-slate-200 opacity-75
                    @else border-l-4 border-l-slate-400 border-slate-200
                    @endif">
                    
                    <!-- Item metadata -->
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h4 class="text-xs font-black text-slate-900 dark:text-white leading-tight">
                                {{ $item->outstandingPurchaseOrderItem->item_name_snapshot }}
                            </h4>
                            <span class="text-[9px] bg-slate-100 dark:bg-slate-800 text-slate-550 dark:text-slate-400 px-1.5 py-0.5 rounded font-mono font-bold mt-1 inline-block">
                                Part: {{ $item->outstandingPurchaseOrderItem->erp_code }}
                            </span>
                        </div>

                        <!-- Status badges -->
                        <div class="flex flex-col items-end gap-1">
                            @if($isVerified)
                                <span class="text-green-600 text-[8px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-green-50 border border-green-200 flex items-center gap-0.5">
                                    <span class="material-symbols-outlined text-[10px]">check_circle</span> Verified
                                </span>
                            @elseif($isRemoved)
                                <span class="text-red-700 text-[8px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-red-50 border border-red-200 flex items-center gap-0.5">
                                    <span class="material-symbols-outlined text-[10px]">delete_forever</span> Removed
                                </span>
                            @else
                                <span class="text-slate-500 text-[8px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-slate-50 border border-slate-200 flex items-center gap-0.5">
                                    <span class="material-symbols-outlined text-[10px]">pending</span> Pending
                                </span>
                            @endif

                            <!-- Location Check Alert -->
                            @if(!$hasBin && !$isRemoved)
                                <span class="text-red-700 text-[8px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-red-50 border border-red-200 flex items-center gap-0.5 animate-pulse mt-1">
                                    <span class="material-symbols-outlined text-[10px]">location_off</span> Location Required
                                </span>
                            @endif
                        </div>
                    </div>

                    @if($isRemoved)
                        <div class="my-2 bg-red-50/50 p-2.5 rounded border border-red-100/50 text-[10px] text-red-800 font-bold uppercase tracking-wide">
                            <div>Reason: {{ $item->removed_reason }}</div>
                            @if($item->remarks)
                                <div class="mt-1 normal-case text-slate-550 font-normal">Notes: {{ $item->remarks }}</div>
                            @endif
                        </div>
                    @endif

                    <!-- Quantities & warning -->
                    <div class="flex items-center justify-between gap-2 mt-4 border-t border-slate-100 dark:border-slate-850 pt-3">
                        <div class="flex items-baseline gap-2">
                            <div class="text-left">
                                <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider block">Expected</span>
                                <span class="text-xs font-bold text-slate-655 font-mono">{{ $item->expected_qty }} {{ $item->outstandingPurchaseOrderItem->unit }}</span>
                            </div>

                            <!-- Over Received Warning Badge -->
                            @if($item->received_qty > $item->expected_qty)
                                <span class="bg-amber-100 text-amber-800 text-[8px] font-black uppercase tracking-widest px-1.5 py-0.5 rounded border border-amber-250 animate-pulse inline-block">
                                    OVER RECEIVED +{{ $item->received_qty - $item->expected_qty }}
                                </span>
                            @endif
                        </div>

                        <!-- Android touch-friendly Counter Controls -->
                        <div class="flex items-center gap-1.5">
                            @if($isDraft && !$isRemoved)
                                <button type="button" 
                                        wire:click="decrementQty({{ $item->id }})" 
                                        class="w-11 h-11 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-md flex items-center justify-center font-bold text-lg text-slate-700 transition-all active:scale-90 select-none">
                                    -
                                </button>
                            @endif

                            <div class="w-16 h-11 bg-slate-50 border border-slate-200 rounded-md flex items-center justify-center font-mono font-bold text-sm text-slate-900">
                                @if($isDraft && !$isRemoved)
                                    <input type="number" 
                                           value="{{ $item->received_qty }}"
                                           wire:change="setQtyManual({{ $item->id }}, $event.target.value)"
                                           class="w-full h-full text-center bg-transparent border-none focus:outline-none focus:ring-0 p-0 font-mono text-sm [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
                                @else
                                    <span>{{ $item->received_qty }}</span>
                                @endif
                            </div>

                            @if($isDraft && !$isRemoved)
                                <button type="button" 
                                        wire:click="incrementQty({{ $item->id }})" 
                                        class="w-11 h-11 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-md flex items-center justify-center font-bold text-lg text-slate-700 transition-all active:scale-90 select-none">
                                    +
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- Touch target Verify / Remove Action Buttons -->
                    @if($isDraft)
                        <div class="flex gap-2 mt-4 pt-3 border-t border-slate-100 dark:border-slate-850">
                            @if(!$isRemoved)
                                <!-- Verify Button -->
                                <button type="button" 
                                        wire:click="verifyLine({{ $item->id }})" 
                                        class="flex-1 h-11 text-[10px] font-black tracking-widest rounded-md transition-all active:scale-95 flex items-center justify-center gap-1.5
                                        @if($isVerified) bg-green-50 text-green-700 border border-green-200 hover:bg-green-100
                                        @else bg-green-600 text-white hover:bg-green-700 shadow-md shadow-green-100
                                        @endif">
                                    <span class="material-symbols-outlined text-sm">check_circle</span>
                                    {{ $isVerified ? 'VERIFIED' : 'VERIFY' }}
                                </button>

                                <!-- Remove Button -->
                                <button type="button" 
                                        wire:click="openRemoveModal({{ $item->id }})" 
                                        class="w-24 h-11 bg-slate-50 hover:bg-slate-100 text-slate-550 border border-slate-200 rounded-md text-[10px] font-black tracking-widest transition-all active:scale-95 flex items-center justify-center gap-1">
                                    <span class="material-symbols-outlined text-sm">delete</span>
                                    REMOVE
                                </button>
                            @else
                                <!-- Revert / Re-verify button if item was removed -->
                                <button type="button" 
                                        wire:click="verifyLine({{ $item->id }})" 
                                        class="flex-1 h-11 text-[10px] font-black tracking-widest bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 rounded-md transition-all active:scale-95 flex items-center justify-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm">undo</span>
                                    RESTORE & VERIFY ITEM
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- Session Remarks Textarea / Label -->
        <div class="mb-6 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md p-4 shadow-sm">
            <label for="sessionRemarks" class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-2">Session Remarks / Notes</label>
            @if($session->status === \App\Models\ReceivingSession::STATUS_DRAFT)
                <textarea id="sessionRemarks" 
                          wire:model.defer="sessionRemarks" 
                          rows="2" 
                          class="w-full text-xs font-bold text-slate-700 p-2.5 border border-slate-200 rounded focus:outline-none focus:ring-1 focus:ring-slate-400" 
                          placeholder="ENTER ANY GENERAL NOTES OR DISCREPANCIES DISCOVERED PHYSICAL RECEIVING..."></textarea>
            @else
                <p class="text-xs font-bold text-slate-655 uppercase tracking-wide leading-relaxed bg-slate-50 p-2.5 rounded border border-slate-100">
                    {{ $session->remarks ?: 'NO REMARKS RECORDED.' }}
                </p>
            @endif
        </div>

        <!-- DIGITAL SIGNATURE WORKFLOW (Visible in REVIEWED state) -->
        @if($session->status === \App\Models\ReceivingSession::STATUS_REVIEWED)
            <div class="mb-6 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md p-4 shadow-sm">
                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">Digital Signature Approvals</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    
                    <!-- 1. DISERAHKAN OLEH -->
                    <div class="border border-slate-200 rounded-md p-4 flex flex-col items-center justify-between text-center min-h-[170px] bg-slate-50/50">
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block mb-2">Diserahkan Oleh</span>
                        @if($diserahkanSig)
                            <div class="w-full flex flex-col items-center">
                                <img src="{{ 'data:image/png;base64,' . base64_encode(Storage::disk('public')->get($diserahkanSig->signature_path)) }}" class="max-h-[60px] max-w-[120px] object-contain bg-white border rounded p-1 mb-2" />
                                <span class="text-[9px] text-slate-500 font-bold block mb-1">Signed: {{ $diserahkanSig->signed_at->format('d/m/Y H:i') }}</span>
                                <button type="button" wire:click="clearSignature('DISERAHKAN_OLEH')" class="text-red-600 hover:text-red-700 text-[9px] font-black uppercase tracking-wider mt-1">Clear Signature</button>
                            </div>
                        @else
                            <button type="button" @click="$dispatch('open-sign-modal', { role: 'DISERAHKAN_OLEH' })" class="w-full h-11 border-2 border-dashed border-slate-300 hover:border-slate-450 hover:bg-slate-100 rounded-md text-[10px] font-black tracking-widest uppercase text-slate-550 transition-all flex items-center justify-center gap-1.5">
                                <span class="material-symbols-outlined text-sm">edit_square</span> Sign
                            </button>
                        @endif
                    </div>

                    <!-- 2. DITERIMA/DICEK OLEH -->
                    <div class="border border-slate-200 rounded-md p-4 flex flex-col items-center justify-between text-center min-h-[170px] bg-slate-50/50">
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block mb-2">Diterima / Dicek Oleh</span>
                        @if($diterimaSig)
                            <div class="w-full flex flex-col items-center">
                                <img src="{{ 'data:image/png;base64,' . base64_encode(Storage::disk('public')->get($diterimaSig->signature_path)) }}" class="max-h-[60px] max-w-[120px] object-contain bg-white border rounded p-1 mb-2" />
                                <span class="text-[9px] text-slate-500 font-bold block mb-1">Signed: {{ $diterimaSig->signed_at->format('d/m/Y H:i') }}</span>
                                <button type="button" wire:click="clearSignature('DITERIMA_OLEH')" class="text-red-655 hover:text-red-700 text-[9px] font-black uppercase tracking-wider mt-1">Clear Signature</button>
                            </div>
                        @else
                            <button type="button" @click="$dispatch('open-sign-modal', { role: 'DITERIMA_OLEH' })" class="w-full h-11 border-2 border-dashed border-slate-300 hover:border-slate-450 hover:bg-slate-100 rounded-md text-[10px] font-black tracking-widest uppercase text-slate-550 transition-all flex items-center justify-center gap-1.5">
                                <span class="material-symbols-outlined text-sm">edit_square</span> Sign
                            </button>
                        @endif
                    </div>

                    <!-- 3. BAG. GUDANG -->
                    <div class="border border-slate-200 rounded-md p-4 flex flex-col items-center justify-between text-center min-h-[170px] bg-slate-50/50">
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block mb-2">Bag. Gudang</span>
                        @if($gudangSig)
                            <div class="w-full flex flex-col items-center">
                                <img src="{{ 'data:image/png;base64,' . base64_encode(Storage::disk('public')->get($gudangSig->signature_path)) }}" class="max-h-[60px] max-w-[120px] object-contain bg-white border rounded p-1 mb-2" />
                                <span class="text-[9px] text-slate-500 font-bold block mb-1">Signed: {{ $gudangSig->signed_at->format('d/m/Y H:i') }}</span>
                                <button type="button" wire:click="clearSignature('BAG_GUDANG')" class="text-red-655 hover:text-red-700 text-[9px] font-black uppercase tracking-wider mt-1">Clear Signature</button>
                            </div>
                        @else
                            <button type="button" @click="$dispatch('open-sign-modal', { role: 'BAG_GUDANG' })" class="w-full h-11 border-2 border-dashed border-slate-300 hover:border-slate-450 hover:bg-slate-100 rounded-md text-[10px] font-black tracking-widest uppercase text-slate-550 transition-all flex items-center justify-center gap-1.5">
                                <span class="material-symbols-outlined text-sm">edit_square</span> Sign
                            </button>
                        @endif
                    </div>

                </div>
            </div>
        @endif

        <!-- Bottom Action Button bar -->
        <div class="bg-white border-t border-slate-200 p-4 fixed bottom-0 left-0 right-0 lg:left-[84px] z-40 flex flex-col gap-2 shadow-lg">
            
            @if($missingLocation)
                <!-- Alert if locations are missing -->
                <div class="bg-red-50 border border-red-200 text-red-800 text-[10px] font-black uppercase tracking-wider p-2.5 rounded text-center">
                    Cannot proceed: Bins must be mapped to all verification lines first.
                </div>
            @endif

            <div class="flex gap-3">
                @if($session->status === \App\Models\ReceivingSession::STATUS_DRAFT)
                    
                    <button type="button" 
                            wire:click="saveDraft" 
                            class="flex-1 h-12 text-[10px] font-black tracking-widest bg-slate-900 hover:bg-slate-800 text-white rounded-md transition-all active:scale-95 flex items-center justify-center gap-1.5 shadow-md">
                        <span class="material-symbols-outlined text-sm">save</span>
                        SAVE DRAFT
                    </button>

                    <button type="button" 
                            wire:click="completeVerification" 
                            @if($session->pendingLines > 0 || $session->verifiedLines === 0 || $missingLocation) disabled @endif
                            class="flex-1 h-12 text-[10px] font-black tracking-widest text-white rounded-md transition-all active:scale-95 flex items-center justify-center gap-1.5 shadow-md
                            @if($session->pendingLines > 0 || $session->verifiedLines === 0 || $missingLocation) bg-slate-300 shadow-none cursor-not-allowed
                            @else bg-green-600 hover:bg-green-700 shadow-green-100
                            @endif">
                        <span class="material-symbols-outlined text-sm">assignment_turned_in</span>
                        COMPLETE VERIFICATION
                    </button>

                @elseif($session->status === \App\Models\ReceivingSession::STATUS_READY_REVIEW)
                    
                    <button type="button" 
                            wire:click="reviewAndConfirm" 
                            @if($missingLocation) disabled @endif
                            class="w-full h-12 text-[10px] font-black tracking-widest text-white rounded-md transition-all active:scale-95 flex items-center justify-center gap-1.5 shadow-md
                            @if($missingLocation) bg-slate-300 shadow-none cursor-not-allowed
                            @else bg-purple-600 hover:bg-purple-700 shadow-purple-100
                            @endif">
                        <span class="material-symbols-outlined text-sm">rate_review</span>
                        REVIEW & CONFIRM
                    </button>

                @elseif($session->status === \App\Models\ReceivingSession::STATUS_REVIEWED)
                    
                    @php
                        $signaturesCount = \App\Models\ReceivingSignature::where('receiving_session_id', $session->id)->count();
                    @endphp

                    <button type="button" 
                            wire:click="finalizeReceiving" 
                            @if($signaturesCount < 3 || $missingLocation) disabled @endif
                            class="w-full h-12 text-[10px] font-black tracking-widest text-white rounded-md transition-all active:scale-95 flex items-center justify-center gap-1.5 shadow-md
                            @if($signaturesCount < 3 || $missingLocation) bg-slate-300 shadow-none cursor-not-allowed
                            @else bg-green-600 hover:bg-green-700 shadow-green-100
                            @endif">
                        <span class="material-symbols-outlined text-sm">verified_user</span>
                        FINALIZE RECEIVING
                    </button>

                @endif
            </div>
        </div>

    @endif

    <!-- Removal Reason Selection Dialog Modal -->
    <div x-show="$wire.showRemoveModal" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
         style="display: none;">
        <div @click.outside="$wire.closeRemoveModal()" 
             class="w-full max-w-sm bg-white rounded-lg border border-slate-200 shadow-2xl p-5 relative">
            
            <h3 class="text-xs font-black text-slate-900 uppercase tracking-widest mb-3 pb-2 border-b border-slate-100">
                Item Removal Verification
            </h3>

            <div class="space-y-4">
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-1">Reason for Removal</label>
                    <select wire:model="removeReason" 
                            class="w-full text-xs font-black uppercase p-2 border border-slate-200 rounded focus:outline-none">
                        <option value="WRONG WAREHOUSE">WRONG WAREHOUSE</option>
                        <option value="IMPORTED BY MISTAKE">IMPORTED BY MISTAKE</option>
                        <option value="CANCELLED">CANCELLED</option>
                        <option value="OTHER">OTHER</option>
                    </select>
                </div>

                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-1">
                        Explanation / Note
                        <span x-show="$wire.removeReason === 'OTHER'" class="text-red-500 font-bold">*</span>
                    </label>
                    <textarea wire:model="removeRemarks" 
                              rows="3" 
                              class="w-full text-xs font-bold p-2 border border-slate-200 rounded focus:outline-none" 
                              placeholder="Describe why this line is being removed..."></textarea>
                    @error('removeRemarks')
                        <span class="text-[9px] font-bold text-red-500 uppercase tracking-wider block mt-1">
                            {{ $message }}
                        </span>
                    @enderror
                </div>
            </div>

            <div class="flex gap-2 mt-5">
                <button type="button" 
                        @click="$wire.closeRemoveModal()" 
                        class="flex-1 h-11 bg-slate-50 hover:bg-slate-100 text-slate-655 border border-slate-200 rounded text-[10px] font-black tracking-widest uppercase active:scale-95 transition-all">
                    Cancel
                </button>
                <button type="button" 
                        wire:click="removeLine()" 
                        class="flex-1 h-11 bg-red-600 hover:bg-red-700 text-white rounded text-[10px] font-black tracking-widest uppercase shadow-md shadow-red-100 active:scale-95 transition-all">
                    Remove Line
                </button>
            </div>
        </div>
    </div>

    <!-- TOUCHSCREEN DIGITAL SIGNATURE CANVAS OVERLAY MODAL -->
    <div x-data="signatureModal()" 
         x-on:open-sign-modal.window="openModal($event.detail.role)"
         x-show="isOpen"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
         style="display: none;">
        <div class="w-full max-w-sm bg-white rounded-lg border border-slate-200 shadow-2xl p-5">
            <h3 class="text-xs font-black text-slate-900 uppercase tracking-widest mb-3 pb-2 border-b border-slate-100 flex items-center justify-between">
                <span>Sign: <span x-text="roleLabel"></span></span>
                <button @click="closeModal()" class="text-slate-400 hover:text-slate-600">
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>
            </h3>
            
            <div class="bg-slate-100 border border-slate-200 rounded-md p-1 overflow-hidden flex items-center justify-center relative" style="height: 180px;">
                <canvas x-ref="canvas" class="w-full h-full bg-white cursor-crosshair touch-none" style="display: block; border-radius: 4px;"></canvas>
            </div>
            
            <div class="flex gap-2 mt-4">
                <button type="button" @click="clear()" class="flex-1 h-11 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 rounded text-[10px] font-black tracking-widest uppercase active:scale-95 transition-all">
                    Clear
                </button>
                <button type="button" @click="save()" class="flex-1 h-11 bg-green-600 hover:bg-green-700 text-white rounded text-[10px] font-black tracking-widest uppercase shadow-md shadow-green-100 active:scale-95 transition-all">
                    Confirm Signature
                </button>
            </div>
        </div>
    </div>

    <!-- Script to tie signature pad library to Alpine modal state -->
    <script>
        function signatureModal() {
            return {
                isOpen: false,
                role: '',
                roleLabel: '',
                pad: null,
                openModal(role) {
                    this.role = role;
                    this.roleLabel = role.replace('_', ' ');
                    this.isOpen = true;
                    
                    this.$nextTick(() => {
                        const canvas = this.$refs.canvas;
                        const rect = canvas.getBoundingClientRect();
                        canvas.width = rect.width;
                        canvas.height = rect.height;
                        
                        if (this.pad) {
                            this.pad.clear();
                        } else {
                            this.pad = new SignaturePad(canvas, {
                                backgroundColor: 'rgb(255, 255, 255)'
                            });
                        }
                    });
                },
                closeModal() {
                    this.isOpen = false;
                    if (this.pad) {
                        this.pad.clear();
                    }
                },
                clear() {
                    if (this.pad) {
                        this.pad.clear();
                    }
                },
                save() {
                    if (!this.pad || this.pad.isEmpty()) {
                        alert("Please provide a signature before saving.");
                        return;
                    }
                    const dataUrl = this.pad.toDataURL('image/png');
                    
                    // Call Livewire saveSignature method directly using Alpine this.$wire helper
                    this.$wire.saveSignature(this.role, dataUrl);
                    this.closeModal();
                }
            };
        }
    </script>
</div>
