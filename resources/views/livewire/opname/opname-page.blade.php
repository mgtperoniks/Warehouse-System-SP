<div class="h-full lg:h-[calc(100vh-64px)] overflow-y-auto lg:overflow-hidden bg-surface" x-data>
    <style>
        /* Precision M3 Color Palette from example */
        :root {
            --outline: #737685;
            --surface-container-highest: #e1e2e4;
            --on-background: #191c1e;
            --inverse-primary: #b2c5ff;
            --on-surface-variant: #434654;
            --on-primary-fixed: #001848;
            --surface-dim: #d9dadc;
            --secondary-container: #b6c8fe;
            --surface-container: #edeef0;
            --on-tertiary-fixed-variant: #812800;
            --on-secondary-container: #415382;
            --surface-container-lowest: #ffffff;
            --on-primary: #ffffff;
            --surface: #f8f9fb;
            --secondary: #4c5d8d;
            --on-secondary-fixed: #021945;
            --error-container: #ffdad6;
            --secondary-fixed: #dae2ff;
            --on-error-container: #93000a;
            --inverse-surface: #2e3132;
            --tertiary-fixed: #ffdbcf;
            --secondary-fixed-dim: #b4c5fb;
            --primary-fixed: #dae2ff;
            --primary-container: #0052cc;
            --on-tertiary-container: #ffc6b2;
            --on-surface: #191c1e;
            --primary: #003d9b;
            --tertiary-fixed-dim: #ffb59b;
            --tertiary-container: #a33500;
            --inverse-on-surface: #f0f1f3;
            --surface-bright: #f8f9fb;
            --on-error: #ffffff;
            --background: #f8f9fb;
            --on-secondary-fixed-variant: #344573;
            --outline-variant: #c3c6d6;
            --on-tertiary: #ffffff;
            --on-secondary: #ffffff;
            --surface-variant: #e1e2e4;
            --primary-fixed-dim: #b2c5ff;
            --on-tertiary-fixed: #380d00;
            --surface-container-low: #f3f4f6;
            --surface-container-high: #e7e8ea;
            --on-primary-fixed-variant: #0040a2;
            --error: #ba1a1a;
            --tertiary: #7b2600;
            --surface-tint: #0c56d0;
            --on-primary-container: #c4d2ff;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        /* Mobile specific adjustments */
        @media (max-width: 1024px) {
            .mobile-stack {
                grid-template-columns: 1fr !important;
                height: auto !important;
                overflow-y: visible !important;
                padding-bottom: 100px !important;
            }
            .mobile-column {
                grid-column: span 12 / span 12 !important;
            }
        }

        .dashboard-main {
            padding: 2rem;
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 2rem;
        }

        /* Scrollbar styling for a cleaner look */
        .custom-scroll::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scroll::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
        }
        .custom-scroll::-webkit-scrollbar-thumb {
            background: rgba(0, 61, 155, 0.2);
            border-radius: 10px;
        }
    </style>

    <main class="dashboard-main mobile-stack h-full">
        <!-- Left Column: Bin Scanning -->
        <section class="col-span-12 lg:col-span-3 flex flex-col gap-6 mobile-column">
            <div class="bg-surface-container-low rounded-2xl p-8 flex flex-col h-full border-l-8 border-primary shadow-sm">
                <div class="mb-10">
                    <span class="text-xs font-black text-primary tracking-[0.2em] uppercase mb-1 block">Step 01</span>
                    <h2 class="text-3xl font-black text-on-surface leading-tight tracking-tighter">Bin Localization</h2>
                    <p class="text-on-surface-variant text-sm mt-3 leading-relaxed font-medium">Position yourself at the physical bin location before scanning.</p>
                </div>
                
                <div class="space-y-8">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 mb-2 uppercase tracking-widest">Scan Bin Barcode</label>
                        <div class="relative group">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-primary material-symbols-outlined transition-transform group-focus-within:scale-110" style="font-variation-settings: 'FILL' 1;">qr_code_2</span>
                            <input wire:model="binScan" 
                                   class="w-full bg-white border-2 border-slate-100 rounded-2xl pl-14 pr-4 py-5 font-black text-xl text-primary focus:ring-4 focus:ring-primary/5 focus:border-primary transition-all shadow-sm" 
                                   placeholder="e.g. A-12-04" 
                                   type="text"/>
                        </div>
                    </div>
                    
                    <div class="bg-primary/5 rounded-2xl p-6 border-2 border-primary/10">
                        <div class="flex items-start gap-4">
                            <span class="material-symbols-outlined text-primary text-2xl">info</span>
                            <p class="text-sm text-on-surface-variant leading-relaxed font-semibold">
                                Bin: <strong class="text-primary font-black">Zone A-12-04</strong>. 
                                <br/><span class="text-xs opacity-75">Contains 12 Item variants.</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-auto pt-10 space-y-4">
                    <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-widest">
                        <span class="text-slate-400">Scanner Readiness</span>
                        <span class="text-primary">Operational</span>
                    </div>
                    <div class="w-full bg-slate-200 h-2 rounded-full overflow-hidden">
                        <div class="bg-primary w-full h-full"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Center Column: Active Item Count -->
        <section class="col-span-12 lg:col-span-5 flex flex-col gap-6 mobile-column">
            <div class="bg-white rounded-3xl shadow-2xl shadow-slate-200/50 flex flex-col h-full overflow-hidden border border-slate-100 min-h-[600px]">
                <!-- Item Image Area -->
                <div class="h-80 relative bg-slate-100 overflow-hidden group border-b-2 border-slate-50">
                    <img alt="Industrial Power Drill" 
                         class="w-full h-full object-cover transition-transform duration-1000 group-hover:scale-110" 
                         src="https://lh3.googleusercontent.com/aida-public/AB6AXuDrOTqMzCDN_TErD20Igc30G-sqin2JL2IhGc2H3Lm8mzAmR4NcqEztmS0FwkLA8Ms9FUl2cp-ajiocozq3wl13vH9OCnUkY65JExt-X0XYBc1c-__MDQLAMasRGitfg3d62iKlDtwpA29HUMIRttW1dzU1qSOooLvjwVFoI6vdaTwQ7xMkgO-hHMWK4XtHz0wUEjZNMs4IVy1V1Tbr6ZxirgeYTHJPPrv7wK6rUEn5MHH1Qcx9WawuHgiK0lH9GHGgWyv2qrohBDQ"/>
                    <div class="absolute bottom-0 left-0 right-0 p-8 bg-gradient-to-t from-black/80 via-black/40 to-transparent">
                        <div class="flex justify-between items-end">
                            <div>
                                <span class="text-[10px] font-black text-blue-300 uppercase tracking-[0.2em] px-3 py-1 bg-white/10 backdrop-blur-md border border-white/20 rounded-full mb-3 inline-block">SKU: DRILL-P4-BLUE-99</span>
                                <h3 class="text-white text-3xl font-black tracking-tight leading-tight">Industrial Power Drill - Gen 4</h3>
                            </div>
                            <div class="text-right">
                                <p class="text-blue-200 text-[10px] uppercase font-black tracking-widest mb-1">System Qty</p>
                                <p class="text-white text-5xl font-black leading-none tracking-tighter">42</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Interaction Area -->
                <div class="p-10 flex-1 flex flex-col justify-center items-center gap-12">
                    <div class="w-full text-center">
                        <label class="text-xs font-black text-slate-400 uppercase tracking-[0.3em] mb-6 block">Actual Physical Count</label>
                        <div class="flex items-center justify-center gap-10">
                            <button wire:click="decrementQty" 
                                    class="w-24 h-24 rounded-3xl bg-surface-container-high text-on-surface hover:bg-slate-200 active:scale-90 transition-all flex items-center justify-center shadow-lg border-b-4 border-slate-300">
                                <span class="material-symbols-outlined text-4xl font-bold">remove</span>
                            </button>
                            <input wire:model="actualQty" 
                                   class="w-48 text-center text-[10rem] font-black text-primary border-none focus:ring-0 p-0 leading-none bg-transparent" 
                                   type="number"/>
                            <button wire:click="incrementQty" 
                                    class="w-24 h-24 rounded-3xl bg-surface-container-high text-on-surface hover:bg-slate-200 active:scale-90 transition-all flex items-center justify-center shadow-lg border-b-4 border-slate-300">
                                <span class="material-symbols-outlined text-4xl font-bold">add</span>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Footer Action Buttons -->
                <div class="p-8 bg-slate-50 flex gap-4 border-t-2 border-slate-100">
                    <button class="flex-1 py-6 bg-white border-2 border-slate-200 text-slate-500 font-black text-sm uppercase tracking-widest rounded-3xl hover:bg-slate-100 transition-all flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-xl">report</span>
                        ReportIssue
                    </button>
                    <button wire:click="saveItem" 
                            class="flex-[2] py-6 bg-gradient-to-br from-primary to-primary-container text-white font-black text-xl uppercase tracking-widest rounded-3xl shadow-xl shadow-primary/20 hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-3 group">
                        SAVE / NEXT ITEM
                        <span class="material-symbols-outlined text-3xl font-black transition-transform group-hover:translate-x-2">arrow_forward</span>
                    </button>
                </div>
            </div>
        </section>

        <!-- Right Column: Batch Progress & Completed Items -->
        <section class="col-span-12 lg:col-span-4 flex flex-col gap-6 mobile-column overflow-hidden h-full">
            <div class="bg-surface-container-low rounded-3xl p-8 flex flex-col h-full overflow-hidden shadow-sm border border-slate-200/50">
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h2 class="text-xl font-black text-on-surface uppercase tracking-tight">Completed</h2>
                        <p class="text-[10px] text-primary font-black uppercase tracking-widest">Bin A-12-04 History</p>
                    </div>
                    <div class="text-right">
                        <p class="text-3xl font-black text-primary tracking-tighter">5/12</p>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Items Verified</p>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto pr-2 space-y-4 custom-scroll">
                    <!-- Item Row: Match -->
                    <div class="bg-white rounded-2xl p-4 flex items-center gap-4 border-2 border-transparent hover:border-primary/10 transition-all shadow-sm">
                        <div class="w-16 h-16 rounded-xl bg-slate-50 overflow-hidden shrink-0 border border-slate-100 p-1">
                            <img alt="Steel Bolts" class="w-full h-full object-cover rounded-lg" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCdou-G5_qpuTRWzcdDkETtNDgfHDxOJir8DKMsfRFLpoq9vu6fXm3rSHsKzfb95aeVYAXcjFezQysLukKFe7wY7gCLXD83yHosUde_po-yrtYM5-0kHQjG0_Hae-fGeMT_NHrzv1XyERMVuvW9DScVgmxkXde75IZ7hsCyrg7sxvdyC4gpxpW5LHoeSMkGa05abLPRgPW0Nx1AJcTsFZ_TvtbAhuyto-Bc9AuG5Sh-LfIY6v-iT4Uspi33-ZTmeihd7XHK9WhwMPw"/>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-black text-on-surface truncate uppercase tracking-tight leading-tight">M12 Steel Bolts (P50)</h4>
                            <p class="text-[10px] text-slate-400 font-bold tracking-tight mt-1">SKU: BT-1209-X</p>
                        </div>
                        <div class="text-right flex flex-col items-end gap-1 shrink-0">
                            <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[10px] font-black uppercase rounded-lg tracking-wider">Match</span>
                            <div class="flex items-center gap-2 text-xs">
                                <span class="text-slate-400">Sys: 120</span>
                                <span class="font-black text-slate-900">Act: 120</span>
                            </div>
                        </div>
                    </div>

                    <!-- Item Row: Shortage -->
                    <div class="bg-white rounded-2xl p-4 flex items-center gap-4 border-l-8 border-error shadow-sm hover:translate-x-1 transition-all">
                        <div class="w-16 h-16 rounded-xl bg-slate-50 overflow-hidden shrink-0 border border-slate-100 p-1">
                            <img alt="Welding Mask" class="w-full h-full object-cover rounded-lg" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDgsDQIVCtAgnw7UxvXeRqD8XI4ttITKaEkR9lhYzuNiJvJk3NhUKGbL1sRqiJE0KGUcp6pjpT2hitKw1lwAETMbL-CpaWr7vyPcbCNhkUmJln2tpdioPUgkqcXJ0wmWmDx5ke7XgJtTiX-6FLUjiZ6W0XEaapfHnTRgMI5no0UXzz55EdGq12iImZ7IvzjB_jReM0GNLioGXzgDgZtWJj_Utc0pUAWqKr01LjyOKUUp_fcKafltaKTlY1sD69_qHljWnHIW8a953k"/>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-black text-on-surface truncate uppercase tracking-tight leading-tight">Auto-Darkening Mask</h4>
                            <p class="text-[10px] text-slate-400 font-bold tracking-tight mt-1">SKU: WM-PRO-88</p>
                        </div>
                        <div class="text-right flex flex-col items-end gap-1 shrink-0">
                            <span class="px-2 py-0.5 bg-error-container text-on-error-container text-[10px] font-black uppercase rounded-lg tracking-wider">-2 Short</span>
                            <div class="flex items-center gap-2 text-xs">
                                <span class="text-slate-400">Sys: 15</span>
                                <span class="font-black text-error">Act: 13</span>
                            </div>
                        </div>
                    </div>

                    <!-- Item Row: Excess -->
                    <div class="bg-white rounded-2xl p-4 flex items-center gap-4 border-l-8 border-tertiary shadow-sm hover:translate-x-1 transition-all">
                        <div class="w-16 h-16 rounded-xl bg-slate-50 overflow-hidden shrink-0 border border-slate-100 p-1">
                            <img alt="Safety Helmet" class="w-full h-full object-cover rounded-lg" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDRJkIzqQvN8pFgxBUof8-Nt_qkw8TiqUDfD4vpVU1OGNvTTTNfbhtCbkdcmtdn0pvSnx0YavjLEH-nMy0LhzH7kbVDJnCxVId7GTeHtY8v6D4LeMyOlfMUfIpGd3A2WjPoizRiImZv-0r9bNmgz0U70t1pIxCgncWXXD2gAzgxXbHYKk0SK2eaCXZbwSPxoMe8Xgpxdr2t1fjEZ_VuqC6nfCJfl8P1TLKx4guDvmwvltpyT-q3pBPuQ7JVr1TdvsDhLime0SuOWZk"/>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-black text-on-surface truncate uppercase tracking-tight leading-tight">V-Gard Helmet</h4>
                            <p class="text-[10px] text-slate-400 font-bold tracking-tight mt-1">SKU: SH-YEL-01</p>
                        </div>
                        <div class="text-right flex flex-col items-end gap-1 shrink-0">
                            <span class="px-2 py-0.5 bg-tertiary-fixed text-on-tertiary-fixed-variant text-[10px] font-black uppercase rounded-lg tracking-wider">+12 Excess</span>
                            <div class="flex items-center gap-2 text-xs">
                                <span class="text-slate-400">Sys: 50</span>
                                <span class="font-black text-tertiary">Act: 62</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="pt-8 mt-6 border-t-2 border-slate-100">
                    <button class="w-full py-5 bg-white text-primary border-2 border-primary/20 font-black text-xs uppercase tracking-widest rounded-2xl hover:bg-primary/5 transition-all flex items-center justify-center gap-3">
                        <span class="material-symbols-outlined text-xl">file_download</span>
                        Export Session Report
                    </button>
                </div>
            </div>
        </section>
    </main>
</div>
