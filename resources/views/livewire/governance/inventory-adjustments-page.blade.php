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
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Card 1: Waiting Approval -->
        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex items-center justify-between transition-all hover:shadow-md">
            <div class="space-y-1">
                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block font-mono">Waiting Approval</span>
                <span class="text-2xl font-mono font-black text-slate-800 block">{{ $kpis['waiting'] }}</span>
            </div>
            <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center shadow-inner">
                <span class="material-symbols-outlined text-xl font-bold">pending_actions</span>
            </div>
        </div>

        <!-- Card 2: Approved Today -->
        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex items-center justify-between transition-all hover:shadow-md">
            <div class="space-y-1">
                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block font-mono">Approved Today</span>
                <span class="text-2xl font-mono font-black text-emerald-600 block">{{ $kpis['approved_today'] }}</span>
            </div>
            <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center shadow-inner">
                <span class="material-symbols-outlined text-xl font-bold">task_alt</span>
            </div>
        </div>

        <!-- Card 3: Rejected Today -->
        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex items-center justify-between transition-all hover:shadow-md">
            <div class="space-y-1">
                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block font-mono">Rejected Today</span>
                <span class="text-2xl font-mono font-black text-rose-600 block">{{ $kpis['rejected_today'] }}</span>
            </div>
            <div class="w-10 h-10 bg-rose-50 text-rose-600 rounded-lg flex items-center justify-center shadow-inner">
                <span class="material-symbols-outlined text-xl font-bold">cancel</span>
            </div>
        </div>

        <!-- Card 4: Avg Approval Time -->
        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex items-center justify-between transition-all hover:shadow-md">
            <div class="space-y-1">
                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block font-mono">Avg Approval Time</span>
                <span class="text-2xl font-mono font-black text-slate-500 block">{{ $kpis['avg_time'] }}</span>
            </div>
            <div class="w-10 h-10 bg-slate-50 text-slate-500 rounded-lg flex items-center justify-center shadow-inner">
                <span class="material-symbols-outlined text-xl font-bold">schedule</span>
            </div>
        </div>
    </div>

    <!-- STICKY FILTER BAR -->
    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm mb-6 sticky top-[44px] z-30">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
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
                <button wire:click="setDatePreset('today')" title="Filter Hari Ini" aria-label="Filter Hari Ini" class="px-2.5 py-1.5 bg-slate-150 hover:bg-slate-200 text-slate-700 rounded-md text-[10px] font-black uppercase tracking-wider transition-all font-mono font-bold">Today</button>
                <button wire:click="setDatePreset('yesterday')" title="Filter Kemarin" aria-label="Filter Kemarin" class="px-2.5 py-1.5 bg-slate-150 hover:bg-slate-200 text-slate-700 rounded-md text-[10px] font-black uppercase tracking-wider transition-all font-mono font-bold">Yesterday</button>
                <button wire:click="setDatePreset('this_week')" title="Filter Minggu Ini" aria-label="Filter Minggu Ini" class="px-2.5 py-1.5 bg-slate-150 hover:bg-slate-200 text-slate-700 rounded-md text-[10px] font-black uppercase tracking-wider transition-all font-mono font-bold">This Week</button>
                <button wire:click="setDatePreset('this_month')" title="Filter Bulan Ini" aria-label="Filter Bulan Ini" class="px-2.5 py-1.5 bg-slate-150 hover:bg-slate-200 text-slate-700 rounded-md text-[10px] font-black uppercase tracking-wider transition-all font-mono font-bold">This Month</button>
            </div>
        </div>
    </div>

    <!-- ADJUSTMENTS HEADER TABLE OR EMPTY STATE -->
    @if($headers->isEmpty())
        <div class="w-full bg-white border border-slate-200 rounded-xl p-12 text-center shadow-sm">
            <div class="w-16 h-16 bg-slate-50 border border-slate-150 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-400 shadow-inner">
                <span class="material-symbols-outlined text-3xl font-bold">task_alt</span>
            </div>
            <h3 class="text-sm font-black text-slate-800 uppercase tracking-wider mb-1">Tidak ada Inventory Adjustment</h3>
            <p class="text-xs text-slate-500 font-medium max-w-md mx-auto">Semua hasil opname hari ini sudah selesai atau tidak ada data yang cocok dengan filter aktif.</p>
        </div>
    @else
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-[9px] font-black uppercase tracking-widest text-slate-400 font-mono">
                            <th class="py-2 px-3 w-12 text-center">Detail</th>
                            <th class="py-2 px-3">Adjustment No</th>
                            <th class="py-2 px-3">Warehouse</th>
                            <th class="py-2 px-3">Operator</th>
                            <th class="py-2 px-3">Business Date</th>
                            <th class="py-2 px-3">Status</th>
                            <th class="py-2 px-3 text-center">Waiting</th>
                            <th class="py-2 px-3 text-center">Approved</th>
                            <th class="py-2 px-3 text-center">Rejected</th>
                            <th class="py-2 px-3">Age</th>
                            <th class="py-2 px-3">BASO Document</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-150">
                        @foreach($headers as $header)
                            @php
                                $isExpanded = in_array($header->id, $expandedHeaders);
                                
                                // Derived Status logic matching ERP Transfer & standardized colors
                                if ($header->waiting_count > 0) {
                                    $derivedStatus = 'WAITING APPROVAL';
                                    $statusBadgeColor = 'bg-slate-100 text-slate-650 border-slate-200';
                                    $bulletColor = 'bg-slate-400';
                                } elseif ($header->approved_count > 0) {
                                    $derivedStatus = 'APPROVED';
                                    $statusBadgeColor = 'bg-emerald-100 text-emerald-800 border-emerald-250';
                                    $bulletColor = 'bg-emerald-500';
                                } else {
                                    $derivedStatus = 'REJECTED';
                                    $statusBadgeColor = 'bg-rose-100 text-rose-800 border-rose-250';
                                    $bulletColor = 'bg-rose-500';
                                }
                            @endphp
                            <tr class="hover:bg-slate-50/50 transition-colors {{ $isExpanded ? 'bg-slate-50/20' : '' }}">
                                <td class="py-2 px-3 text-center">
                                    <button wire:click="toggleExpand({{ $header->id }})" 
                                            title="Toggle Detail Item"
                                            aria-label="Toggle Detail Item"
                                            class="p-1 rounded hover:bg-slate-200 transition-colors flex items-center justify-center mx-auto">
                                        <span class="material-symbols-outlined text-base font-black transition-transform {{ $isExpanded ? 'rotate-180' : '' }}">
                                            keyboard_arrow_down
                                        </span>
                                    </button>
                                </td>
                                <td class="py-2 px-3 font-mono font-black text-slate-900 text-xs select-all">{{ $header->adjustment_no }}</td>
                                <td class="py-2 px-3 text-xs font-semibold text-slate-700 uppercase">{{ $header->warehouse->name }}</td>
                                <td class="py-2 px-3 text-xs font-bold text-slate-700">{{ $header->operator->name }}</td>
                                <td class="py-2 px-3 text-xs font-mono font-bold text-slate-650">
                                    {{ \Carbon\Carbon::parse($header->date)->locale('id')->translatedFormat('d M Y') }}
                                </td>
                                <td class="py-2 px-3">
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 {{ $statusBadgeColor }} border text-[9px] font-black uppercase tracking-wider rounded font-mono">
                                        <span class="w-1.5 h-1.5 {{ $bulletColor }} rounded-full"></span>
                                        {{ $derivedStatus }}
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-center text-xs font-mono font-bold text-slate-600">{{ $header->waiting_count }}</td>
                                <td class="py-2 px-3 text-center text-xs font-mono font-bold text-slate-600">{{ $header->approved_count }}</td>
                                <td class="py-2 px-3 text-center text-xs font-mono font-bold text-slate-600">{{ $header->rejected_count }}</td>
                                <td class="py-2 px-3 text-xs font-mono font-bold text-slate-550">
                                    {{ $this->formatAge($header->created_at) }}
                                </td>
                                <td class="py-2 px-3">
                                    @php
                                        $baso = $basoMap[$header->id] ?? null;
                                    @endphp
                                    @if($header->status === 'COMPLETED')
                                        @if(!$baso)
                                            @if(auth()->user()->role === 'manager')
                                                <button wire:click="generateBaso({{ $header->id }})" 
                                                        wire:loading.attr="disabled"
                                                        title="Generate BASO"
                                                        aria-label="Generate BASO"
                                                        class="px-2.5 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-[10px] font-black uppercase tracking-wider transition-all shadow-sm flex items-center gap-1">
                                                    <span class="material-symbols-outlined text-[12px] font-bold">post_add</span>
                                                    Generate BASO
                                                </button>
                                            @else
                                                <button disabled 
                                                        title="Awaiting BASO"
                                                        aria-label="Awaiting BASO"
                                                        class="px-2.5 py-1 bg-slate-100 border border-slate-200 text-slate-400 rounded text-[10px] font-black uppercase tracking-wider flex items-center gap-1 cursor-not-allowed">
                                                    <span class="material-symbols-outlined text-[12px] font-bold">pending</span>
                                                    Awaiting BASO
                                                </button>
                                            @endif
                                        @else
                                            <a href="{{ route('governance.baso.view', $baso->id) }}" 
                                               target="_blank"
                                               title="View BASO"
                                               aria-label="View BASO"
                                               class="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-[10px] font-black uppercase tracking-wider transition-all shadow-sm inline-flex items-center gap-1 font-bold">
                                                <span class="material-symbols-outlined text-[12px] font-bold">visibility</span>
                                                View BASO
                                            </a>
                                        @endif
                                    @else
                                        <button disabled 
                                                title="Not Ready"
                                                aria-label="Not Ready"
                                                class="px-2.5 py-1 bg-slate-100 border border-slate-200 text-slate-400 rounded text-[10px] font-black uppercase tracking-wider flex items-center gap-1 cursor-not-allowed">
                                            <span class="material-symbols-outlined text-[12px] font-bold">lock</span>
                                            Not Ready
                                        </button>
                                    @endif
                                </td>
                            </tr>

                            <!-- NESTED DETAIL SECTION -->
                            @if($isExpanded)
                                <tr>
                                    <td colspan="11" class="bg-slate-100/50 p-3 border-y border-slate-250">
                                        <div class="bg-white border border-slate-200 rounded-lg overflow-hidden shadow-inner animate-in slide-in-from-top-2 duration-200">
                                            <table class="w-full text-left border-collapse text-xs">
                                                <thead>
                                                    <tr class="bg-slate-50 border-b border-slate-200 text-[9px] font-black uppercase tracking-widest text-slate-400 font-mono">
                                                        <th class="py-1.5 px-2.5">ERP Code</th>
                                                        <th class="py-1.5 px-2.5">Item Name</th>
                                                        <th class="py-1.5 px-2.5">Bin</th>
                                                        <th class="py-1.5 px-2.5 text-right">System Qty</th>
                                                        <th class="py-1.5 px-2.5 text-right">Physical Qty</th>
                                                        <th class="py-1.5 px-2.5 text-right">Variance</th>
                                                        <th class="py-1.5 px-2.5">Reason</th>
                                                        <th class="py-1.5 px-2.5">Notes</th>
                                                        <th class="py-1.5 px-2.5">Status</th>
                                                        <th class="py-1.5 px-2.5">Age</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100">
                                                    @forelse($header->items as $item)
                                                        @php
                                                            // Variance Badge formatting
                                                            $varText = ($item->variance > 0 ? '+' : '') . $item->variance;
                                                            $varColor = 'bg-slate-100 text-slate-800 border-slate-300';
                                                            if ($item->variance > 0) {
                                                                $varColor = 'bg-emerald-100 text-emerald-800 border-emerald-300';
                                                            } elseif ($item->variance < 0) {
                                                                $varColor = 'bg-rose-100 text-rose-800 border-rose-300';
                                                            }

                                                            // Mapping 15 standardized reasons to operator-friendly labels & groupings
                                                            $friendlyReason = match($item->reason_code) {
                                                                'COUNTING_ERROR' => 'Salah Hitung',
                                                                'WRONG_SCAN' => 'Salah Scan Barcode',
                                                                'WRONG_BIN' => 'Salah Penempatan Rak',
                                                                'WRONG_PICK' => 'Salah Ambil Barang',
                                                                'FOUND_ITEM' => 'Barang Ditemukan',
                                                                'RETURN_FOUND' => 'Barang Retur Ditemukan',
                                                                'LEFTOVER_FOUND' => 'Sisa Produksi Ditemukan',
                                                                'MISSING_ITEM' => 'Barang Tidak Ditemukan',
                                                                'DAMAGED_ITEM' => 'Barang Rusak',
                                                                'EXPIRED_ITEM' => 'Barang Kadaluarsa / Tidak Layak Pakai',
                                                                'MOVED_WITHOUT_SCAN' => 'Dipindahkan Tanpa Scan',
                                                                'TRANSFER_ERROR' => 'Salah Transfer Gudang',
                                                                'ERP_ERROR' => 'Kesalahan ERP',
                                                                'SYSTEM_ERROR' => 'Kesalahan Sistem WMS',
                                                                'LAINNYA' => 'Lainnya',
                                                                default => $item->reason_code ?: 'Lainnya'
                                                            };

                                                            $reasonColor = match($item->reason_code) {
                                                                'COUNTING_ERROR', 'WRONG_SCAN', 'WRONG_BIN', 'WRONG_PICK' => 'bg-blue-100 text-blue-800 border-blue-300/80',
                                                                'FOUND_ITEM', 'RETURN_FOUND', 'LEFTOVER_FOUND' => 'bg-emerald-100 text-emerald-800 border-emerald-300/80',
                                                                'MISSING_ITEM', 'DAMAGED_ITEM', 'EXPIRED_ITEM' => 'bg-orange-100 text-orange-850 border-orange-300/80',
                                                                'MOVED_WITHOUT_SCAN', 'TRANSFER_ERROR' => 'bg-purple-100 text-purple-800 border-purple-300/80',
                                                                'ERP_ERROR', 'SYSTEM_ERROR' => 'bg-rose-100 text-rose-800 border-rose-300/80',
                                                                default => 'bg-slate-100 text-slate-800 border-slate-300/80'
                                                            };

                                                            // Dynamic Age styling based on thresholds
                                                            $createdAt = \Carbon\Carbon::parse($item->created_at);
                                                            $diffMinutes = $createdAt->diffInMinutes(now());
                                                            $ageBadgeColor = 'bg-emerald-100 text-emerald-800 border-emerald-300';
                                                            if ($diffMinutes > 120) {
                                                                $ageBadgeColor = 'bg-rose-100 text-rose-800 border-rose-300';
                                                            } elseif ($diffMinutes > 30) {
                                                                $ageBadgeColor = 'bg-amber-100 text-amber-800 border-amber-300';
                                                            }
                                                        @endphp
                                                        <tr class="hover:bg-slate-50/50 transition-colors">
                                                            <td class="py-1.5 px-2.5 font-mono font-black text-slate-900 select-all">{{ $item->erp_code_snapshot ?: '-' }}</td>
                                                            <td class="py-1.5 px-2.5 font-bold text-slate-700 select-all">{{ $item->item_name_snapshot }}</td>
                                                            <td class="py-1.5 px-2.5 font-mono font-bold text-slate-600 select-all">{{ $item->bin_code_snapshot }}</td>
                                                            <td class="py-1.5 px-2.5 text-right font-mono font-bold text-slate-650">{{ $item->system_qty }}</td>
                                                            <td class="py-1.5 px-2.5 text-right font-mono font-bold text-slate-650">{{ $item->physical_qty }}</td>
                                                            <td class="py-1.5 px-2.5 text-right">
                                                                <span class="inline-flex items-center px-1.5 py-0.5 {{ $varColor }} border text-[10px] font-black font-mono rounded">
                                                                    {{ $varText }}
                                                                </span>
                                                            </td>
                                                            <td class="py-1.5 px-2.5">
                                                                <span class="inline-flex items-center px-1.5 py-0.5 {{ $reasonColor }} border text-[10px] font-bold rounded">
                                                                    {{ $friendlyReason }}
                                                                </span>
                                                            </td>
                                                            <td class="py-1.5 px-2.5 font-semibold text-slate-600 max-w-[200px] truncate select-all" title="{{ $item->notes }}">
                                                                @if(empty(trim($item->notes)))
                                                                    <span class="text-slate-400 font-normal italic">Tidak ada catatan</span>
                                                                @else
                                                                    {{ $item->notes }}
                                                                @endif
                                                            </td>
                                                            <td class="py-1.5 px-2.5">
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
                                                                                            wire:loading.attr="disabled"
                                                                                            wire:target="confirmReject({{ $item->id }})"
                                                                                            title="Konfirmasi Penolakan"
                                                                                            aria-label="Konfirmasi Penolakan"
                                                                                            class="h-7 px-2 bg-rose-600 hover:bg-rose-700 text-white rounded text-[9px] font-black uppercase tracking-wider transition-all disabled:opacity-50 flex items-center justify-center">
                                                                                        <span wire:loading.remove wire:target="confirmReject({{ $item->id }})">Confirm</span>
                                                                                        <span wire:loading wire:target="confirmReject({{ $item->id }})" class="animate-spin h-3.5 w-3.5 border-2 border-white border-t-transparent rounded-full"></span>
                                                                                    </button>
                                                                                    <button wire:click="cancelReject" 
                                                                                            title="Batal Menolak"
                                                                                            aria-label="Batal Menolak"
                                                                                            class="h-7 px-1.5 bg-slate-200 hover:bg-slate-350 text-slate-700 rounded text-[9px] font-black uppercase tracking-wider transition-all">
                                                                                        Cancel
                                                                                    </button>
                                                                                </div>
                                                                            @else
                                                                                <div class="flex items-center gap-1.5">
                                                                                    <button wire:click="approveItem({{ $item->id }})" 
                                                                                            wire:loading.attr="disabled"
                                                                                            wire:target="approveItem({{ $item->id }})"
                                                                                            title="Setujui Adjustment"
                                                                                            aria-label="Setujui Adjustment"
                                                                                            class="h-7 px-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-[9px] font-black uppercase tracking-wider transition-all flex items-center gap-0.5 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                                                                        <span wire:loading.remove wire:target="approveItem({{ $item->id }})" class="flex items-center gap-0.5">
                                                                                            <span class="material-symbols-outlined text-[11px] font-bold">check_circle</span>
                                                                                            Approve
                                                                                        </span>
                                                                                        <span wire:loading wire:target="approveItem({{ $item->id }})" class="flex items-center gap-1">
                                                                                            <svg class="animate-spin h-3 w-3 text-white" fill="none" viewBox="0 0 24 24">
                                                                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                                            </svg>
                                                                                            <span>Processing</span>
                                                                                        </span>
                                                                                    </button>
                                                                                    <button wire:click="startReject({{ $item->id }})" 
                                                                                            title="Tolak Adjustment"
                                                                                            aria-label="Tolak Adjustment"
                                                                                            class="h-7 px-2 bg-rose-600 hover:bg-rose-700 text-white rounded text-[9px] font-black uppercase tracking-wider transition-all flex items-center gap-0.5 shadow-sm">
                                                                                        <span class="material-symbols-outlined text-[11px] font-bold">cancel</span>
                                                                                        Reject
                                                                                    </button>
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    @else
                                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-amber-100 text-amber-800 border border-amber-300 text-[10px] font-black uppercase tracking-wider rounded font-mono">
                                                                            <span class="w-1.5 h-1.5 bg-amber-500 rounded-full"></span>
                                                                            WAITING
                                                                        </span>
                                                                    @endif
                                                                @elseif($item->status === 'APPROVED')
                                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-emerald-100 text-emerald-800 border border-emerald-300 text-[10px] font-black uppercase tracking-wider rounded font-mono">
                                                                        <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                                                                        APPROVED
                                                                    </span>
                                                                @elseif($item->status === 'REJECTED')
                                                                    <div class="flex flex-col gap-1">
                                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-rose-100 text-rose-800 border border-rose-300 text-[10px] font-black uppercase tracking-wider rounded font-mono w-fit">
                                                                            <span class="w-1.5 h-1.5 bg-rose-500 rounded-full"></span>
                                                                            REJECTED
                                                                        </span>
                                                                        @if(!empty($item->reject_reason))
                                                                            <div class="text-[10px] leading-tight text-rose-700 bg-rose-50 border border-rose-100 rounded px-1.5 py-0.5 mt-0.5 max-w-[180px]">
                                                                                <span class="font-bold block uppercase text-[8px] text-rose-500">Alasan:</span>
                                                                                <span class="font-medium select-all">{{ $item->reject_reason }}</span>
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                @endif
                                                            </td>
                                                            <td class="py-1.5 px-2.5">
                                                                <span class="inline-flex items-center px-1.5 py-0.5 {{ $ageBadgeColor }} border text-[9px] font-black rounded font-mono whitespace-nowrap">
                                                                    {{ $this->formatAge($item->created_at) }}
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
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
