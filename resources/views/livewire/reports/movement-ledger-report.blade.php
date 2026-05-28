<div class="p-6 bg-slate-50 min-h-screen" x-data="{ searchOpen: true }">
    
    <!-- Page Header -->
    <div class="flex items-center justify-between mb-5">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-black uppercase tracking-widest text-slate-400 bg-slate-200/50 px-2 py-0.5 rounded font-mono">Report Engine</span>
                <span class="text-xs font-black uppercase tracking-widest text-green-700 bg-green-100 px-2 py-0.5 rounded font-mono">Operational Ledger</span>
            </div>
            <h1 class="text-xl font-black text-slate-900 uppercase tracking-tight mt-1">Kartu Stok / Movement Ledger</h1>
            <p class="text-xs text-slate-500 font-medium">Trace inventory balances chronologically, track PIC / Departments, and verify warehouse transaction sequences.</p>
        </div>
        <div>
            <a href="{{ route('reports.stock-out') }}" class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-200 hover:bg-slate-300/80 text-slate-700 rounded-lg text-[11px] font-black uppercase tracking-wider transition-all">
                <span class="material-symbols-outlined text-base">arrow_back</span>
                Reports Hub
            </a>
        </div>
    </div>

    <!-- Alert Notifications -->
    @if(session('warning'))
        <div class="mb-4 bg-amber-50 border-l-4 border-amber-500 p-3.5 rounded shadow-sm flex items-start gap-2.5">
            <span class="material-symbols-outlined text-amber-600 text-lg">warning</span>
            <div class="text-[11.5px] text-amber-800 font-bold leading-relaxed">
                {{ session('warning') }}
            </div>
        </div>
    @endif

    <!-- FILTER BOARD -->
    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm mb-5">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
            
            <!-- Item Search Autocomplete -->
            <div class="relative">
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Cari Barang (ERP Code / Name)</label>
                <div class="flex items-center gap-1.5">
                    @if($selectedVariant)
                        <div class="flex items-center justify-between w-full bg-slate-100 border border-slate-300 rounded-lg px-2.5 py-1.5">
                            <span class="text-xs font-mono font-bold text-slate-800 truncate" title="{{ $selectedVariant->erp_code }} - {{ $selectedVariant->item->name }}">
                                {{ $selectedVariant->erp_code }} ({{ $selectedVariant->item->name }})
                            </span>
                            <button type="button" wire:click="resetItem" class="text-rose-600 hover:text-rose-800 flex items-center font-bold">
                                <span class="material-symbols-outlined text-base">close</span>
                            </button>
                        </div>
                    @else
                        <div class="relative w-full">
                            <input type="text" 
                                   wire:model.live.debounce.300ms="searchItem" 
                                   @focus="searchOpen = true"
                                   placeholder="Type e.g. 5.01 or hub..." 
                                   class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-mono font-bold text-slate-800 focus:outline-none focus:border-green-600">
                            
                            <!-- Autocomplete Dropdown overlay -->
                            @if(!empty($suggestions))
                                <div class="absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-lg shadow-lg z-50 overflow-hidden max-h-60 overflow-y-auto" x-show="searchOpen">
                                    <div class="bg-slate-50 px-3 py-1.5 border-b border-slate-100">
                                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Pilih Barang (Hasil Pencarian)</span>
                                    </div>
                                    @foreach($suggestions as $sug)
                                        <button type="button" 
                                                wire:click="selectItem({{ $sug->id }})" 
                                                @click="searchOpen = false"
                                                class="w-full text-left px-3 py-2 hover:bg-slate-50 border-b border-slate-50 transition-colors flex items-center justify-between gap-2">
                                            <div class="truncate">
                                                <p class="text-xs font-mono font-black text-slate-800 leading-none">{{ $sug->erp_code }}</p>
                                                <p class="text-[10px] text-slate-500 font-bold truncate mt-0.5">{{ $sug->item->name }}</p>
                                            </div>
                                            <span class="text-[9px] font-black uppercase bg-slate-100 border border-slate-200 px-1.5 py-0.5 rounded text-slate-500 font-mono">{{ $sug->unit }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <!-- Date Range Filters -->
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Tanggal Mulai</label>
                <input type="date" wire:model.live="startDate" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-mono font-bold text-slate-800 focus:outline-none focus:border-green-600">
            </div>
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Tanggal Akhir</label>
                <input type="date" wire:model.live="endDate" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-mono font-bold text-slate-800 focus:outline-none focus:border-green-600">
            </div>

            <!-- Movement Type Filter -->
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Tipe Transaksi</label>
                <select wire:model.live="movementType" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-bold text-slate-800 focus:outline-none focus:border-green-600">
                    <option value="ALL">SEMUA TRANSAKSI</option>
                    <option value="IN">IN (STOCK MASUK)</option>
                    <option value="OUT">OUT (STOCK KELUAR)</option>
                    <option value="ADJUSTMENT">ADJ (PENYESUAIAN/REVERSAL)</option>
                </select>
            </div>
        </div>

        <!-- Preset Filters & Report Action Trigger -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mt-4 pt-4 border-t border-slate-100 gap-3">
            <div class="flex items-center gap-1.5 flex-wrap">
                <button wire:click="setDatePreset('today')" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200/80 text-slate-700 rounded-md text-[10px] font-black uppercase tracking-wider transition-all">Hari Ini</button>
                <button wire:click="setDatePreset('this_week')" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200/80 text-slate-700 rounded-md text-[10px] font-black uppercase tracking-wider transition-all">Minggu Ini</button>
                <button wire:click="setDatePreset('this_month')" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200/80 text-slate-700 rounded-md text-[10px] font-black uppercase tracking-wider transition-all">Bulan Ini</button>
                <button wire:click="setDatePreset('this_year')" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200/80 text-slate-700 rounded-md text-[10px] font-black uppercase tracking-wider transition-all">Tahun Ini</button>
            </div>
            
            <button type="button" 
                    wire:click="generateReport" 
                    class="px-5 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-xs font-black uppercase tracking-wider transition-all flex items-center gap-1.5 active:scale-95 shadow-md shadow-green-600/10">
                <span class="material-symbols-outlined text-base">query_stats</span>
                Generate Kartu Stok
            </button>
        </div>
    </div>

    <!-- LEDGER REPORT PREVIEW -->
    @if($reportGenerated && $selectedVariant)
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            
            <!-- Report Meta Info Banner -->
            <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="truncate">
                    <span class="text-[9px] font-black uppercase bg-green-100 text-green-700 border border-green-200 px-2 py-0.5 rounded-full font-mono font-bold select-none">Active Ledger</span>
                    <h2 class="text-sm font-black text-slate-800 mt-1 truncate font-mono">
                        {{ $selectedVariant->erp_code }} &mdash; {{ $selectedVariant->item->name }}
                    </h2>
                    <p class="text-[10px] text-slate-500 font-bold mt-0.5">
                        Range: {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }} | Tipe: {{ $movementType }}
                    </p>
                </div>
                
                <!-- Action Controls (Excel & Print) -->
                <div class="flex items-center gap-2 flex-shrink-0">
                    <!-- CSV / Excel Export -->
                    <a href="{{ route('reports.movement-ledger.csv', ['selectedVariantId' => $selectedVariantId, 'startDate' => $startDate, 'endDate' => $endDate, 'movementType' => $movementType]) }}" 
                       class="flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-black uppercase tracking-wider rounded-lg transition-colors active:scale-95 shadow-sm">
                        <span class="material-symbols-outlined text-base">download</span>
                        Export Excel
                    </a>

                    <!-- Compact Printable PDF (Enabled only if total movements <= 200) -->
                    @if($totalRows <= 200)
                        <a href="{{ route('reports.movement-ledger.print', ['selectedVariantId' => $selectedVariantId, 'startDate' => $startDate, 'endDate' => $endDate, 'movementType' => $movementType]) }}" 
                           target="_blank"
                           class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-900 text-white text-[11px] font-black uppercase tracking-wider rounded-lg transition-colors active:scale-95 shadow-sm">
                            <span class="material-symbols-outlined text-base">print</span>
                            Print PDF
                        </a>
                    @else
                        <button type="button" 
                                onclick="alert('Histori melebihi 200 baris (Total: {{ $totalRows }}). Silakan gunakan tombol Export Excel demi efisiensi render browser.')"
                                class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-200 text-slate-450 text-[11px] font-black uppercase tracking-wider rounded-lg cursor-not-allowed select-none"
                                title="Data melebihi 200 row. Gunakan export Excel.">
                            <span class="material-symbols-outlined text-base text-slate-400">print_disabled</span>
                            Print PDF
                        </button>
                    @endif
                </div>
            </div>

            <!-- Ledger Preview Data Grid -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-100/70 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-wider select-none">
                            <th class="py-2.5 px-4" style="width: 100px;">Date</th>
                            <th class="py-2.5 px-4" style="width: 140px;">Reference</th>
                            <th class="py-2.5 px-4">Department / PIC / Supplier</th>
                            <th class="py-2.5 px-4 text-center" style="width: 80px;">Type</th>
                            <th class="py-2.5 px-4 text-right" style="width: 80px;">Qty In</th>
                            <th class="py-2.5 px-4 text-right" style="width: 80px;">Qty Out</th>
                            <th class="py-2.5 px-4 text-right" style="width: 100px;">Balance</th>
                            <th class="py-2.5 px-4 text-center" style="width: 90px;">Location</th>
                            <th class="py-2.5 px-4 text-right" style="width: 90px;">Operator</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-[11px] font-medium text-slate-700">
                        
                        <!-- Initial Starting Balance Row -->
                        <tr class="bg-slate-50/50 font-mono text-slate-500">
                            <td class="py-2 px-4 select-none">-</td>
                            <td class="py-2 px-4 select-none font-bold uppercase tracking-wider">INIT_BALANCE</td>
                            <td class="py-2 px-4 italic font-sans font-bold">Saldo awal sebelum rentang tanggal terpilih</td>
                            <td class="py-2 px-4 text-center select-none">-</td>
                            <td class="py-2 px-4 text-right select-none">-</td>
                            <td class="py-2 px-4 text-right select-none">-</td>
                            <td class="py-2 px-4 text-right font-black text-slate-800 text-[11.5px] bg-slate-100/40">
                                {{ number_format($startingBalance) }}
                            </td>
                            <td class="py-2 px-4 text-center select-none">-</td>
                            <td class="py-2 px-4 text-right select-none uppercase">SYSTEM</td>
                        </tr>

                        <!-- Paginated Movements Stream -->
                        @forelse($movements as $mov)
                            @php
                                $picDept = 'N/A';
                                if ($mov->type === 'IN') {
                                    if ($mov->receipt) {
                                        $picDept = ($mov->receipt->supplier ? $mov->receipt->supplier->name : 'BPB Inbound') . ' / ' . ($mov->receipt->operator ? $mov->receipt->operator->name : 'Operator');
                                    } elseif ($mov->supplier) {
                                        $picDept = $mov->supplier->name . ' / Inbound';
                                    } else {
                                        $picDept = 'General Stock In';
                                    }
                                } elseif ($mov->type === 'OUT') {
                                    if ($mov->transaction) {
                                        $picDept = ($mov->transaction->department ? $mov->transaction->department->name : 'General') . ' / ' . ($mov->transaction->user ? $mov->transaction->user->name : 'PIC');
                                    } else {
                                        $picDept = 'Direct Checkout';
                                    }
                                } elseif ($mov->type === 'ADJUSTMENT' || $mov->type === 'REVERSAL') {
                                    $picDept = 'Opname Adjustment / Reversal';
                                }
                            @endphp
                            <tr class="hover:bg-slate-50 transition-colors font-mono leading-none">
                                <td class="py-2 px-4 text-slate-400 select-none">
                                    {{ $mov->created_at->format('d M H:i') }}
                                </td>
                                <td class="py-2 px-4 text-slate-800 font-bold tracking-tight">
                                    {{ $mov->reference ?: 'SYSTEM_GEN' }}
                                </td>
                                <td class="py-2 px-4 text-slate-600 font-sans font-bold uppercase truncate max-w-[280px]" title="{{ $picDept }}">
                                    {{ $picDept }}
                                </td>
                                <td class="py-2 px-4 text-center">
                                    @if($mov->type === 'IN')
                                        <span class="inline-block px-1.5 py-0.5 bg-emerald-50 text-emerald-700 font-bold rounded border border-emerald-100 text-[8.5px] uppercase tracking-wide">IN</span>
                                    @elseif($mov->type === 'OUT')
                                        <span class="inline-block px-1.5 py-0.5 bg-rose-50 text-rose-700 font-bold rounded border border-rose-100 text-[8.5px] uppercase tracking-wide">OUT</span>
                                    @elseif($mov->type === 'ADJUSTMENT')
                                        <span class="inline-block px-1.5 py-0.5 bg-purple-50 text-purple-700 font-bold rounded border border-purple-100 text-[8.5px] uppercase tracking-wide">ADJ</span>
                                    @elseif($mov->type === 'REVERSAL')
                                        <span class="inline-block px-1.5 py-0.5 bg-slate-100 text-slate-600 font-bold rounded border border-slate-200 text-[8.5px] uppercase tracking-wide">REV</span>
                                    @else
                                        <span class="inline-block px-1.5 py-0.5 bg-blue-50 text-blue-700 font-bold rounded border border-blue-100 text-[8.5px] uppercase tracking-wide">{{ $mov->type }}</span>
                                    @endif
                                </td>
                                
                                <!-- Quantity In -->
                                <td class="py-2 px-4 text-right font-black text-emerald-600 text-[11px]">
                                    @if($mov->qty > 0)
                                        +{{ number_format($mov->qty) }}
                                    @else
                                        -
                                    @endif
                                </td>

                                <!-- Quantity Out -->
                                <td class="py-2 px-4 text-right font-black text-rose-600 text-[11px]">
                                    @if($mov->qty < 0)
                                        {{ number_format(abs($mov->qty)) }}
                                    @else
                                        -
                                    @endif
                                </td>

                                <!-- Running Balance -->
                                <td class="py-2 px-4 text-right font-black text-slate-800 text-[11.5px] bg-slate-50">
                                    {{ number_format($mov->running_balance) }}
                                </td>

                                <!-- Bin / Location -->
                                <td class="py-2 px-4 text-center font-bold text-slate-500 uppercase select-none">
                                    [{{ $mov->bin ? $mov->bin->code : 'UNASSIGND' }}]
                                </td>

                                <!-- Operator -->
                                <td class="py-2 px-4 text-right text-slate-400 font-bold truncate max-w-[90px] uppercase" title="{{ $mov->operator ? $mov->operator->name : 'System' }}">
                                    {{ substr($mov->operator ? $mov->operator->name : ($mov->created_by ?: 'System'), 0, 8) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-12 border border-dashed border-slate-200 rounded-lg bg-slate-50/50">
                                    <span class="material-symbols-outlined text-3xl text-slate-350 mb-1">history_toggle_off</span>
                                    <h4 class="text-slate-700 text-[11px] font-black uppercase tracking-wider mb-0.5">Tidak Ada Transaksi Ditemukan</h4>
                                    <p class="text-[9.5px] text-slate-400 max-w-[420px] mx-auto leading-relaxed font-bold">Barang terpilih tidak memiliki catatan log histori transaksi pada interval tanggal yang Anda tentukan.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            @if($movements->isNotEmpty() && method_exists($movements, 'links'))
                <div class="px-4 py-3 bg-slate-50 border-t border-slate-200">
                    {{ $movements->links() }}
                </div>
            @endif
        </div>
    @elseif($reportGenerated)
        <!-- General Selection Reminder -->
        <div class="text-center py-12 bg-white border border-slate-200 rounded-xl shadow-sm">
            <span class="material-symbols-outlined text-4xl text-slate-300 mb-2">find_in_page</span>
            <h3 class="text-slate-800 text-sm font-black uppercase tracking-wider mb-1">Laporan Belum Dihasilkan</h3>
            <p class="text-xs text-slate-400 max-w-md mx-auto leading-relaxed">Silakan cari dan pilih salah satu barang serta tentukan rentang tanggal sebelum mengklik tombol **Generate Kartu Stok**.</p>
        </div>
    @else
        <!-- Welcome Screen Information Feed -->
        <div class="text-center py-16 bg-white border border-slate-200 rounded-xl shadow-sm">
            <span class="material-symbols-outlined text-5xl text-slate-300 mb-3">timeline</span>
            <h3 class="text-slate-800 text-sm font-black uppercase tracking-wider mb-1">Pencarian Kartu Stok / Ledger</h3>
            <p class="text-xs text-slate-400 max-w-md mx-auto leading-relaxed">System Kartu Stok melacak secara mutlak histori kuantitas saldo berjalan setiap barang. Masukkan ERP Code barang pada input di atas untuk memulai audit audit trail barang.</p>
        </div>
    @endif

</div>
