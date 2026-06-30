<div class="w-full flex flex-col min-h-screen p-6 bg-slate-50">
    
    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-black uppercase tracking-widest text-slate-400 bg-slate-200/50 px-2 py-0.5 rounded font-mono">GOVERNANCE</span>
                <span class="text-xs font-black uppercase tracking-widest text-green-700 bg-green-100 px-2 py-0.5 rounded font-mono">Stock Opname Mutator Queue</span>
            </div>
            <h1 class="text-xl font-black text-slate-900 uppercase tracking-tight mt-1">Inventory Adjustments</h1>
            <p class="text-xs text-slate-500 font-medium">Audit adjustments waiting for Manager PPIC authorization before physical ledger mutation.</p>
        </div>
    </div>

    <!-- FLASH MESSAGES -->
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
    @if(session()->has('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold px-4 py-2.5 rounded-lg mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-base">error</span>
            {{ session('error') }}
        </div>
    @endif

    <!-- KPI METRICS HEADER -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Card 1: Waiting Approval -->
        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block font-mono">Waiting Approval</span>
                <span class="text-2xl font-mono font-black text-slate-800 mt-1 block">{{ $kpis['waiting'] }}</span>
            </div>
            <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center">
                <span class="material-symbols-outlined text-xl">pending_actions</span>
            </div>
        </div>

        <!-- Card 2: Approved Today -->
        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block font-mono">Approved Today</span>
                <span class="text-2xl font-mono font-black text-blue-600 mt-1 block">{{ $kpis['approved_today'] }}</span>
            </div>
            <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center">
                <span class="material-symbols-outlined text-xl">task_alt</span>
            </div>
        </div>

        <!-- Card 3: Rejected Today -->
        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block font-mono">Rejected Today</span>
                <span class="text-2xl font-mono font-black text-rose-600 mt-1 block">{{ $kpis['rejected_today'] }}</span>
            </div>
            <div class="w-10 h-10 bg-rose-50 text-rose-600 rounded-lg flex items-center justify-center">
                <span class="material-symbols-outlined text-xl">cancel</span>
            </div>
        </div>

        <!-- Card 4: Average Approval Time -->
        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block font-mono">Avg Approval Time</span>
                <span class="text-2xl font-mono font-black text-slate-500 mt-1 block">{{ $kpis['avg_time'] }}</span>
            </div>
            <div class="w-10 h-10 bg-slate-50 text-slate-500 rounded-lg flex items-center justify-center">
                <span class="material-symbols-outlined text-xl">schedule</span>
            </div>
        </div>
    </div>

    <!-- STICKY FILTER BAR -->
    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm mb-6 sticky top-[44px] z-30">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <!-- Date Filter -->
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Start Date</label>
                    <input type="date" wire:model.live="startDate" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-mono font-bold text-slate-800 focus:outline-none focus:border-green-650">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">End Date</label>
                    <input type="date" wire:model.live="endDate" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-mono font-bold text-slate-800 focus:outline-none focus:border-green-650">
                </div>
            </div>

            <!-- Operator Filter (Manager only) -->
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Operator</label>
                @if(auth()->user()->role === 'manager')
                    <select wire:model.live="operatorId" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-bold text-slate-800 focus:outline-none focus:border-green-650">
                        <option value="">ALL OPERATORS</option>
                        @foreach($operators as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                @else
                    <input type="text" readonly value="{{ auth()->user()->name }}" class="w-full bg-slate-100 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-bold text-slate-500 cursor-not-allowed">
                @endif
            </div>

            <!-- Status Filter -->
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Status</label>
                <select wire:model.live="statusFilter" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-bold text-slate-800 focus:outline-none focus:border-green-650">
                    <option value="ALL">ALL STATUS</option>
                    <option value="WAITING">WAITING</option>
                    <option value="APPROVED">APPROVED</option>
                    <option value="REJECTED">REJECTED</option>
                </select>
            </div>

            <!-- Preset Buttons Row -->
            <div class="flex items-center gap-1.5">
                <button wire:click="setDatePreset('today')" class="px-2.5 py-1.5 bg-slate-150 hover:bg-slate-200 text-slate-700 rounded-md text-[10px] font-black uppercase tracking-wider transition-all font-mono font-bold">Today</button>
                <button wire:click="setDatePreset('yesterday')" class="px-2.5 py-1.5 bg-slate-150 hover:bg-slate-200 text-slate-700 rounded-md text-[10px] font-black uppercase tracking-wider transition-all font-mono font-bold">Yesterday</button>
                <button wire:click="setDatePreset('this_week')" class="px-2.5 py-1.5 bg-slate-150 hover:bg-slate-200 text-slate-700 rounded-md text-[10px] font-black uppercase tracking-wider transition-all font-mono font-bold">This Week</button>
                <button wire:click="setDatePreset('this_month')" class="px-2.5 py-1.5 bg-slate-150 hover:bg-slate-200 text-slate-700 rounded-md text-[10px] font-black uppercase tracking-wider transition-all font-mono font-bold">This Month</button>
            </div>
        </div>
    </div>

    <!-- ADJUSTMENTS HEADER TABLE -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[9px] font-black uppercase tracking-widest text-slate-400 font-mono">
                        <th class="py-3 px-4 w-12 text-center">Detail</th>
                        <th class="py-3 px-4">Adjustment No</th>
                        <th class="py-3 px-4">Warehouse</th>
                        <th class="py-3 px-4">Operator</th>
                        <th class="py-3 px-4">Business Date</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-center">Waiting</th>
                        <th class="py-3 px-4 text-center">Approved</th>
                        <th class="py-3 px-4 text-center">Rejected</th>
                        <th class="py-3 px-4">Age</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-150">
                    @forelse($headers as $header)
                        @php
                            $isExpanded = in_array($header->id, $expandedHeaders);
                            
                            // Derived Status logic
                            $derivedStatus = 'REJECTED';
                            $statusBadgeColor = 'bg-rose-100 text-rose-800 border-rose-300';
                            
                            if ($header->waiting_count > 0) {
                                $derivedStatus = 'WAITING';
                                $statusBadgeColor = 'bg-amber-100 text-amber-800 border-amber-300';
                            } elseif ($header->approved_count > 0) {
                                $derivedStatus = 'APPROVED';
                                $statusBadgeColor = 'bg-emerald-100 text-emerald-800 border-emerald-300';
                            }
                        @endphp
                        <tr class="hover:bg-slate-50/50 transition-colors {{ $isExpanded ? 'bg-slate-50/20' : '' }}">
                            <td class="py-3 px-4 text-center">
                                <button wire:click="toggleExpand({{ $header->id }})" class="p-1 rounded hover:bg-slate-200 transition-colors flex items-center justify-center mx-auto">
                                    <span class="material-symbols-outlined text-base font-black transition-transform {{ $isExpanded ? 'rotate-180' : '' }}">
                                        keyboard_arrow_down
                                    </span>
                                </button>
                            </td>
                            <td class="py-3 px-4 font-mono font-black text-slate-900 text-xs select-all">{{ $header->adjustment_no }}</td>
                            <td class="py-3 px-4 text-xs font-semibold text-slate-700 uppercase">{{ $header->warehouse->name }}</td>
                            <td class="py-3 px-4 text-xs font-bold text-slate-700">{{ $header->operator->name }}</td>
                            <td class="py-3 px-4 text-xs font-mono font-bold text-slate-650">{{ $header->date }}</td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 {{ $statusBadgeColor }} border text-[9px] font-black uppercase tracking-wider rounded font-mono">
                                    {{ $derivedStatus }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center text-xs font-mono font-bold text-slate-600">{{ $header->waiting_count }}</td>
                            <td class="py-3 px-4 text-center text-xs font-mono font-bold text-slate-600">{{ $header->approved_count }}</td>
                            <td class="py-3 px-4 text-center text-xs font-mono font-bold text-slate-600">{{ $header->rejected_count }}</td>
                            <td class="py-3 px-4 text-xs font-mono font-bold text-slate-500">
                                {{ \Carbon\Carbon::parse($header->created_at)->diffForHumans(null, true) }}
                            </td>
                        </tr>

                        <!-- NESTED DETAIL SECTION -->
                        @if($isExpanded)
                            <tr>
                                <td colspan="10" class="bg-slate-100/50 p-4 border-y border-slate-200">
                                    <div class="bg-white border border-slate-200 rounded-lg overflow-hidden shadow-inner animate-in slide-in-from-top-2 duration-200">
                                        <table class="w-full text-left border-collapse text-xs">
                                            <thead>
                                                <tr class="bg-slate-50 border-b border-slate-200 text-[9px] font-black uppercase tracking-widest text-slate-400 font-mono">
                                                    <th class="py-2.5 px-3">ERP Code</th>
                                                    <th class="py-2.5 px-3">Item Name</th>
                                                    <th class="py-2.5 px-3">Bin</th>
                                                    <th class="py-2.5 px-3 text-right">System Qty</th>
                                                    <th class="py-2.5 px-3 text-right">Physical Qty</th>
                                                    <th class="py-2.5 px-3 text-right">Variance</th>
                                                    <th class="py-2.5 px-3">Reason</th>
                                                    <th class="py-2.5 px-3">Notes</th>
                                                    <th class="py-2.5 px-3">Status</th>
                                                    <th class="py-2.5 px-3">Age</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100">
                                                @forelse($header->items as $item)
                                                    @php
                                                        // Variance Badge
                                                        $varText = ($item->variance > 0 ? '+' : '') . $item->variance;
                                                        $varColor = 'bg-slate-100 text-slate-800 border-slate-300';
                                                        if ($item->variance > 0) {
                                                            $varColor = 'bg-emerald-100 text-emerald-800 border-emerald-300';
                                                        } elseif ($item->variance < 0) {
                                                            $varColor = 'bg-rose-100 text-rose-800 border-rose-300';
                                                        }

                                                        // Reason Badge mapping
                                                        $reasonText = match($item->reason_code) {
                                                            'BARANG_DITEMUKAN', 'FOUND_ITEM' => 'FOUND_ITEM',
                                                            'SALAH_HITUNG', 'COUNTING_ERROR' => 'COUNTING_ERROR',
                                                            'PINDAH_RAK', 'SALAH_SCAN', 'WRONG_BIN' => 'WRONG_BIN',
                                                            'KERUSAKAN', 'DAMAGED_ITEM' => 'DAMAGED_ITEM',
                                                            'KESALAHAN_SISTEM', 'SYSTEM_ERROR' => 'SYSTEM_ERROR',
                                                            default => 'OTHER'
                                                        };
                                                        $reasonColor = match($reasonText) {
                                                            'FOUND_ITEM' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                                                            'COUNTING_ERROR' => 'bg-blue-100 text-blue-800 border-blue-300',
                                                            'WRONG_BIN' => 'bg-purple-100 text-purple-800 border-purple-300',
                                                            'DAMAGED_ITEM' => 'bg-orange-100 text-orange-850 border-orange-300',
                                                            'SYSTEM_ERROR' => 'bg-rose-100 text-rose-800 border-rose-300',
                                                            default => 'bg-slate-100 text-slate-800 border-slate-300'
                                                        };

                                                        // Age Badge mapping
                                                        $createdAt = \Carbon\Carbon::parse($item->created_at);
                                                        $diffMinutes = $createdAt->diffInMinutes(now());
                                                        $ageColor = 'bg-emerald-100 text-emerald-800 border-emerald-300';
                                                        if ($diffMinutes > 120) {
                                                            $ageColor = 'bg-rose-100 text-rose-800 border-rose-300';
                                                        } elseif ($diffMinutes > 30) {
                                                            $ageColor = 'bg-amber-100 text-amber-800 border-amber-300';
                                                        }
                                                    @endphp
                                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                                        <td class="py-2.5 px-3 font-mono font-black text-slate-900 select-all">{{ $item->erp_code_snapshot ?: '-' }}</td>
                                                        <td class="py-2.5 px-3 font-bold text-slate-700 select-all">{{ $item->item_name_snapshot }}</td>
                                                        <td class="py-2.5 px-3 font-mono font-bold text-slate-600 select-all">{{ $item->bin_code_snapshot }}</td>
                                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-650">{{ $item->system_qty }}</td>
                                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-650">{{ $item->physical_qty }}</td>
                                                        <td class="py-2.5 px-3 text-right">
                                                            <span class="inline-flex items-center px-1.5 py-0.5 {{ $varColor }} border text-[10px] font-black font-mono rounded">
                                                                {{ $varText }}
                                                            </span>
                                                        </td>
                                                        <td class="py-2.5 px-3">
                                                            <span class="inline-flex items-center px-1.5 py-0.5 {{ $reasonColor }} border text-[10px] font-black rounded font-mono">
                                                                {{ $reasonText }}
                                                            </span>
                                                        </td>
                                                        <td class="py-2.5 px-3 font-semibold text-slate-600 max-w-[200px] truncate select-all" title="{{ $item->notes }}">
                                                            {{ $item->notes ?: '-' }}
                                                        </td>
                                                        <td class="py-2.5 px-3">
                                                            @if($item->status === 'WAITING')
                                                                @if(auth()->user()->role === 'manager')
                                                                    <div class="flex flex-col gap-1">
                                                                        @if($confirmingRejectId === $item->id)
                                                                            <div class="flex items-center gap-1.5 animate-in slide-in-from-top-1 duration-150">
                                                                                <input type="text" 
                                                                                       wire:model.defer="rejectReasons.{{ $item->id }}" 
                                                                                       placeholder="Input reject reason..." 
                                                                                       class="h-7 bg-slate-50 border border-slate-200 rounded px-1.5 text-[10px] font-bold text-slate-800 focus:outline-none focus:border-red-650 w-[130px]">
                                                                                <button wire:click="confirmReject({{ $item->id }})" 
                                                                                        class="h-7 px-2 bg-rose-600 hover:bg-rose-700 text-white rounded text-[9px] font-black uppercase tracking-wider transition-all">
                                                                                    Confirm
                                                                                </button>
                                                                                <button wire:click="cancelReject" 
                                                                                        class="h-7 px-1.5 bg-slate-200 hover:bg-slate-350 text-slate-700 rounded text-[9px] font-black uppercase tracking-wider transition-all">
                                                                                    Cancel
                                                                                </button>
                                                                            </div>
                                                                        @else
                                                                            <div class="flex items-center gap-1.5">
                                                                                <button wire:click="approveItem({{ $item->id }})" 
                                                                                        class="h-7 px-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-[9px] font-black uppercase tracking-wider transition-all flex items-center gap-0.5 shadow-sm">
                                                                                    <span class="material-symbols-outlined text-[11px] font-bold">check_circle</span>
                                                                                    Approve
                                                                                </button>
                                                                                <button wire:click="startReject({{ $item->id }})" 
                                                                                        class="h-7 px-2 bg-rose-600 hover:bg-rose-700 text-white rounded text-[9px] font-black uppercase tracking-wider transition-all flex items-center gap-0.5 shadow-sm">
                                                                                    <span class="material-symbols-outlined text-[11px] font-bold">cancel</span>
                                                                                    Reject
                                                                                </button>
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                @else
                                                                    <span class="inline-flex items-center px-2 py-0.5 bg-amber-100 text-amber-800 border border-amber-300 text-[10px] font-black uppercase tracking-wider rounded font-mono">
                                                                        WAITING APPROVAL
                                                                    </span>
                                                                @endif
                                                            @elseif($item->status === 'APPROVED')
                                                                <span class="inline-flex items-center px-2 py-0.5 bg-emerald-100 text-emerald-800 border border-emerald-300 text-[10px] font-black uppercase tracking-wider rounded font-mono">
                                                                    APPROVED
                                                                </span>
                                                            @elseif($item->status === 'REJECTED')
                                                                <div class="flex flex-col">
                                                                    <span class="inline-flex items-center px-2 py-0.5 bg-rose-100 text-rose-800 border border-rose-300 text-[10px] font-black uppercase tracking-wider rounded font-mono w-fit">
                                                                        REJECTED
                                                                    </span>
                                                                    @if(!empty($item->reject_reason))
                                                                        <span class="text-[9px] font-bold text-rose-600 mt-1 select-all">Reason: {{ $item->reject_reason }}</span>
                                                                    @endif
                                                                </div>
                                                            @endif
                                                        </td>
                                                        <td class="py-2.5 px-3">
                                                            <span class="inline-flex items-center px-1.5 py-0.5 {{ $ageColor }} border text-[9px] font-black rounded font-mono whitespace-nowrap">
                                                                {{ $item->age }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="10" class="py-4 text-center font-bold text-slate-400">No items in this session.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="10" class="py-8 text-center bg-white">
                                <span class="material-symbols-outlined text-4xl text-slate-300 mb-2">find_in_page</span>
                                <h3 class="text-sm font-black text-slate-700 uppercase tracking-wider mb-1">No Adjustments Found</h3>
                                <p class="text-xs text-slate-500">No stock adjustment requests match your filter selection.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
