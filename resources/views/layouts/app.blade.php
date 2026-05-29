<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>WMS Orchestrator</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @keyframes flash-success {
            0% { box-shadow: 0 0 0 0px rgba(16, 185, 129, 0.4); border-color: rgba(16, 185, 129, 0.5); }
            50% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); border-color: rgba(16, 185, 129, 1); }
            100% { box-shadow: 0 0 0 0px rgba(16, 185, 129, 0); border-color: transparent; }
        }
        .success-flash {
            animation: flash-success 2s cubic-bezier(0.4, 0, 0.2, 1) infinite;
            border: 2px solid transparent;
        }
    </style>
    <style>
        .industrial-shadow {
            box-shadow: 0px 24px 48px rgba(25, 28, 30, 0.06);
        }
        .green-action-gradient {
            background: linear-gradient(135deg, #16a34a 0%, #22c55e 100%);
        }
        .scanning-active {
            box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.2);
            border: 2px solid #22c55e !important;
        }
        
        /* Modern Minimalist Ultra-Thin Scrollbar */
        .custom-sidebar-scroll {
            scrollbar-gutter: stable;
            overflow-x: hidden !important;
        }
        .custom-sidebar-scroll::-webkit-scrollbar {
            width: 3px;
        }
        .custom-sidebar-scroll::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-sidebar-scroll::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.2);
            border-radius: 9999px;
            transition: background 0.2s ease;
        }
        .custom-sidebar-scroll::-webkit-scrollbar-thumb:hover {
            background: rgba(148, 163, 184, 0.5);
        }
        /* Firefox Support */
        .custom-sidebar-scroll {
            scrollbar-width: thin;
            scrollbar-color: rgba(148, 163, 184, 0.2) transparent;
        }
    </style>
