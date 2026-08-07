<div class="pt-24 px-4 pb-6 lg:px-8 min-h-screen flex flex-col bg-slate-50/30">
    <!-- Header -->
    <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4 mb-6">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-slate-900 rounded-lg flex items-center justify-center text-green-400 shadow-sm">
                <span class="material-symbols-outlined text-2xl">receipt_long</span>
            </div>
            <div>
                <h1 class="text-3xl font-black tracking-tight text-slate-900">Outstanding Purchases</h1>
                <p class="text-xs font-bold text-slate-500 mt-1 uppercase tracking-widest text-[9px]">ERP Sync Ledger</p>
            </div>
        </div>

        <!-- Compact Dashboard Strip positioned beside title -->
        <div class="flex flex-wrap items-center gap-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg p-1.5 shadow-sm max-w-full overflow-x-auto">
            <!-- Total PO Card -->
            <button wire:click="$set('filterStatus', 'all')" 
                class="flex items-center gap-1.5 px-3 py-1.5 rounded transition-all duration-200 group border text-[11px] font-black shrink-0 {{ $filterStatus === 'all' ? 'bg-slate-900 text-white border-slate-900 shadow-sm dark:bg-slate-750 dark:border-slate-700' : 'bg-slate-50 dark:bg-slate-800 text-slate-665 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 hover:scale-105 active:scale-95' }}">
                <span class="material-symbols-outlined text-sm {{ $filterStatus === 'all' ? 'text-white' : 'text-slate-500' }}">receipt_long</span>
                <span>Total PO</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-black {{ $filterStatus === 'all' ? 'bg-slate-800 text-white dark:bg-slate-900' : 'bg-slate-200/60 text-slate-700 dark:bg-slate-700 dark:text-slate-300' }}">
                    {{ number_format($counts['total']) }}
                </span>
            </button>

            <!-- Pending -->
            <button wire:click="$set('filterStatus', 'pending')" 
                class="flex items-center gap-1.5 px-3 py-1.5 rounded transition-all duration-200 group border text-[11px] font-black shrink-0 {{ $filterStatus === 'pending' ? 'bg-blue-600 text-white border-blue-600 shadow-sm shadow-blue-600/10' : 'bg-blue-50 dark:bg-blue-950/10 text-blue-700 dark:text-blue-400 border-blue-200 dark:border-blue-900/50 hover:bg-blue-50 dark:hover:bg-blue-950/20 hover:scale-105 active:scale-95' }}">
                <span class="material-symbols-outlined text-sm {{ $filterStatus === 'pending' ? 'text-white' : 'text-blue-600' }}">hourglass_empty</span>
                <span>Pending</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-black {{ $filterStatus === 'pending' ? 'bg-blue-700 text-white' : 'bg-blue-100 text-blue-800 dark:bg-blue-950/30' }}">
                    {{ number_format($counts['pending']) }}
                </span>
            </button>

            <!-- Partial -->
            <button wire:click="$set('filterStatus', 'partial')" 
                class="flex items-center gap-1.5 px-3 py-1.5 rounded transition-all duration-200 group border text-[11px] font-black shrink-0 {{ $filterStatus === 'partial' ? 'bg-amber-500 text-white border-amber-500 shadow-sm shadow-amber-500/10 dark:bg-amber-600 dark:border-amber-600' : 'bg-amber-50 dark:bg-amber-950/10 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-900/50 hover:bg-amber-50 dark:hover:bg-amber-950/20 hover:scale-105 active:scale-95' }}">
                <span class="material-symbols-outlined text-sm {{ $filterStatus === 'partial' ? 'text-white' : 'text-amber-500' }}">incomplete_circle</span>
                <span>Partial</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-black {{ $filterStatus === 'partial' ? 'bg-amber-600 text-white dark:bg-amber-700' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/30' }}">
                    {{ number_format($counts['partial']) }}
                </span>
            </button>

            <!-- Closed -->
            <button wire:click="$set('filterStatus', 'closed')" 
                class="flex items-center gap-1.5 px-3 py-1.5 rounded transition-all duration-200 group border text-[11px] font-black shrink-0 {{ $filterStatus === 'closed' ? 'bg-slate-600 text-white border-slate-600 shadow-sm shadow-slate-600/10' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-350 border-slate-200 dark:border-slate-700 hover:bg-slate-205 dark:hover:bg-slate-750 hover:scale-105 active:scale-95' }}">
                <span class="material-symbols-outlined text-sm {{ $filterStatus === 'closed' ? 'text-white' : 'text-slate-500' }}">check_circle</span>
                <span>Closed</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-black {{ $filterStatus === 'closed' ? 'bg-slate-700 text-white' : 'bg-slate-200 text-slate-700 dark:bg-slate-900' }}">
                    {{ number_format($counts['closed']) }}
                </span>
            </button>

            <!-- Ready to Receive (Green) -->
            <button wire:click="$set('filterStatus', 'ready_to_receive')" 
                class="flex items-center gap-1.5 px-3 py-1.5 rounded transition-all duration-200 group border text-[11px] font-black shrink-0 {{ $filterStatus === 'ready_to_receive' ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm shadow-emerald-600/10' : 'bg-emerald-50 dark:bg-emerald-950/10 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-900/50 hover:bg-emerald-50 dark:hover:bg-emerald-950/20 hover:scale-105 active:scale-95' }}">
                <span class="material-symbols-outlined text-sm {{ $filterStatus === 'ready_to_receive' ? 'text-white' : 'text-emerald-600' }}">task_alt</span>
                <span>Ready</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-black {{ $filterStatus === 'ready_to_receive' ? 'bg-emerald-700 text-white' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/30' }}">
                    {{ number_format($counts['ready']) }}
                </span>
            </button>

            <!-- Needs Catalog (Red) -->
            <button wire:click="$set('filterStatus', 'needs_catalog')" 
                class="flex items-center gap-1.5 px-3 py-1.5 rounded transition-all duration-200 group border text-[11px] font-black shrink-0 {{ $filterStatus === 'needs_catalog' ? 'bg-rose-600 text-white border-rose-600 shadow-sm shadow-rose-600/10' : 'bg-rose-50 dark:bg-rose-950/10 text-rose-700 dark:text-rose-455 border-rose-200 dark:border-rose-900/50 hover:bg-rose-50 dark:hover:bg-rose-950/20 hover:scale-105 active:scale-95' }}">
                <span class="material-symbols-outlined text-sm {{ $filterStatus === 'needs_catalog' ? 'text-white' : 'text-rose-600' }}">warning</span>
                <span>Needs Catalog</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-black {{ $filterStatus === 'needs_catalog' ? 'bg-rose-700 text-white' : 'bg-rose-100 text-rose-800 dark:bg-rose-950/30' }}">
                    {{ number_format($counts['needs_catalog']) }}
                </span>
            </button>

            <!-- Archived -->
            <button wire:click="$set('filterStatus', 'archived')" 
                class="flex items-center gap-1.5 px-3 py-1.5 rounded transition-all duration-200 group border text-[11px] font-black shrink-0 {{ $filterStatus === 'archived' ? 'bg-slate-700 text-white border-slate-700 shadow-sm shadow-slate-700/10' : 'bg-slate-50 dark:bg-slate-800 text-slate-655 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 hover:scale-105 active:scale-95' }}">
                <span class="material-symbols-outlined text-sm {{ $filterStatus === 'archived' ? 'text-white' : 'text-slate-500' }}">archive</span>
                <span>Archived</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-black {{ $filterStatus === 'archived' ? 'bg-slate-800 text-white dark:bg-slate-900' : 'bg-slate-200/60 text-slate-700 dark:bg-slate-700 dark:text-slate-300' }}">
                    {{ number_format($counts['archived']) }}
                </span>
            </button>
        </div>
    </div>

    <!-- Toolbar matching Inventory Planning layout density -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md py-2.5 px-4 shadow-sm mb-4 flex flex-col xl:flex-row gap-3 items-center justify-between">
        <!-- Search Bar -->
        <div class="relative w-full xl:w-96 flex-1 min-w-0">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
            <input wire:model.live.debounce.300ms="search" 
                class="w-full pl-9 pr-4 h-9 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md focus:ring-1 focus:ring-primary/20 focus:border-primary text-xs font-bold placeholder:text-slate-400 transition-all text-on-surface"
                placeholder="Search PO, Supplier, ERP Code, Item..."
                type="text"/>
            
            <div wire:loading wire:target="search" class="absolute right-4 top-1/2 -translate-y-1/2">
                <span class="material-symbols-outlined animate-spin text-primary text-sm">progress_activity</span>
            </div>
        </div>

        <!-- Actions / Toolbar Area -->
        <div class="flex flex-wrap gap-2 w-full xl:w-auto shrink-0 items-center justify-end">
            <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest pl-2">
                Viewing Filter: <span class="text-slate-800 dark:text-slate-200 underline decoration-2">{{ str_replace('_', ' ', $filterStatus) }}</span>
            </div>
            <div class="w-px h-5 bg-slate-200 dark:bg-slate-800 mx-2"></div>
            <a href="{{ route('outstanding-purchases.import') }}" class="h-9 px-4 text-[10px] font-black text-white green-action-gradient rounded-md shadow-md shadow-green-200 active:scale-95 transition-all flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-md">cloud_upload</span>
                IMPORT TERMINAL
            </a>
        </div>
    </div>

    <!-- Data Table Container matching Inventory Planning -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md shadow-sm overflow-hidden mb-4 flex-1">
        <div class="overflow-x-auto overflow-y-visible">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="px-4 py-2 text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest">PO Number</th>
                        <th class="px-4 py-2 text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest">Supplier</th>
                        <th class="px-4 py-2 text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest">PO Date</th>
                        <th class="px-4 py-2 text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest text-center">Total Items</th>
                        <th class="px-4 py-2 text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest text-right">Pending Qty</th>
                        <th class="px-4 py-2 text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest text-center">Readiness</th>
                        <th class="px-4 py-2 text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest text-center">PO Status</th>
                        <th class="px-4 py-2 text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($orders as $order)
                        @php
                            $pendingQty = $order->items->sum('pending_qty');
                            $readiness = $order->receiving_readiness;
                        @endphp
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-805/30 transition-colors">
                            <td class="px-4 py-3.5 text-xs font-black text-slate-900 dark:text-white font-mono">
                                {{ $order->po_number }}
                            </td>
                            <td class="px-4 py-3.5 text-xs font-medium text-slate-700 dark:text-slate-300">
                                {{ $order->supplier_name_snapshot }}
                                @if($order->supplier_code_snapshot)
                                    <span class="text-[8px] bg-slate-100 dark:bg-slate-800 text-slate-550 dark:text-slate-400 px-1 py-0.5 rounded font-mono ml-1">{{ $order->supplier_code_snapshot }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-xs font-mono text-slate-500">
                                {{ $order->po_date ? $order->po_date->format('Y-m-d') : '-' }}
                            </td>
                            <td class="px-4 py-3.5 text-xs font-bold text-slate-655 dark:text-slate-400 text-center font-mono">
                                {{ $order->items->count() }}
                            </td>
                            <td class="px-4 py-3.5 text-xs font-mono font-bold text-right text-slate-500">
                                {{ number_format($pendingQty) }}
                            </td>
                            <!-- Readiness (Strong Colors: Green/Red) -->
                            <td class="px-4 py-3.5 text-center">
                                @if($readiness === \App\Models\OutstandingPurchaseOrder::READINESS_READY)
                                    <span class="bg-green-500 text-white text-[8px] font-black uppercase tracking-widest px-2 py-0.5 rounded shadow-sm">
                                        Ready
                                    </span>
                                @else
                                    <span class="bg-red-650 text-white text-[8px] font-black uppercase tracking-widest px-2 py-0.5 rounded shadow-sm">
                                        Needs Catalog
                                    </span>
                                @endif
                            </td>
                            <!-- PO Status (Secondary Soft Colors) -->
                            <td class="px-4 py-3.5 text-center">
                                @if($order->status === \App\Models\OutstandingPurchaseOrder::STATUS_PENDING)
                                    <span class="text-slate-500 text-[8px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded border border-slate-200 bg-slate-50">
                                        Pending
                                    </span>
                                @elseif($order->status === \App\Models\OutstandingPurchaseOrder::STATUS_PARTIAL)
                                    <span class="text-slate-500 text-[8px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded border border-slate-200 bg-slate-50">
                                        Partial
                                    </span>
                                @elseif($order->status === \App\Models\OutstandingPurchaseOrder::STATUS_CLOSED)
                                    <span class="text-slate-500 text-[8px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded border border-slate-200 bg-slate-50">
                                        Closed
                                    </span>
                                @endif
                            </td>
                            <!-- Action: View Details -->
                            <td class="px-4 py-3.5 text-center">
                                <a href="{{ route('outstanding-purchases.show', $order->id) }}" class="h-8 px-4 bg-slate-900 hover:bg-slate-800 text-white text-[9px] font-black uppercase tracking-widest rounded transition-all inline-flex items-center justify-center active:scale-95 shadow-sm">
                                    View Details
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-8 opacity-40">
                                <span class="material-symbols-outlined text-4xl text-slate-300">receipt_long</span>
                                <p class="text-[10px] font-black text-slate-400 mt-2 uppercase tracking-widest">No outstanding purchase orders found.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($orders->hasPages())
            <div class="px-4 py-3 bg-slate-50 dark:bg-slate-805/50 border-t border-slate-200 dark:border-slate-850">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</div>
