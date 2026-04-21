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
    </style>
</head>
<body class="bg-surface text-on-surface flex min-h-screen antialiased">
    <!-- SideNavBar (Desktop Shell) -->
    <aside class="fixed left-0 top-0 h-full p-4 space-y-2 h-screen w-64 hidden lg:flex flex-col border-r-0 bg-slate-100/85 dark:bg-slate-900/85 backdrop-blur-md z-40">
        <div class="mb-8 px-4 py-2">
            <h1 class="text-xl font-black tracking-tighter text-slate-900 dark:text-white uppercase">Terminal 01</h1>
            <p class="text-[10px] text-slate-500 font-black uppercase tracking-widest leading-none">Receiving Dock A</p>
        </div>
        <nav class="flex-1 space-y-1">
            <a class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('dashboard') ? 'bg-white dark:bg-slate-800 text-green-600 dark:text-green-400 border-l-4 border-green-600 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200' }} rounded-xl transition-all duration-200" href="{{ route('dashboard') }}">
                <span class="material-symbols-outlined">dashboard</span>
                <span class="font-inter text-sm font-bold">Dashboard</span>
            </a>
            @if(auth()->user()->role !== 'auditor')
            <a class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('scan') ? 'bg-white dark:bg-slate-800 text-green-600 dark:text-green-400 border-l-4 border-green-600 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200' }} rounded-xl transition-all duration-200" href="{{ route('scan') }}">
                <span class="material-symbols-outlined">barcode_scanner</span>
                <span class="font-inter text-sm font-bold">Scan</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('stock-in') ? 'bg-white dark:bg-slate-800 text-green-600 dark:text-green-400 border-l-4 border-green-600 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200' }} rounded-xl transition-all duration-200" href="{{ route('stock-in') }}">
                <span class="material-symbols-outlined">input</span>
                <span class="font-inter text-sm font-bold">Stock In</span>
            </a>
            @endif
            <a class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('items') ? 'bg-white dark:bg-slate-800 text-green-600 dark:text-green-400 border-l-4 border-green-600 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200' }} rounded-xl transition-all duration-200" href="{{ route('items') }}">
                <span class="material-symbols-outlined">inventory_2</span>
                <span class="font-inter text-sm font-bold">Items</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('barcode.printing') ? 'bg-white dark:bg-slate-800 text-green-600 dark:text-green-400 border-l-4 border-green-600 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200' }} rounded-xl transition-all duration-200" href="{{ route('barcode.printing') }}">
                <span class="material-symbols-outlined">print</span>
                <span class="font-inter text-sm font-bold">Print</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('opname') ? 'bg-white dark:bg-slate-800 text-green-600 dark:text-green-400 border-l-4 border-green-600 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200' }} rounded-xl transition-all duration-200" href="{{ route('opname') }}">
                <span class="material-symbols-outlined">inventory</span>
                <span class="font-inter text-sm font-bold">Opname</span>
            </a>
        </nav>
        <div class="pt-4 border-t border-slate-200">
            <p class="px-4 mb-2 text-[10px] text-slate-400 font-bold uppercase tracking-widest">Settings</p>
            <div class="space-y-1">
                <a class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('settings.departments') ? 'bg-white dark:bg-slate-800 text-green-600 border-l-4 border-green-600 shadow-sm' : 'text-slate-600 hover:bg-slate-200' }} rounded-xl transition-all duration-200" href="{{ route('settings.departments') }}">
                    <span class="material-symbols-outlined">corporate_fare</span>
                    <span class="font-inter text-sm font-bold">Departments</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('settings.users') ? 'bg-white dark:bg-slate-800 text-green-600 border-l-4 border-green-600 shadow-sm' : 'text-slate-600 hover:bg-slate-200' }} rounded-xl transition-all duration-200" href="{{ route('settings.users') }}">
                    <span class="material-symbols-outlined">person</span>
                    <span class="font-inter text-sm font-bold">Users / PIC</span>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content Canvas -->
    <main class="flex-1 lg:ml-64 flex flex-col min-h-screen">
        <!-- TopAppBar -->
        <header class="fixed top-0 right-0 left-0 lg:left-64 z-30 bg-slate-50/85 backdrop-blur-md border-b border-slate-200">
            <div class="flex items-center justify-between px-6 h-16 w-full">
                <div class="flex items-center gap-4 flex-1">
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
