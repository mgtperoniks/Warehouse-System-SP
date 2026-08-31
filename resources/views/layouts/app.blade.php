<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>WMS Orchestrator</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

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
        [x-cloak] { display: none !important; }

        /* =========================================================
           WMS DESKTOP SIDEBAR COLLAPSE ARCHITECTURE
           Single Source of Truth: Driven by body.sidebar-collapsed / body.sidebar-expanded
           ========================================================= */
        :root {
            --wms-sidebar-width: 240px;
        }

        body.sidebar-expanded {
            --wms-sidebar-width: 240px;
        }

        body.sidebar-collapsed {
            --wms-sidebar-width: 84px;
        }

        @media (min-width: 1024px) {
            #desktop-sidebar {
                width: var(--wms-sidebar-width) !important;
                transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            }

            #main-canvas {
                margin-left: var(--wms-sidebar-width) !important;
                width: calc(100% - var(--wms-sidebar-width)) !important;
                max-width: calc(100% - var(--wms-sidebar-width)) !important;
                transition: margin-left 0.25s cubic-bezier(0.4, 0, 0.2, 1), width 0.25s cubic-bezier(0.4, 0, 0.2, 1), max-width 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            }

            #top-app-bar {
                left: var(--wms-sidebar-width) !important;
                transition: left 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .sidebar-fixed-bottom-bar {
                left: var(--wms-sidebar-width) !important;
                transition: left 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            }

            /* Strict Icon-Only Mode for Collapsed Sidebar */
            body.sidebar-collapsed .sidebar-text-label,
            body.sidebar-collapsed .sidebar-section-title,
            body.sidebar-collapsed .sidebar-branding-text {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
                width: 0 !important;
                height: 0 !important;
                overflow: hidden !important;
                pointer-events: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            /* Section divider in collapsed mode: clean hairline divider only */
            body.sidebar-collapsed .sidebar-section-container {
                margin-top: 0.5rem;
                padding-top: 0.5rem;
                border-top: 1px solid rgba(226, 232, 240, 0.6);
            }
            .dark body.sidebar-collapsed .sidebar-section-container {
                border-top: 1px solid rgba(30, 41, 59, 0.6);
            }

            body.sidebar-collapsed .sidebar-nav-item {
                width: 48px !important;
                height: 48px !important;
                padding: 0 !important;
                justify-content: center !important;
                margin-left: auto !important;
                margin-right: auto !important;
            }

            /* Tooltips in collapsed mode */
            body.sidebar-collapsed .group\/nav:hover .sidebar-hover-tooltip {
                opacity: 1 !important;
                transform: translateX(0) !important;
                pointer-events: auto !important;
            }
            body.sidebar-expanded .sidebar-hover-tooltip {
                display: none !important;
            }
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

    {{-- Shared Camera Scanner Engine (PeroniksCameraScanner) --}}
    {{-- Loaded here so it is available on every authenticated page. --}}
    <script src="{{ asset('assets/js/peroniksscanner.js') }}" defer></script>
</head>
<body 
    x-data="{ sidebarCollapsed: localStorage.getItem('wms_sidebar_collapsed') === 'true' }" 
    x-init="$watch('sidebarCollapsed', val => localStorage.setItem('wms_sidebar_collapsed', val))" 
    :class="sidebarCollapsed ? 'sidebar-collapsed' : 'sidebar-expanded'" 
    class="bg-surface text-on-surface min-h-screen antialiased">
    <!-- SideNavBar (Desktop Shell) -->
    <aside 
        id="desktop-sidebar"
        class="fixed left-0 top-0 h-full p-2 h-screen hidden lg:flex flex-col border-r border-slate-200 dark:border-slate-800 bg-slate-100/85 dark:bg-slate-900/85 backdrop-blur-md z-40">
        <!-- Sticky Top: Branding & Collapse Toggle -->
        <div class="mb-4 flex-shrink-0 flex items-center justify-between transition-all duration-200" :class="sidebarCollapsed ? 'flex-col gap-2 px-0' : 'px-2'">
            <div class="flex items-center gap-2.5 overflow-hidden min-w-0" :class="sidebarCollapsed ? 'justify-center' : ''">
                <div class="w-10 h-10 rounded-lg bg-green-600 text-white flex items-center justify-center shadow-md shadow-green-600/20 shrink-0">
                    <span class="material-symbols-outlined text-xl">warehouse</span>
                </div>
                <div class="sidebar-branding-text text-left leading-tight truncate">
                    <h1 class="text-[11px] font-black tracking-tight text-slate-900 dark:text-white uppercase leading-none">Terminal 01</h1>
                    <p class="text-[8px] text-slate-500 font-bold uppercase tracking-widest leading-none mt-1">Dock A</p>
                </div>
            </div>
            <button 
                type="button" 
                @click="sidebarCollapsed = !sidebarCollapsed"
                :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                :aria-label="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                class="p-1.5 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-200/60 dark:hover:bg-slate-800/60 rounded-lg transition-colors flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-lg" x-text="sidebarCollapsed ? 'chevron_right' : 'chevron_left'"></span>
            </button>
        </div>

        <!-- Scrollable Middle: Unified Grouped Navigation -->
        <div class="flex-1 overflow-y-auto space-y-3 pb-6 pr-1 custom-sidebar-scroll">
            
            <!-- General Link -->
            <a 
                class="sidebar-nav-item relative group/nav flex items-center w-full px-3 py-2.5 justify-start gap-3 {{ request()->routeIs('dashboard') ? 'bg-slate-200/90 dark:bg-slate-800/90 text-green-700 dark:text-green-300 border-l-[4px] border-green-600 shadow-sm font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800/60 border-l-[4px] border-transparent' }} rounded-xl transition-all duration-200" 
                href="{{ route('dashboard') }}">
                <span class="material-symbols-outlined text-2xl shrink-0">dashboard</span>
                <span class="sidebar-text-label text-xs font-bold truncate">Dashboard</span>
                <span class="sidebar-hover-tooltip absolute left-16 bg-slate-900 dark:bg-slate-800 text-white text-xs font-bold px-2.5 py-1.5 rounded-md shadow-xl opacity-0 translate-x-[-8px] transition-all duration-200 pointer-events-none whitespace-nowrap z-50">
                    Dashboard
                </span>
            </a>

            <!-- Group: Inventory -->
            <div class="sidebar-section-container">
                <div class="sidebar-section-title text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase px-3 py-1 border-t border-slate-200/50 dark:border-slate-850 mt-2 mb-1">Inventory</div>
                <div class="space-y-1.5">
                    <a 
                        class="sidebar-nav-item relative group/nav flex items-center w-full px-3 py-2.5 justify-start gap-3 {{ request()->routeIs('items') ? 'bg-slate-200/90 dark:bg-slate-800/90 text-green-700 dark:text-green-300 border-l-[4px] border-green-600 shadow-sm font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800/60 border-l-[4px] border-transparent' }} rounded-xl transition-all duration-200" 
                        href="{{ route('items') }}">
                        <span class="material-symbols-outlined text-2xl shrink-0">inventory_2</span>
                        <span class="sidebar-text-label text-xs font-bold truncate">Items Catalog</span>
                        <span class="sidebar-hover-tooltip absolute left-16 bg-slate-900 dark:bg-slate-800 text-white text-xs font-bold px-2.5 py-1.5 rounded-md shadow-xl opacity-0 translate-x-[-8px] transition-all duration-200 pointer-events-none whitespace-nowrap z-50">
                            Items Catalog
                        </span>
                    </a>
                    <a 
                        class="sidebar-nav-item relative group/nav flex items-center w-full px-3 py-2.5 justify-start gap-3 {{ request()->routeIs('items.planning') ? 'bg-slate-200/90 dark:bg-slate-800/90 text-green-700 dark:text-green-300 border-l-[4px] border-green-600 shadow-sm font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800/60 border-l-[4px] border-transparent' }} rounded-xl transition-all duration-200" 
                        href="{{ route('items.planning') }}">
                        <span class="material-symbols-outlined text-2xl shrink-0">assignment</span>
                        <span class="sidebar-text-label text-xs font-bold truncate">Inventory Planning</span>
                        <span class="sidebar-hover-tooltip absolute left-16 bg-slate-900 dark:bg-slate-800 text-white text-xs font-bold px-2.5 py-1.5 rounded-md shadow-xl opacity-0 translate-x-[-8px] transition-all duration-200 pointer-events-none whitespace-nowrap z-50">
                            Inventory Planning
                        </span>
                    </a>
                    <a 
                        class="sidebar-nav-item relative group/nav flex items-center w-full px-3 py-2.5 justify-start gap-3 {{ request()->routeIs('barcode.printing') ? 'bg-slate-200/90 dark:bg-slate-800/90 text-green-700 dark:text-green-300 border-l-[4px] border-green-600 shadow-sm font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800/60 border-l-[4px] border-transparent' }} rounded-xl transition-all duration-200" 
                        href="{{ route('barcode.printing') }}">
                        <span class="material-symbols-outlined text-2xl shrink-0">print</span>
                        <span class="sidebar-text-label text-xs font-bold truncate">Print Labels</span>
                        <span class="sidebar-hover-tooltip absolute left-16 bg-slate-900 dark:bg-slate-800 text-white text-xs font-bold px-2.5 py-1.5 rounded-md shadow-xl opacity-0 translate-x-[-8px] transition-all duration-200 pointer-events-none whitespace-nowrap z-50">
                            Print Labels
                        </span>
                    </a>
                </div>
            </div>

            <!-- Group: Receiving -->
            <div class="sidebar-section-container">
                <div class="sidebar-section-title text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase px-3 py-1 border-t border-slate-200/50 dark:border-slate-850 mt-2 mb-1">Receiving</div>
                <div class="space-y-1.5">
                    <a 
                        class="sidebar-nav-item relative group/nav flex items-center w-full px-3 py-2.5 justify-start gap-3 {{ request()->routeIs('outstanding-purchases') || request()->routeIs('outstanding-purchases.show') || request()->routeIs('outstanding-purchases.import') ? 'bg-slate-200/90 dark:bg-slate-800/90 text-green-700 dark:text-green-300 border-l-[4px] border-green-600 shadow-sm font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800/60 border-l-[4px] border-transparent' }} rounded-xl transition-all duration-200" 
                        href="{{ route('outstanding-purchases') }}">
                        <span class="material-symbols-outlined text-2xl shrink-0">receipt_long</span>
                        <span class="sidebar-text-label text-xs font-bold truncate">Outstanding Purchases</span>
                        <span class="sidebar-hover-tooltip absolute left-16 bg-slate-900 dark:bg-slate-800 text-white text-xs font-bold px-2.5 py-1.5 rounded-md shadow-xl opacity-0 translate-x-[-8px] transition-all duration-200 pointer-events-none whitespace-nowrap z-50">
                            Outstanding Purchases
                        </span>
                    </a>
                </div>
            </div>

            <!-- Group: Operations -->
            <div class="sidebar-section-container">
                <div class="sidebar-section-title text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase px-3 py-1 border-t border-slate-200/50 dark:border-slate-850 mt-2 mb-1">Operations</div>
                <div class="space-y-1.5">
                    @if(auth()->user()->role !== 'auditor')
                    <a 
                        class="sidebar-nav-item relative group/nav flex items-center w-full px-3 py-2.5 justify-start gap-3 {{ request()->routeIs('scan') ? 'bg-slate-200/90 dark:bg-slate-800/90 text-green-700 dark:text-green-300 border-l-[4px] border-green-600 shadow-sm font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800/60 border-l-[4px] border-transparent' }} rounded-xl transition-all duration-200" 
                        href="{{ route('scan') }}">
                        <span class="material-symbols-outlined text-2xl shrink-0">barcode_scanner</span>
                        <span class="sidebar-text-label text-xs font-bold truncate">Scan Out</span>
                        <span class="sidebar-hover-tooltip absolute left-16 bg-slate-900 dark:bg-slate-800 text-white text-xs font-bold px-2.5 py-1.5 rounded-md shadow-xl opacity-0 translate-x-[-8px] transition-all duration-200 pointer-events-none whitespace-nowrap z-50">
                            Scan Out
                        </span>
                    </a>
                    <a 
                        class="sidebar-nav-item relative group/nav flex items-center w-full px-3 py-2.5 justify-start gap-3 {{ request()->routeIs('stock-in') ? 'bg-slate-200/90 dark:bg-slate-800/90 text-green-700 dark:text-green-300 border-l-[4px] border-green-600 shadow-sm font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800/60 border-l-[4px] border-transparent' }} rounded-xl transition-all duration-200" 
                        href="{{ route('stock-in') }}">
                        <span class="material-symbols-outlined text-2xl shrink-0">input</span>
                        <span class="sidebar-text-label text-xs font-bold truncate">Stock In</span>
                        <span class="sidebar-hover-tooltip absolute left-16 bg-slate-900 dark:bg-slate-800 text-white text-xs font-bold px-2.5 py-1.5 rounded-md shadow-xl opacity-0 translate-x-[-8px] transition-all duration-200 pointer-events-none whitespace-nowrap z-50">
                            Stock In
                        </span>
                    </a>
                    @endif
                </div>
            </div>

            <!-- Group: Reports -->
            <div class="sidebar-section-container">
                <div class="sidebar-section-title text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase px-3 py-1 border-t border-slate-200/50 dark:border-slate-850 mt-2 mb-1">Reports</div>
                <div class="space-y-1.5">
                    <a 
                        class="sidebar-nav-item relative group/nav flex items-center w-full px-3 py-2.5 justify-start gap-3 {{ request()->routeIs('reports.movement-ledger') ? 'bg-slate-200/90 dark:bg-slate-800/90 text-green-700 dark:text-green-300 border-l-[4px] border-green-600 shadow-sm font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800/60 border-l-[4px] border-transparent' }} rounded-xl transition-all duration-200" 
                        href="{{ route('reports.movement-ledger') }}">
                        <span class="material-symbols-outlined text-2xl shrink-0">receipt_long</span>
                        <span class="sidebar-text-label text-xs font-bold truncate">Kartu Stok</span>
                        <span class="sidebar-hover-tooltip absolute left-16 bg-slate-900 dark:bg-slate-800 text-white text-xs font-bold px-2.5 py-1.5 rounded-md shadow-xl opacity-0 translate-x-[-8px] transition-all duration-200 pointer-events-none whitespace-nowrap z-50">
                            Kartu Stok
                        </span>
                    </a>
                    <a 
                        class="sidebar-nav-item relative group/nav flex items-center w-full px-3 py-2.5 justify-start gap-3 {{ request()->routeIs('reports.stock-out') || request()->routeIs('reports.stock-in') ? 'bg-slate-200/90 dark:bg-slate-800/90 text-green-700 dark:text-green-300 border-l-[4px] border-green-600 shadow-sm font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800/60 border-l-[4px] border-transparent' }} rounded-xl transition-all duration-200" 
                        href="{{ route('reports.stock-out') }}">
                        <span class="material-symbols-outlined text-2xl shrink-0">description</span>
                        <span class="sidebar-text-label text-xs font-bold truncate">Reports Hub</span>
                        <span class="sidebar-hover-tooltip absolute left-16 bg-slate-900 dark:bg-slate-800 text-white text-xs font-bold px-2.5 py-1.5 rounded-md shadow-xl opacity-0 translate-x-[-8px] transition-all duration-200 pointer-events-none whitespace-nowrap z-50">
                            Reports Hub
                        </span>
                    </a>
                </div>
            </div>

            <!-- Group: Governance -->
            <div class="sidebar-section-container">
                <div class="sidebar-section-title text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase px-3 py-1 border-t border-slate-200/50 dark:border-slate-850 mt-2 mb-1">Governance</div>
                <div class="space-y-1.5">
                    <a 
                        class="sidebar-nav-item relative group/nav flex items-center w-full px-3 py-2.5 justify-start gap-3 {{ request()->routeIs('opname') ? 'bg-slate-200/90 dark:bg-slate-800/90 text-green-700 dark:text-green-300 border-l-[4px] border-green-600 shadow-sm font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800/60 border-l-[4px] border-transparent' }} rounded-xl transition-all duration-200" 
                        href="{{ route('opname') }}">
                        <span class="material-symbols-outlined text-2xl shrink-0">fact_check</span>
                        <span class="sidebar-text-label text-xs font-bold truncate">Stock Opname</span>
                        <span class="sidebar-hover-tooltip absolute left-16 bg-slate-900 dark:bg-slate-800 text-white text-xs font-bold px-2.5 py-1.5 rounded-md shadow-xl opacity-0 translate-x-[-8px] transition-all duration-200 pointer-events-none whitespace-nowrap z-50">
                            Stock Opname
                        </span>
                    </a>
                    <a 
                        class="sidebar-nav-item relative group/nav flex items-center w-full px-3 py-2.5 justify-start gap-3 {{ request()->routeIs('inventory-adjustments') ? 'bg-slate-200/90 dark:bg-slate-800/90 text-green-700 dark:text-green-300 border-l-[4px] border-green-600 shadow-sm font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800/60 border-l-[4px] border-transparent' }} rounded-xl transition-all duration-200" 
                        href="{{ route('inventory-adjustments') }}">
                        <span class="material-symbols-outlined text-2xl shrink-0">rule</span>
                        <span class="sidebar-text-label text-xs font-bold truncate">Adjustments Queue</span>
                        <span class="sidebar-hover-tooltip absolute left-16 bg-slate-900 dark:bg-slate-800 text-white text-xs font-bold px-2.5 py-1.5 rounded-md shadow-xl opacity-0 translate-x-[-8px] transition-all duration-200 pointer-events-none whitespace-nowrap z-50">
                            Adjustments Queue
                        </span>
                    </a>
                    <a 
                        class="sidebar-nav-item relative group/nav flex items-center w-full px-3 py-2.5 justify-start gap-3 {{ request()->routeIs('governance.audit-coverage') ? 'bg-slate-200/90 dark:bg-slate-800/90 text-green-700 dark:text-green-300 border-l-[4px] border-green-600 shadow-sm font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800/60 border-l-[4px] border-transparent' }} rounded-xl transition-all duration-200" 
                        href="{{ route('governance.audit-coverage') }}">
                        <span class="material-symbols-outlined text-2xl shrink-0">track_changes</span>
                        <span class="sidebar-text-label text-xs font-bold truncate">Audit Coverage</span>
                        <span class="sidebar-hover-tooltip absolute left-16 bg-slate-900 dark:bg-slate-800 text-white text-xs font-bold px-2.5 py-1.5 rounded-md shadow-xl opacity-0 translate-x-[-8px] transition-all duration-200 pointer-events-none whitespace-nowrap z-50">
                            Audit Coverage
                        </span>
                    </a>
                </div>
            </div>

            <!-- Group: System Configuration -->
            <div class="sidebar-section-container">
                <div class="sidebar-section-title text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase px-3 py-1 border-t border-slate-200/50 dark:border-slate-850 mt-2 mb-1">System</div>
                <div class="space-y-1.5">
                    <a 
                        class="sidebar-nav-item relative group/nav flex items-center w-full px-3 py-2.5 justify-start gap-3 {{ request()->routeIs('settings.departments') ? 'bg-slate-200/90 dark:bg-slate-800/90 text-green-700 dark:text-green-300 border-l-[4px] border-green-600 shadow-sm font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800/60 border-l-[4px] border-transparent' }} rounded-xl transition-all duration-200" 
                        href="{{ route('settings.departments') }}">
                        <span class="material-symbols-outlined text-2xl shrink-0">corporate_fare</span>
                        <span class="sidebar-text-label text-xs font-bold truncate">Departments</span>
                        <span class="sidebar-hover-tooltip absolute left-16 bg-slate-900 dark:bg-slate-800 text-white text-xs font-bold px-2.5 py-1.5 rounded-md shadow-xl opacity-0 translate-x-[-8px] transition-all duration-200 pointer-events-none whitespace-nowrap z-50">
                            Departments
                        </span>
                    </a>
                    <a 
                        class="sidebar-nav-item relative group/nav flex items-center w-full px-3 py-2.5 justify-start gap-3 {{ request()->routeIs('settings.users') ? 'bg-slate-200/90 dark:bg-slate-800/90 text-green-700 dark:text-green-300 border-l-[4px] border-green-600 shadow-sm font-bold' : 'text-slate-655 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800/60 border-l-[4px] border-transparent' }} rounded-xl transition-all duration-200" 
                        href="{{ route('settings.users') }}">
                        <span class="material-symbols-outlined text-2xl shrink-0">person</span>
                        <span class="sidebar-text-label text-xs font-bold truncate">PIC Master</span>
                        <span class="sidebar-hover-tooltip absolute left-16 bg-slate-900 dark:bg-slate-800 text-white text-xs font-bold px-2.5 py-1.5 rounded-md shadow-xl opacity-0 translate-x-[-8px] transition-all duration-200 pointer-events-none whitespace-nowrap z-50">
                            PIC Master
                        </span>
                    </a>
                </div>
            </div>

        </div>
    </aside>

    <!-- Main Content Canvas -->
    <main id="main-canvas" class="flex flex-col min-h-screen">
        <!-- TopAppBar -->
        <header id="top-app-bar" class="fixed top-0 right-0 left-0 z-30 bg-slate-50/85 backdrop-blur-md border-b border-slate-200">
            <div class="flex items-center justify-between px-6 h-11 w-full">
                <div class="flex items-center gap-4 flex-1">
                    @if(auth()->check())
                    @php
                        $userWarehousesCount = auth()->user()->warehouses->count();
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
                        <button 
                            @if($userWarehousesCount <= 1) disabled @else @click="open = !open" @endif 
                            class="flex items-center gap-2 px-3 py-1 bg-white border border-slate-200 rounded-md shadow-sm text-xs font-black uppercase tracking-wider transition-all duration-200 border-l-4 border-l-{{ $colorClass }}-500 hover:bg-slate-50 active:scale-95 @if($userWarehousesCount <= 1) opacity-75 cursor-not-allowed @endif">
                            <span class="material-symbols-outlined text-sm text-{{ $colorClass }}-600" style="font-variation-settings: 'FILL' 1;">warehouse</span>
                            <span class="text-slate-800 dark:text-slate-200">{{ session('active_warehouse_name', 'Spareparts Warehouse') }}</span>
                            @if($userWarehousesCount > 1)
                            <span class="material-symbols-outlined text-xs text-slate-400">arrow_drop_down</span>
                            @endif
                        </button>
                        @if($userWarehousesCount > 1)
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
                        @endif
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
        <a href="{{ route('outstanding-purchases') }}" class="flex flex-col items-center justify-center {{ request()->routeIs('outstanding-purchases*') ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 rounded-xl px-3 py-2 shadow-sm' : 'text-slate-500' }} transition-all duration-200">
            <span class="material-symbols-outlined" style="{{ request()->routeIs('outstanding-purchases*') ? "font-variation-settings: 'FILL' 1;" : '' }}">receipt_long</span>
            <span class="font-inter text-[9px] sm:text-[10px] font-bold uppercase tracking-wider">Purchase</span>
        </a>
        @endif
        <a href="{{ route('items') }}" class="flex flex-col items-center justify-center {{ request()->routeIs('items') ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 rounded-xl px-4 py-2 shadow-sm' : 'text-slate-500' }} transition-all duration-200">
            <span class="material-symbols-outlined" style="{{ request()->routeIs('items') ? "font-variation-settings: 'FILL' 1;" : '' }}">inventory_2</span>
            <span class="font-inter text-[10px] font-bold uppercase tracking-widest">Items</span>
        </a>
        <a href="{{ route('reports.stock-out') }}" class="flex flex-col items-center justify-center {{ request()->routeIs('reports.*') ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 rounded-xl px-4 py-2 shadow-sm' : 'text-slate-500' }} transition-all duration-200">
            <span class="material-symbols-outlined" style="{{ request()->routeIs('reports.*') ? "font-variation-settings: 'FILL' 1;" : '' }}">description</span>
            <span class="font-inter text-[10px] font-bold uppercase tracking-widest">Reports</span>
        </a>
        <a href="{{ route('opname') }}" class="flex flex-col items-center justify-center {{ request()->routeIs('opname') ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 rounded-xl px-4 py-2 shadow-sm' : 'text-slate-500' }} transition-all duration-200">
            <span class="material-symbols-outlined" style="{{ request()->routeIs('opname') ? "font-variation-settings: 'FILL' 1;" : '' }}">inventory</span>
            <span class="font-inter text-[10px] font-bold uppercase tracking-widest">Opname</span>
        </a>
    </nav>

    @livewireScripts
</body>
</html>