</head>
<body class="bg-surface text-on-surface flex min-h-screen antialiased">
    <!-- SideNavBar (Desktop Shell) -->
    <aside class="fixed left-0 top-0 h-full p-2 h-screen w-[84px] hidden lg:flex flex-col border-r border-slate-200 dark:border-slate-800 bg-slate-100/85 dark:bg-slate-900/85 backdrop-blur-md z-40">
        <!-- Sticky Top: Branding -->
        <div class="mb-4 text-center flex-shrink-0">
            <div class="w-11 h-11 mx-auto rounded-lg bg-green-600 text-white flex items-center justify-center shadow-md shadow-green-600/20">
                <span class="material-symbols-outlined text-xl">warehouse</span>
            </div>
            <h1 class="text-[10px] font-black tracking-tight text-slate-900 dark:text-white uppercase leading-none mt-1.5">Terminal 01</h1>
            <p class="text-[8px] text-slate-500 font-bold uppercase tracking-widest leading-none mt-0.5">Dock A</p>
        </div>

        <!-- Scrollable Middle: Unified Grouped Navigation -->
        <div class="flex-1 overflow-y-auto space-y-4 pb-6 pr-1 custom-sidebar-scroll">
            
            <!-- General Link -->
            <a class="relative group/nav flex items-center justify-center w-12 h-12 mx-auto {{ request()->routeIs('dashboard') ? 'bg-slate-200/90 dark:bg-slate-800/90 text-green-700 dark:text-green-300 border-l-[5px] border-green-600 shadow-sm font-bold' : 'text-slate-655 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800/60' }} rounded-xl transition-all duration-200" href="{{ route('dashboard') }}">
                <span class="material-symbols-outlined text-2xl">dashboard</span>
                <span class="absolute left-16 bg-slate-900 dark:bg-slate-800 text-white text-xs font-bold px-2 py-1 rounded shadow-lg opacity-0 translate-x-[-10px] group-hover/nav:opacity-100 group-hover/nav:translate-x-0 transition-all pointer-events-none whitespace-nowrap z-50">
                    Dashboard
                </span>
            </a>

            <!-- Group: Inventory -->
            <div>
                <div class="text-[8px] font-black text-slate-400 dark:text-slate-500 uppercase text-center select-none py-1 border-t border-slate-200/50 dark:border-slate-850 mt-2 mb-1">INV</div>
                <div class="space-y-2">
                    <a class="relative group/nav flex items-center justify-center w-12 h-12 mx-auto {{ request()->routeIs('items') ? 'bg-slate-200/90 dark:bg-slate-800/90 text-green-700 dark:text-green-300 border-l-[5px] border-green-600 shadow-sm font-bold' : 'text-slate-655 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800/60' }} rounded-xl transition-all duration-200" href="{{ route('items') }}">
                        <span class="material-symbols-outlined text-2xl">inventory_2</span>
                        <span class="absolute left-16 bg-slate-900 dark:bg-slate-800 text-white text-xs font-bold px-2 py-1 rounded shadow-lg opacity-0 translate-x-[-10px] group-hover/nav:opacity-100 group-hover/nav:translate-x-0 transition-all pointer-events-none whitespace-nowrap z-50">
                            Items Catalog
                        </span>
                    </a>
                    <a class="relative group/nav flex items-center justify-center w-12 h-12 mx-auto {{ request()->routeIs('barcode.printing') ? 'bg-slate-200/90 dark:bg-slate-800/90 text-green-700 dark:text-green-300 border-l-[5px] border-green-600 shadow-sm font-bold' : 'text-slate-655 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800/60' }} rounded-xl transition-all duration-200" href="{{ route('barcode.printing') }}">
                        <span class="material-symbols-outlined text-2xl">print</span>
                        <span class="absolute left-16 bg-slate-900 dark:bg-slate-800 text-white text-xs font-bold px-2 py-1 rounded shadow-lg opacity-0 translate-x-[-10px] group-hover/nav:opacity-100 group-hover/nav:translate-x-0 transition-all pointer-events-none whitespace-nowrap z-50">
                            Print Labels
                        </span>
                    </a>
                </div>
            </div>

            <!-- Group: Operations -->
            <div>
                <div class="text-[8px] font-black text-slate-400 dark:text-slate-500 uppercase text-center select-none py-1 border-t border-slate-200/50 dark:border-slate-850 mt-2 mb-1">OPS</div>
                <div class="space-y-2">
                    @if(auth()->user()->role !== 'auditor')
                    <a class="relative group/nav flex items-center justify-center w-12 h-12 mx-auto {{ request()->routeIs('scan') ? 'bg-slate-200/90 dark:bg-slate-800/90 text-green-700 dark:text-green-300 border-l-[5px] border-green-600 shadow-sm font-bold' : 'text-slate-655 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800/60' }} rounded-xl transition-all duration-200" href="{{ route('scan') }}">
                        <span class="material-symbols-outlined text-2xl">barcode_scanner</span>
                        <span class="absolute left-16 bg-slate-900 dark:bg-slate-800 text-white text-xs font-bold px-2 py-1 rounded shadow-lg opacity-0 translate-x-[-10px] group-hover/nav:opacity-100 group-hover/nav:translate-x-0 transition-all pointer-events-none whitespace-nowrap z-50">
                            Scan Out
                        </span>
                    </a>
                    <a class="relative group/nav flex items-center justify-center w-12 h-12 mx-auto {{ request()->routeIs('stock-in') ? 'bg-slate-200/90 dark:bg-slate-800/90 text-green-700 dark:text-green-300 border-l-[5px] border-green-600 shadow-sm font-bold' : 'text-slate-655 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800/60' }} rounded-xl transition-all duration-200" href="{{ route('stock-in') }}">
                        <span class="material-symbols-outlined text-2xl">input</span>
                        <span class="absolute left-16 bg-slate-900 dark:bg-slate-800 text-white text-xs font-bold px-2 py-1 rounded shadow-lg opacity-0 translate-x-[-10px] group-hover/nav:opacity-100 group-hover/nav:translate-x-0 transition-all pointer-events-none whitespace-nowrap z-50">
                            Stock In
                        </span>
                    </a>
                    @endif
                    <a class="relative group/nav flex items-center justify-center w-12 h-12 mx-auto {{ request()->routeIs('opname') ? 'bg-slate-200/90 dark:bg-slate-800/90 text-green-700 dark:text-green-300 border-l-[5px] border-green-600 shadow-sm font-bold' : 'text-slate-655 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800/60' }} rounded-xl transition-all duration-200" href="{{ route('opname') }}">
                        <span class="material-symbols-outlined text-2xl">fact_check</span>
                        <span class="absolute left-16 bg-slate-900 dark:bg-slate-800 text-white text-xs font-bold px-2 py-1 rounded shadow-lg opacity-0 translate-x-[-10px] group-hover/nav:opacity-100 group-hover/nav:translate-x-0 transition-all pointer-events-none whitespace-nowrap z-50">
                            Opname Audit
                        </span>
                    </a>
                </div>
            </div>

            <!-- Group: Reports -->
            <div>
                <div class="text-[8px] font-black text-slate-400 dark:text-slate-500 uppercase text-center select-none py-1 border-t border-slate-200/50 dark:border-slate-850 mt-2 mb-1">REP</div>
                <div class="space-y-2">
                    <a class="relative group/nav flex items-center justify-center w-12 h-12 mx-auto {{ request()->routeIs('reports.movement-ledger') ? 'bg-slate-200/90 dark:bg-slate-800/90 text-green-700 dark:text-green-300 border-l-[5px] border-green-600 shadow-sm font-bold' : 'text-slate-655 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800/60' }} rounded-xl transition-all duration-200" href="{{ route('reports.movement-ledger') }}">
                        <span class="material-symbols-outlined text-2xl">receipt_long</span>
                        <span class="absolute left-16 bg-slate-900 dark:bg-slate-800 text-white text-xs font-bold px-2 py-1 rounded shadow-lg opacity-0 translate-x-[-10px] group-hover/nav:opacity-100 group-hover/nav:translate-x-0 transition-all pointer-events-none whitespace-nowrap z-50">
                            Kartu Stok
                        </span>
                    </a>
                    <a class="relative group/nav flex items-center justify-center w-12 h-12 mx-auto {{ request()->routeIs('reports.stock-out') || request()->routeIs('reports.stock-in') ? 'bg-slate-200/90 dark:bg-slate-800/90 text-green-700 dark:text-green-300 border-l-[5px] border-green-600 shadow-sm font-bold' : 'text-slate-655 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800/60' }} rounded-xl transition-all duration-200" href="{{ route('reports.stock-out') }}">
                        <span class="material-symbols-outlined text-2xl">description</span>
                        <span class="absolute left-16 bg-slate-900 dark:bg-slate-800 text-white text-xs font-bold px-2 py-1 rounded shadow-lg opacity-0 translate-x-[-10px] group-hover/nav:opacity-100 group-hover/nav:translate-x-0 transition-all pointer-events-none whitespace-nowrap z-50">
                            Reports Hub
                        </span>
                    </a>
                </div>
            </div>

            <!-- Group: System Configuration -->
            <div>
                <div class="text-[8px] font-black text-slate-400 dark:text-slate-500 uppercase text-center select-none py-1 border-t border-slate-200/50 dark:border-slate-850 mt-2 mb-1">SYS</div>
                <div class="space-y-2">
                    <a class="relative group/nav flex items-center justify-center w-12 h-12 mx-auto {{ request()->routeIs('settings.departments') ? 'bg-slate-200/90 dark:bg-slate-800/90 text-green-700 dark:text-green-300 border-l-[5px] border-green-600 shadow-sm font-bold' : 'text-slate-655 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800/60' }} rounded-xl transition-all duration-200" href="{{ route('settings.departments') }}">
                        <span class="material-symbols-outlined text-2xl">corporate_fare</span>
                        <span class="absolute left-16 bg-slate-900 dark:bg-slate-800 text-white text-xs font-bold px-2 py-1 rounded shadow-lg opacity-0 translate-x-[-10px] group-hover/nav:opacity-100 group-hover/nav:translate-x-0 transition-all pointer-events-none whitespace-nowrap z-50">
                            Departments
                        </span>
                    </a>
                    <a class="relative group/nav flex items-center justify-center w-12 h-12 mx-auto {{ request()->routeIs('settings.users') ? 'bg-slate-200/90 dark:bg-slate-800/90 text-green-700 dark:text-green-300 border-l-[5px] border-green-600 shadow-sm font-bold' : 'text-slate-655 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800/60' }} rounded-xl transition-all duration-200" href="{{ route('settings.users') }}">
                        <span class="material-symbols-outlined text-2xl">person</span>
                        <span class="absolute left-16 bg-slate-900 dark:bg-slate-800 text-white text-xs font-bold px-2 py-1 rounded shadow-lg opacity-0 translate-x-[-10px] group-hover/nav:opacity-100 group-hover/nav:translate-x-0 transition-all pointer-events-none whitespace-nowrap z-50">
                            PIC Master
                        </span>
                    </a>
                </div>
            </div>

        </div>
    </aside>

    <!-- Main Content Canvas -->
    <main class="flex-1 lg:ml-[84px] flex flex-col min-h-screen">
        <!-- TopAppBar -->
        <header class="fixed top-0 right-0 left-0 lg:left-[84px] z-30 bg-slate-50/85 backdrop-blur-md border-b border-slate-200">
            <div class="flex items-center justify-between px-6 h-11 w-full">
                <div class="flex items-center gap-4 flex-1">
                    @if(auth()->check())
                    @php
                        $whCode = session('active_warehouse_code', 'SPAREPART');
                        $colorClass = match($whCode) {
                            'SPAREPART' => 'teal',
                            'RAW_MATERIAL' => 'amber',
                            'CONSUMABLE' => 'purple',
                            'FINISHED_GOODS' => 'blue',
                            default => 'teal'
                        };
                    @endphp
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-2 px-3 py-1 bg-white border border-slate-200 rounded-md shadow-sm text-xs font-black uppercase tracking-wider transition-all duration-200 border-l-4 border-l-{{ $colorClass }}-500 hover:bg-slate-50 active:scale-95">
                            <span class="material-symbols-outlined text-sm text-{{ $colorClass }}-600" style="font-variation-settings: 'FILL' 1;">warehouse</span>
                            <span class="text-slate-800 dark:text-slate-200">{{ session('active_warehouse_name', 'Spareparts Warehouse') }}</span>
                            <span class="material-symbols-outlined text-xs text-slate-400">arrow_drop_down</span>
                        </button>
                        <div x-show="open" @click.away="open = false" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" class="absolute left-0 mt-1 w-64 bg-white border border-slate-200 rounded-md shadow-lg z-50 overflow-hidden" style="display: none;">
                            <div class="bg-slate-50 px-3 py-1.5 border-b border-slate-100">
                                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Switch Warehouse Context</span>
                            </div>
                            @foreach(auth()->user()->warehouses as $wh)
                                @php
                                    $whColor = match($wh->code) {
                                        'SPAREPART' => 'teal',
                                        'RAW_MATERIAL' => 'amber',
                                        'CONSUMABLE' => 'purple',
                                        'FINISHED_GOODS' => 'blue',
                                        default => 'teal'
                                    };
                                    $isActive = $wh->id == session('active_warehouse_id');
                                @endphp
                                <form action="{{ route('warehouse.switch', $wh->id) }}" method="POST" class="w-full">
                                    @csrf
                                    <button type="submit" 
                                        @if($isActive) disabled @else onclick="return confirm('⚠️ Are you sure? Switching warehouses will automatically clear your active scanner cart and Inbound receipt drafts to prevent stock contamination.')" @endif
                                        class="w-full flex items-center justify-between px-4 py-2 hover:bg-slate-50 text-left transition-colors border-l-4 {{ $isActive ? 'border-l-'.$whColor.'-500 bg-slate-50/50 font-black text-'.$whColor.'-700' : 'border-l-transparent text-slate-700' }}">
                                        <div class="flex items-center gap-2">
                                            <span class="material-symbols-outlined text-sm {{ $isActive ? 'text-'.$whColor.'-600' : 'text-slate-400' }}" style="font-variation-settings: 'FILL' 1;">warehouse</span>
                                            <span class="text-xs uppercase tracking-wider">{{ $wh->name }}</span>
                                        </div>
                                        @if($isActive)
                                        <span class="material-symbols-outlined text-xs text-{{ $whColor }}-600 font-bold">check_circle</span>
                                        @endif
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
                <div class="flex items-center gap-4">
                    <button class="p-2 text-slate-600 hover:bg-slate-100 rounded-full transition-colors active:scale-95">
                        <span class="material-symbols-outlined">notifications</span>
                    </button>
                    <button class="p-2 text-slate-600 hover:bg-slate-100 rounded-full transition-colors active:scale-95">
                        <span class="material-symbols-outlined">settings</span>
                    </button>
                    <div class="flex items-center gap-2 pl-2 border-l border-slate-200">
                        <form action="{{ route('logout') }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="p-2 text-slate-600 hover:bg-slate-100 rounded-full transition-colors active:scale-95" title="Sign Out">
                                <span class="material-symbols-outlined">logout</span>
                            </button>
                        </form>
                        <div class="text-right hidden sm:block">
                            <p class="text-[10px] font-black text-slate-900 uppercase leading-none">{{ auth()->user()->name }}</p>
                            <p class="text-[8px] font-bold text-slate-400 uppercase tracking-widest">{{ auth()->user()->role }}</p>
                        </div>
                        <img alt="User profile" class="w-8 h-8 rounded-full border-2 border-slate-200" src="{{ asset('images/placeholders/avatar.svg') }}"/>
                    </div>
                </div>
            </div>
        </header>

        <!-- Dynamic Page Content -->
        @yield('content')
        {{ $slot ?? '' }}
    </main>

    <!-- BottomNavBar (Mobile Shell Only) -->
    <nav class="fixed bottom-0 left-0 w-full z-50 flex justify-around items-center px-4 pb-4 pt-2 lg:hidden bg-slate-50/85 dark:bg-slate-900/85 backdrop-blur-md rounded-t-xl shadow-[0px_-4px_12px_rgba(0,0,0,0.05)]">
        <a href="{{ route('dashboard') }}" class="flex flex-col items-center justify-center {{ request()->routeIs('dashboard') ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 rounded-xl px-4 py-2 shadow-sm' : 'text-slate-500' }} transition-all duration-200">
            <span class="material-symbols-outlined" style="{{ request()->routeIs('dashboard') ? "font-variation-settings: 'FILL' 1;" : '' }}">dashboard</span>
            <span class="font-inter text-[10px] font-bold uppercase tracking-widest">Home</span>
        </a>
        @if(auth()->user()->role !== 'auditor')
        <a href="{{ route('scan') }}" class="flex flex-col items-center justify-center {{ request()->routeIs('scan') ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 rounded-xl px-4 py-2 shadow-sm' : 'text-slate-500' }} transition-all duration-200">
            <span class="material-symbols-outlined" style="{{ request()->routeIs('scan') ? "font-variation-settings: 'FILL' 1;" : '' }}">barcode_scanner</span>
            <span class="font-inter text-[10px] font-bold uppercase tracking-widest">Scan</span>
        </a>
        <a href="{{ route('stock-in') }}" class="flex flex-col items-center justify-center {{ request()->routeIs('stock-in') ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 rounded-xl px-5 py-2 shadow-sm' : 'text-slate-400' }} transition-all duration-200">
            <span class="material-symbols-outlined" style="{{ request()->routeIs('stock-in') ? "font-variation-settings: 'FILL' 1;" : '' }}">input</span>
            <span class="font-inter text-[10px] font-bold uppercase tracking-widest">In</span>
        </a>
        @endif
        <a href="{{ route('items') }}" class="flex flex-col items-center justify-center {{ request()->routeIs('items') ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 rounded-xl px-4 py-2 shadow-sm' : 'text-slate-500' }} transition-all duration-200">
            <span class="material-symbols-outlined" style="{{ request()->routeIs('items') ? "font-variation-settings: 'FILL' 1;" : '' }}">inventory_2</span>
            <span class="font-inter text-[10px] font-bold uppercase tracking-widest">Items</span>
        </a>
        <a href="{{ route('barcode.printing') }}" class="flex flex-col items-center justify-center {{ request()->routeIs('barcode.printing') ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 rounded-xl px-4 py-2 shadow-sm' : 'text-slate-500' }} transition-all duration-200">
            <span class="material-symbols-outlined" style="{{ request()->routeIs('barcode.printing') ? "font-variation-settings: 'FILL' 1;" : '' }}">print</span>
            <span class="font-inter text-[10px] font-bold uppercase tracking-widest">Print</span>
        </a>
        <a href="{{ route('opname') }}" class="flex flex-col items-center justify-center {{ request()->routeIs('opname') ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 rounded-xl px-4 py-2 shadow-sm' : 'text-slate-500' }} transition-all duration-200">
            <span class="material-symbols-outlined" style="{{ request()->routeIs('opname') ? "font-variation-settings: 'FILL' 1;" : '' }}">inventory</span>
            <span class="font-inter text-[10px] font-bold uppercase tracking-widest">Opname</span>
        </a>
    </nav>

</body>
</html>
