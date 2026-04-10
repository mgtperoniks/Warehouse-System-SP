<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>WMS Orchestrator</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            display: inline-block;
            line-height: 1;
            text-transform: none;
            letter-spacing: normal;
            word-wrap: normal;
            white-space: nowrap;
            direction: ltr;
        }
        body { font-family: 'Inter', sans-serif; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        
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
    <script id="tailwind-config">
        tailwind.config = {
          darkMode: "class",
          theme: {
            extend: {
              "colors": {
                      "on-surface": "#191c1e",
                      "surface-container-highest": "#e1e2e4",
                      "primary-fixed": "#dae2ff",
                      "on-tertiary-fixed": "#380d00",
                      "tertiary-fixed": "#ffdbcf",
                      "inverse-surface": "#2e3132",
                      "tertiary-container": "#a33500",
                      "on-tertiary-container": "#ffc6b2",
                      "error-container": "#ffdad6",
                      "outline": "#737685",
                      "on-error-container": "#93000a",
                      "on-secondary-fixed-variant": "#344573",
                      "surface-variant": "#e1e2e4",
                      "on-error": "#ffffff",
                      "surface-bright": "#f8f9fb",
                      "on-primary-fixed": "#001848",
                      "on-secondary-fixed": "#021945",
                      "surface-container-lowest": "#ffffff",
                      "on-secondary-container": "#415382",
                      "secondary-fixed": "#dae2ff",
                      "on-background": "#191c1e",
                      "inverse-on-surface": "#f0f1f3",
                      "primary-container": "#0052cc",
                      "primary-fixed-dim": "#b2c5ff",
                      "outline-variant": "#c3c6d6",
                      "surface-container-high": "#e7e8ea",
                      "tertiary": "#7b2600",
                      "secondary": "#4c5d8d",
                      "on-primary-container": "#c4d2ff",
                      "background": "#f8f9fb",
                      "surface-container": "#edeef0",
                      "inverse-primary": "#b2c5ff",
                      "surface-container-low": "#f3f4f6",
                      "on-primary": "#ffffff",
                      "secondary-fixed-dim": "#b4c5fb",
                      "secondary-container": "#b6c8fe",
                      "surface-dim": "#d9dadc",
                      "surface-tint": "#0c56d0",
                      "on-tertiary": "#ffffff",
                      "tertiary-fixed-dim": "#ffb59b",
                      "on-secondary": "#ffffff",
                      "surface": "#f8f9fb",
                      "on-tertiary-fixed-variant": "#812800",
                      "on-surface-variant": "#434654",
                      "primary": "#003d9b",
                      "on-primary-fixed-variant": "#0040a2",
                      "error": "#ba1a1a"
              },
              "borderRadius": {
                      "DEFAULT": "0.125rem",
                      "lg": "0.25rem",
                      "xl": "0.5rem",
                      "full": "0.75rem"
              },
              "fontFamily": {
                      "headline": ["Inter"],
                      "body": ["Inter"],
                      "label": ["Inter"]
              }
            },
          },
        }
    </script>
</head>
<body class="bg-surface text-on-surface flex min-h-screen">
    <!-- SideNavBar (Desktop Shell) -->
    <aside class="fixed left-0 top-0 h-full p-4 space-y-2 h-screen w-64 hidden lg:flex flex-col border-r-0 bg-slate-50 z-40">
        <div class="mb-8 px-4 py-2">
            <h1 class="text-lg font-black tracking-tighter text-blue-700">Precision WMS</h1>
            <p class="text-xs text-slate-500 font-medium">Warehouse Alpha</p>
        </div>
        <nav class="flex-1 space-y-1">
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-slate-100 rounded-xl hover:translate-x-1 transition-transform duration-200" href="{{ route('dashboard') }}">
                <span class="material-symbols-outlined">dashboard</span>
                <span class="font-inter text-sm font-medium">Dashboard</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-3 bg-white text-blue-700 rounded-xl shadow-sm border-l-4 border-blue-600 hover:translate-x-1 transition-transform duration-200" href="{{ route('scan') }}">
                <span class="material-symbols-outlined">barcode_scanner</span>
                <span class="font-inter text-sm font-medium">Scan</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-slate-100 rounded-xl hover:translate-x-1 transition-transform duration-200" href="{{ route('items') }}">
                <span class="material-symbols-outlined">inventory_2</span>
                <span class="font-inter text-sm font-medium">Items</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-slate-100 rounded-xl hover:translate-x-1 transition-transform duration-200" href="{{ route('opname') }}">
                <span class="material-symbols-outlined">inventory</span>
                <span class="font-inter text-sm font-medium">Opname</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-slate-100 rounded-xl hover:translate-x-1 transition-transform duration-200" href="#">
                <span class="material-symbols-outlined">more_horiz</span>
                <span class="font-inter text-sm font-medium">More</span>
            </a>
        </nav>
        <div class="pt-4 border-t border-slate-100 space-y-1">
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-slate-100 rounded-xl" href="#">
                <span class="material-symbols-outlined">settings</span>
                <span class="font-inter text-sm font-medium">Settings</span>
            </a>
        </div>
    </aside>

    <!-- Main Content Canvas -->
    <main class="flex-1 lg:ml-64 flex flex-col min-h-screen pb-20 lg:pb-0">
        <!-- TopAppBar -->
        <header class="fixed top-0 right-0 left-0 lg:left-64 z-30 bg-slate-50/85 backdrop-blur-md shadow-sm">
            <div class="flex items-center justify-between px-6 py-3 w-full">
                <div class="flex items-center gap-4 flex-1">
                    <div class="relative w-full max-w-md hidden sm:block">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
                        <input class="w-full pl-10 pr-4 py-2 bg-surface-container rounded-full border-none focus:ring-2 focus:ring-primary-container text-sm" placeholder="Global search..." type="text"/>
                    </div>
                    <span class="sm:hidden font-bold text-lg">WMS Orchestrator</span>
                </div>
                <div class="flex items-center gap-3">
                    <button class="p-2 text-slate-600 hover:bg-slate-100 rounded-full transition-colors active:scale-95">
                        <span class="material-symbols-outlined">qr_code_scanner</span>
                    </button>
                    <button class="p-2 text-slate-600 hover:bg-slate-100 rounded-full transition-colors active:scale-95">
                        <span class="material-symbols-outlined">account_circle</span>
                    </button>
                </div>
            </div>
            <div class="bg-slate-200/50 h-[1px] w-full"></div>
        </header>

        <!-- Dynamic Page Content -->
        @yield('content')
    </main>

    <!-- BottomNavBar (Mobile Shell Only) -->
    <nav class="fixed bottom-0 left-0 w-full z-50 flex justify-around items-center px-4 py-3 pb-safe bg-slate-50/85 backdrop-blur-md lg:hidden border-t border-slate-200/20 shadow-[0_-4px_12px_rgba(0,0,0,0.05)] rounded-t-2xl">
        <a href="{{ route('dashboard') }}" class="flex flex-col items-center justify-center text-slate-500 p-2 scale-90">
            <span class="material-symbols-outlined">dashboard</span>
            <span class="font-inter text-[10px] font-bold uppercase tracking-widest">Home</span>
        </a>
        <a href="{{ route('scan') }}" class="flex flex-col items-center justify-center bg-blue-100/50 text-blue-700 rounded-xl p-2 scale-90">
            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">barcode_scanner</span>
            <span class="font-inter text-[10px] font-bold uppercase tracking-widest">Scan</span>
        </a>
        <a href="{{ route('items') }}" class="flex flex-col items-center justify-center text-slate-500 p-2 scale-90">
            <span class="material-symbols-outlined">inventory_2</span>
            <span class="font-inter text-[10px] font-bold uppercase tracking-widest">Items</span>
        </a>
        <a href="{{ route('opname') }}" class="flex flex-col items-center justify-center text-slate-500 p-2 scale-90">
            <span class="material-symbols-outlined">inventory</span>
            <span class="font-inter text-[10px] font-bold uppercase tracking-widest">Opname</span>
        </a>
    </nav>
</body>
</html>
