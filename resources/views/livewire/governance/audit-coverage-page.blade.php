<div class="pt-24 px-6 pb-6 lg:px-8 min-h-screen flex flex-col bg-slate-50/30">
    
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-slate-900 rounded-lg flex items-center justify-center text-green-400 shadow-sm">
                <span class="material-symbols-outlined text-2xl">track_changes</span>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 bg-slate-200/50 px-2 py-0.5 rounded font-mono">GOVERNANCE</span>
                    <span class="text-[9px] font-black uppercase tracking-widest text-green-700 bg-green-100 px-2 py-0.5 rounded font-mono">Location Coverage</span>
                </div>
                <h1 class="text-3xl font-black tracking-tight text-slate-900 mt-1">Audit Coverage</h1>
                <p class="text-xs text-slate-550 font-medium">Verify physical audit coverage by Bin Location using physical opname logs.</p>
            </div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="bg-white border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm mb-6 z-30">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            
            <!-- Warehouse Selector -->
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-550 mb-1">Warehouse</label>
                <select wire:model.live="warehouseId" class="w-full bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md px-3 py-1.5 text-xs font-bold text-slate-800 focus:outline-none focus:border-green-600">
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Bin Location Autocomplete Input -->
            <div class="relative flex-1" x-data="{ open: @entangle('binDropdownOpen') }">
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-550 mb-1">Bin Location Code</label>
                <div class="relative">
                    <input 
                        type="text" 
                        wire:model.live="binSearch"
                        @focus="open = true"
                        @click.away="open = false"
                        placeholder="Search Bin Location..." 
                        class="w-full bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md px-3 py-1.5 pl-8 text-xs font-bold text-slate-800 focus:outline-none focus:border-green-600 transition-all placeholder:text-slate-400"
                    />
                    <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                    @if($selectedBinCode)
                        <button 
                            type="button" 
                            wire:click="resetBinCode" 
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-655 focus:outline-none flex items-center justify-center"
                        >
                            <span class="material-symbols-outlined text-xs">close</span>
                        </button>
                    @endif
                </div>

                <!-- Suggestions Dropdown -->
                <div 
                    x-show="open" 
                    class="absolute left-0 mt-1 w-full bg-white border border-slate-200 rounded-md shadow-lg z-50 max-h-48 overflow-y-auto"
                    style="display: none;"
                >
                    @if($binOptions->isEmpty())
                        <div class="px-3 py-2 text-xs text-slate-400 italic">No bins found in this warehouse</div>
                    @else
                        @foreach($binOptions as $code)
                            <button 
                                type="button"
                                wire:click="selectBinCode('{{ $code }}')"
                                @click="open = false"
                                class="w-full text-left px-3 py-2 hover:bg-slate-50 transition-colors text-xs font-bold text-slate-700"
                            >
                                {{ $code }}
                            </button>
                        @endforeach
                    @endif
                </div>
            </div>

            <!-- Action Buttons (Generate + Print) -->
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-550 mb-1">Actions</label>
                <div class="flex gap-2">
                    <button 
                        type="button" 
                        wire:click="generateCoverage" 
                        wire:loading.attr="disabled"
                        wire:target="generateCoverage"
                        @if(!$selectedBinCode) disabled @endif
                        class="flex-1 h-9 bg-green-600 disabled:bg-slate-200 disabled:text-slate-400 disabled:cursor-not-allowed text-white text-xs font-black uppercase tracking-widest rounded-md hover:bg-green-700 transition-colors shadow-md flex items-center justify-center gap-2"
                    >
                        <span wire:loading wire:target="generateCoverage" class="material-symbols-outlined text-sm animate-spin">progress_activity</span>
                        <span wire:loading.remove wire:target="generateCoverage" class="material-symbols-outlined text-sm">analytics</span>
                        <span>Generate</span>
                    </button>

                    <a 
                        @if($activeBinCode)
                            href="{{ route('governance.audit-coverage.pdf', ['warehouse_id' => $warehouseId, 'bin_code' => $activeBinCode, 'filter' => $quickFilter]) }}"
                            target="_blank"
                        @else
                            href="javascript:void(0)"
                            style="pointer-events: none;"
                        @endif
                        class="h-9 px-3 bg-slate-900 text-white rounded-md flex items-center justify-center hover:bg-slate-800 transition-colors shadow-md @if(!$activeBinCode) opacity-50 cursor-not-allowed @endif"
                        title="Print Report"
                    >
                        <span class="material-symbols-outlined text-sm">print</span>
                    </a>
                </div>
            </div>

        </div>
    </div>

    @if(!$hasGenerated)
        <!-- Welcome / Empty State (Before Generation) -->
        <div class="w-full bg-white border border-slate-200 dark:border-slate-800 rounded-xl p-12 text-center shadow-sm flex-1 flex flex-col items-center justify-center">
            <div class="w-16 h-16 bg-slate-50 dark:bg-slate-800 border border-slate-150 dark:border-slate-700 rounded-full flex items-center justify-center mb-4 text-slate-400 shadow-inner">
                <span class="material-symbols-outlined text-3xl font-bold">search_location</span>
            </div>
            <h3 class="text-sm font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider mb-1">Pilih Lokasi untuk Memulai Audit</h3>
            <p class="text-xs text-slate-550 max-w-[28rem] mx-auto font-medium">Pilih Bin Location Code di atas dan klik Generate untuk memulai audit.</p>
        </div>
    @else

        <!-- Currently Viewing Indicator -->
        <div class="mb-4 flex items-center gap-2 text-xs font-bold text-slate-600">
            <span class="material-symbols-outlined text-sm text-slate-400">visibility</span>
            <span>Currently Viewing Bin Location:</span>
            <span class="font-mono bg-slate-200/60 text-slate-800 px-2 py-0.5 rounded font-black text-[11px]">{{ $activeBinCode }}</span>
        </div>

        <!-- COMPACT HORIZONTAL STATISTICS BAR -->
        <div class="bg-white border border-slate-200 dark:border-slate-800 rounded-xl p-3 shadow-sm mb-6 flex flex-wrap items-center justify-between text-xs font-bold text-slate-700 divide-y sm:divide-y-0 sm:divide-x divide-slate-200/80">
            <!-- Total Items -->
            <div class="flex items-center gap-2 px-4 first:pl-0 flex-1 min-w-[110px] justify-center sm:justify-start py-2 sm:py-0">
                <span class="material-symbols-outlined text-slate-400 text-base">inventory_2</span>
                <span class="text-slate-450 uppercase text-[9px] tracking-wider font-mono">Total:</span>
                <span class="text-sm font-mono font-black text-slate-850">{{ $summary['total'] }}</span>
            </div>

            <!-- Audited -->
            <div class="flex items-center gap-2 px-4 flex-1 min-w-[110px] justify-center sm:justify-start py-2 sm:py-0">
                <span class="material-symbols-outlined text-emerald-500 text-base">check_circle</span>
                <span class="text-slate-450 uppercase text-[9px] tracking-wider font-mono">Audited:</span>
                <span class="text-sm font-mono font-black text-emerald-650">{{ $summary['audited'] }}</span>
            </div>

            <!-- Aging -->
            <div class="flex items-center gap-2 px-4 flex-1 min-w-[110px] justify-center sm:justify-start py-2 sm:py-0">
                <span class="material-symbols-outlined text-amber-500 text-base">pending_actions</span>
                <span class="text-slate-450 uppercase text-[9px] tracking-wider font-mono">Aging:</span>
                <span class="text-sm font-mono font-black text-amber-605">{{ $summary['aging'] }}</span>
            </div>

            <!-- Needs Audit -->
            <div class="flex items-center gap-2 px-4 flex-1 min-w-[115px] justify-center sm:justify-start py-2 sm:py-0">
                <span class="material-symbols-outlined text-rose-550 text-base">warning</span>
                <span class="text-slate-450 uppercase text-[9px] tracking-wider font-mono font-bold">Needs Audit:</span>
                <span class="text-sm font-mono font-black text-rose-655">{{ $summary['needs_audit'] }}</span>
            </div>

            <!-- Coverage -->
            <div class="flex items-center gap-2 px-4 last:pr-0 flex-1 min-w-[140px] justify-center sm:justify-start py-2 sm:py-0">
                <span class="material-symbols-outlined text-blue-500 text-base">percent</span>
                <span class="text-slate-450 uppercase text-[9px] tracking-wider font-mono">Coverage:</span>
                <div class="flex items-baseline gap-1">
                    <span class="text-sm font-mono font-black text-slate-800">{{ $summary['coverage'] }}%</span>
                    <span class="text-[9px] font-mono text-slate-400">({{ $summary['audited'] }}/{{ $summary['total'] }})</span>
                </div>
            </div>
        </div>

        <!-- QUICK FILTERS -->
        <div class="flex flex-wrap gap-2 mb-4 bg-white border border-slate-200 dark:border-slate-800 p-2 rounded-xl shadow-sm items-center">
            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 px-2 font-mono">Filter Status:</span>
            <button 
                wire:click="setQuickFilter('all')" 
                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $quickFilter === 'all' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-655 hover:bg-slate-50' }}"
            >
                All
            </button>
            <button 
                wire:click="setQuickFilter('audited')" 
                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 {{ $quickFilter === 'audited' ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-655 hover:bg-slate-50' }}"
            >
                <span class="w-2 h-2 rounded-full {{ $quickFilter === 'audited' ? 'bg-white' : 'bg-emerald-500' }}"></span>
                Audited
            </button>
            <button 
                wire:click="setQuickFilter('stale')" 
                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 {{ $quickFilter === 'stale' ? 'bg-amber-500 text-white shadow-sm' : 'text-slate-655 hover:bg-slate-50' }}"
            >
                <span class="w-2 h-2 rounded-full {{ $quickFilter === 'stale' ? 'bg-white' : 'bg-amber-500' }}"></span>
                Stale
            </button>
            <button 
                wire:click="setQuickFilter('needs_audit')" 
                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 {{ $quickFilter === 'needs_audit' ? 'bg-rose-600 text-white shadow-sm' : 'text-slate-655 hover:bg-slate-50' }}"
            >
                <span class="w-2 h-2 rounded-full {{ $quickFilter === 'needs_audit' ? 'bg-white' : 'bg-rose-500' }}"></span>
                Needs Audit
            </button>
        </div>

        <!-- MAIN TABLE CONTAINER -->
        <div class="bg-white border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm overflow-hidden flex-1">
            <div class="overflow-x-auto">
                @if($items->isEmpty())
                    <div class="p-12 text-center text-slate-550 italic text-xs">
                        No items match the active quick filter inside this location.
                    </div>
                @else
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[9px] font-black uppercase tracking-widest text-slate-400 font-mono">
                                <th class="py-3 px-4">Bin Location</th>
                                <th class="py-3 px-4">Item Code</th>
                                <th class="py-3 px-4 w-[25%]">Item Name</th>
                                <th class="py-3 px-4 text-center">Current Stock</th>
                                <th class="py-3 px-4">Last Audit & Age</th>
                                <th class="py-3 px-4">Last Auditor</th>
                                <th class="py-3 px-4 w-[20%]">Audit Note</th>
                                <th class="py-3 px-4">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-150 text-xs font-medium text-slate-700">
                            @foreach($items as $item)
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <!-- Bin Location Code -->
                                    <td class="py-3 px-4 font-mono font-black text-slate-900">{{ $item->bin_code }}</td>
                                    
                                    <!-- Item Code -->
                                    <td class="py-3 px-4 font-mono text-slate-800 font-bold">{{ $item->item_code }}</td>
                                    
                                    <!-- Item Name -->
                                    <td class="py-3 px-4 text-slate-855 uppercase leading-snug">{{ $item->item_name }}</td>
                                    
                                    <!-- Current Stock -->
                                    <td class="py-3 px-4 text-center font-mono font-bold">{{ $item->current_stock }}</td>
                                    
                                    <!-- Last Audit & Age & Next Due -->
                                    <td class="py-3 px-4 leading-normal">
                                        <div class="font-mono font-bold text-slate-900">{{ $item->last_audit_date }}</div>
                                        <div class="text-[10px] text-slate-500 font-medium">({{ $item->age }})</div>
                                        @if($item->last_audit_date !== 'NEVER')
                                            @if($item->is_overdue)
                                                <div class="text-[10px] text-rose-600 font-bold mt-0.5">Overdue {{ $item->overdue_days }} days</div>
                                            @else
                                                <div class="text-[10px] text-slate-500 font-medium mt-0.5">Next Due {{ $item->next_due_date }}</div>
                                            @endif
                                        @endif
                                    </td>
                                    
                                    <!-- Last Auditor -->
                                    <td class="py-3 px-4 font-bold text-slate-700">{{ $item->last_auditor }}</td>
                                    
                                    <!-- Audit Note -->
                                    <td class="py-3 px-4 text-slate-500 leading-snug italic font-medium">{{ $item->audit_note }}</td>
                                    
                                    <!-- Status Badge -->
                                    <td class="py-3 px-4">
                                        @php
                                            $badgeClass = match($item->status) {
                                                'green' => 'bg-emerald-100 text-emerald-800 border-emerald-250',
                                                'yellow' => 'bg-amber-100 text-amber-800 border-amber-250',
                                                'red' => 'bg-rose-100 text-rose-800 border-rose-250',
                                                default => 'bg-slate-100 text-slate-800 border-slate-200'
                                            };
                                            $bulletClass = match($item->status) {
                                                'green' => 'bg-emerald-500',
                                                'yellow' => 'bg-amber-500',
                                                'red' => 'bg-rose-500',
                                                default => 'bg-slate-550'
                                            };
                                        @endphp
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 {{ $badgeClass }} border text-[9px] font-black uppercase tracking-wider rounded font-mono">
                                            <span class="w-1.5 h-1.5 {{ $bulletClass }} rounded-full"></span>
                                            {{ $item->status_label }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @endif
</div>
