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
                <p class="text-xs text-slate-500 font-medium">Verify physical audit coverage by Rack and Sub Rack locations using physical opname logs.</p>
            </div>
        </div>
    </div>

    <!-- STICKY FILTER BAR -->
    <div class="bg-white border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm mb-6 z-30">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
            
            <!-- Warehouse Selector -->
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Warehouse</label>
                <select wire:model.live="warehouseId" class="w-full bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md px-3 py-1.5 text-xs font-bold text-slate-800 focus:outline-none focus:border-green-600">
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Rack Autocomplete Input -->
            <div class="relative flex-1" x-data="{ open: @entangle('rackDropdownOpen') }">
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Rack (Location)</label>
                <div class="relative">
                    <input 
                        type="text" 
                        wire:model.live="rackSearch"
                        @focus="open = true"
                        @click.away="open = false"
                        placeholder="Search Rack Code..." 
                        class="w-full bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md px-3 py-1.5 pl-8 text-xs font-bold text-slate-800 focus:outline-none focus:border-green-600 transition-all placeholder:text-slate-400"
                    />
                    <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                    @if($selectedRackCode)
                        <button 
                            type="button" 
                            wire:click="resetRack" 
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
                    @if($rackOptions->isEmpty())
                        <div class="px-3 py-2 text-xs text-slate-400 italic">No racks found in this warehouse</div>
                    @else
                        @foreach($rackOptions as $loc)
                            <button 
                                type="button"
                                wire:click="selectRack({{ $loc->id }}, '{{ $loc->code }}')"
                                @click="open = false"
                                class="w-full text-left px-3 py-2 hover:bg-slate-50 transition-colors text-xs font-bold text-slate-700 flex justify-between items-center"
                            >
                                <span>{{ $loc->code }}</span>
                                @if($loc->description)
                                    <span class="text-[10px] text-slate-400 font-medium">{{ $loc->description }}</span>
                                @endif
                            </button>
                        @endforeach
                    @endif
                </div>
            </div>

            <!-- Sub Rack Autocomplete Input -->
            <div class="relative flex-1" x-data="{ open: @entangle('subRackDropdownOpen') }">
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Sub Rack (Bin Code)</label>
                <div class="relative">
                    <input 
                        type="text" 
                        wire:model.live="subRackSearch"
                        @focus="open = true"
                        @click.away="open = false"
                        placeholder="{{ $selectedRackId ? 'Search Sub Rack...' : 'Select Rack first' }}" 
                        {{ $selectedRackId ? '' : 'disabled' }}
                        class="w-full bg-slate-50 disabled:bg-slate-100 disabled:cursor-not-allowed border border-slate-200 dark:border-slate-800 rounded-md px-3 py-1.5 pl-8 text-xs font-bold text-slate-800 focus:outline-none focus:border-green-600 transition-all placeholder:text-slate-400"
                    />
                    <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                    @if($selectedSubRackCode)
                        <button 
                            type="button" 
                            wire:click="resetSubRack" 
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
                    @if($subRackOptions->isEmpty())
                        <div class="px-3 py-2 text-xs text-slate-400 italic">No sub-racks found</div>
                    @else
                        @foreach($subRackOptions as $code)
                            <button 
                                type="button"
                                wire:click="selectSubRack('{{ $code }}')"
                                @click="open = false"
                                class="w-full text-left px-3 py-2 hover:bg-slate-50 transition-colors text-xs font-bold text-slate-700"
                            >
                                {{ $code }}
                            </button>
                        @endforeach
                    @endif
                </div>
            </div>

        </div>
    </div>

    @if(!$selectedRackId)
        <!-- Welcome Prompt / Select Location State -->
        <div class="w-full bg-white border border-slate-200 dark:border-slate-800 rounded-xl p-12 text-center shadow-sm flex-1 flex flex-col items-center justify-center">
            <div class="w-16 h-16 bg-slate-50 dark:bg-slate-800 border border-slate-150 dark:border-slate-700 rounded-full flex items-center justify-center mb-4 text-slate-400 shadow-inner">
                <span class="material-symbols-outlined text-3xl font-bold">search_location</span>
            </div>
            <h3 class="text-sm font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider mb-1">Pilih Lokasi untuk Mulai Audit</h3>
            <p class="text-xs text-slate-550 max-w-[28rem] mx-auto font-medium">Gunakan filter Rack di atas untuk menampilkan status audit barang yang tersimpan di lokasi tersebut.</p>
        </div>
    @else

        <!-- SUMMARY CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <!-- Card 1: Total Items -->
            <div class="bg-white border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm flex items-center justify-between transition-all hover:shadow-md">
                <div class="space-y-1">
                    <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block font-mono">Total Items</span>
                    <span class="text-2xl font-mono font-black text-slate-850 block">{{ $summary['total'] }}</span>
                </div>
                <div class="w-10 h-10 bg-slate-50 text-slate-600 rounded-lg flex items-center justify-center shadow-inner">
                    <span class="material-symbols-outlined text-xl font-bold font-mono">inventory_2</span>
                </div>
            </div>

            <!-- Card 2: Audited -->
            <div class="bg-white border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm flex items-center justify-between transition-all hover:shadow-md">
                <div class="space-y-1">
                    <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block font-mono">Audited (Recent)</span>
                    <span class="text-2xl font-mono font-black text-emerald-650 block">{{ $summary['audited'] }}</span>
                </div>
                <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center shadow-inner">
                    <span class="material-symbols-outlined text-xl font-bold">check_circle</span>
                </div>
            </div>

            <!-- Card 3: Needs Audit -->
            <div class="bg-white border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm flex items-center justify-between transition-all hover:shadow-md">
                <div class="space-y-1">
                    <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block font-mono">Needs Audit</span>
                    <span class="text-2xl font-mono font-black text-rose-650 block">{{ $summary['needs_audit'] }}</span>
                </div>
                <div class="w-10 h-10 bg-rose-50 text-rose-600 rounded-lg flex items-center justify-center shadow-inner">
                    <span class="material-symbols-outlined text-xl font-bold">warning</span>
                </div>
            </div>

            <!-- Card 4: Coverage -->
            <div class="bg-white border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm flex items-center justify-between transition-all hover:shadow-md">
                <div class="space-y-1">
                    <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block font-mono">Coverage</span>
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-2xl font-mono font-black text-slate-800">{{ $summary['coverage'] }}%</span>
                        <span class="text-[10px] font-mono text-slate-400">({{ $summary['audited'] }} / {{ $summary['total'] }})</span>
                    </div>
                </div>
                <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center shadow-inner">
                    <span class="material-symbols-outlined text-xl font-bold">percent</span>
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
                    <div class="p-12 text-center text-slate-500 italic text-xs">
                        No items match the active quick filter inside this location.
                    </div>
                @else
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[9px] font-black uppercase tracking-widest text-slate-400 font-mono">
                                <th class="py-3 px-4">Sub Rack</th>
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
                                    <!-- Sub Rack Code -->
                                    <td class="py-3 px-4 font-mono font-black text-slate-900">{{ $item->bin_code }}</td>
                                    
                                    <!-- Item Code -->
                                    <td class="py-3 px-4 font-mono text-slate-800 font-bold">{{ $item->item_code }}</td>
                                    
                                    <!-- Item Name -->
                                    <td class="py-3 px-4 text-slate-850 uppercase leading-snug">{{ $item->item_name }}</td>
                                    
                                    <!-- Current Stock -->
                                    <td class="py-3 px-4 text-center font-mono font-bold">{{ $item->current_stock }}</td>
                                    
                                    <!-- Last Audit & Age -->
                                    <td class="py-3 px-4 leading-normal">
                                        <div class="font-mono font-bold text-slate-900">{{ $item->last_audit_date }}</div>
                                        <div class="text-[10px] text-slate-500 font-medium">({{ $item->age }})</div>
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
