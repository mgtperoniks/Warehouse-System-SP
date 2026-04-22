<div class="p-6 mt-16 min-h-screen bg-slate-50">
    <!-- Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h2 class="text-2xl font-black text-slate-900 tracking-tighter uppercase">Bulk Spreadsheet Import</h2>
            <p class="text-xs text-slate-500 font-bold uppercase tracking-widest mt-1">Industrial Data Entry Terminal</p>
        </div>
        
        <div class="flex items-center gap-3">
            <a href="{{ route('items') }}" class="px-4 py-2 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-all">
                Cancel
            </a>
            <button 
                id="saveButton"
                class="px-6 py-2 text-sm font-black text-white green-action-gradient rounded-xl shadow-lg shadow-green-200 active:scale-95 transition-all disabled:opacity-50"
                onclick="processImport()"
            >
                Execute Import
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Spreadsheet Sidebar/Instructions -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white/70 backdrop-blur-md border border-slate-200 p-5 rounded-3xl industrial-shadow">
                <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-4 border-b border-slate-100 pb-2">Status & Controls</h3>
                
                <div x-data="{ processing: @entangle('isProcessing') }">
                    <template x-if="processing">
                        <div class="flex flex-col items-center justify-center p-8">
                            <div class="w-12 h-12 border-4 border-green-100 border-t-green-600 rounded-full animate-spin"></div>
                            <p class="text-[10px] font-black text-green-600 uppercase mt-4 tracking-widest">Processing Data...</p>
                        </div>
                    </template>

                    <template x-if="!processing && $wire.importResults">
                        <div class="space-y-4">
                            <div class="p-4 bg-green-50 border border-green-100 rounded-2xl">
                                <p class="text-[10px] font-black text-green-600 uppercase tracking-widest">Success</p>
                                <p class="text-2xl font-black text-green-700" x-text="$wire.importResults.success"></p>
                            </div>
                            
                            <template x-if="$wire.importResults.rejected > 0">
                                <div class="p-4 bg-red-50 border border-red-100 rounded-2xl">
                                    <p class="text-[10px] font-black text-red-600 uppercase tracking-widest">Rejected (Duplicates/Errors)</p>
                                    <p class="text-2xl font-black text-red-700" x-text="$wire.importResults.rejected"></p>
                                    
                                    <div class="mt-2 max-h-40 overflow-y-auto space-y-1">
                                        <template x-for="detail in $wire.importResults.details">
                                            <p class="text-[9px] font-bold text-red-500 uppercase leading-tight">
                                                Row <span x-text="detail.row"></span>: <span x-text="detail.erp_code"></span> - <span x-text="detail.reason"></span>
                                            </p>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                    
                    <template x-if="!processing && !$wire.importResults">
                        <div class="text-center p-8 bg-slate-50 border border-dashed border-slate-200 rounded-2xl">
                            <span class="material-symbols-outlined text-slate-300 text-4xl mb-2">content_paste</span>
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Paste your spreadsheet data into the table on the right</p>
                        </div>
                    </template>
                </div>
            </div>

            <div class="bg-slate-900 p-5 rounded-3xl text-white">
                <h3 class="text-[10px] font-black text-green-400 uppercase tracking-widest mb-3">Schema Guide</h3>
                <ul class="space-y-2">
                    <li class="flex items-start gap-2">
                        <span class="text-[10px] font-black text-green-500">01</span>
                        <p class="text-[10px] font-bold text-slate-400 uppercase leading-tight">Mandatory: Name, ERP Code, SKU, Unit</p>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-[10px] font-black text-green-500">02</span>
                        <p class="text-[10px] font-bold text-slate-400 uppercase leading-tight">Optional: Brand, Supplier, Bin Code, Stock, Price, Description, Barcode</p>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-[10px] font-black text-green-500">03</span>
                        <p class="text-[10px] font-bold text-slate-400 uppercase leading-tight">Existing ERP Codes will be REJECTED to prevent data corruption.</p>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Spreadsheet Area -->
        <div class="lg:col-span-3">
            <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden industrial-shadow">
                <div id="hot-container" class="w-full"></div>
            </div>
            <p class="mt-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest px-4">
                Tip: Use Ctrl+V to paste data from Excel or Google Sheets.
            </p>
        </div>
    </div>

    <!-- Handsontable Assets -->
    <link rel="stylesheet" href="{{ asset('vendor/handsontable/handsontable.full.min.css') }}" />
    <script src="{{ asset('vendor/handsontable/handsontable.full.min.js') }}"></script>

    <script>
        let hot;

        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('#hot-container');
            
            hot = new Handsontable(container, {
                data: [
                    ['', '', '', 'PCS', '', '', '', '', '', '', '']
                ],
                colHeaders: [
                    'NAME', 'ERP CODE', 'SKU', 'UNIT', 'BRAND', 
                    'SUPPLIER', 'BIN CODE', 'STOCK', 'PRICE', 'DESC', 'BARCODE'
                ],
                columns: [
                    { width: 250 }, // Name
                    { width: 120 }, // ERP
                    { width: 120 }, // SKU
                    { width: 80 },  // Unit
                    { width: 120 }, // Brand
                    { width: 150 }, // Supplier
                    { width: 100 }, // Bin
                    { width: 80, type: 'numeric' }, // Stock
                    { width: 100, type: 'numeric' }, // Price
                    { width: 200 }, // Desc
                    { width: 150 }, // Barcode
                ],
                rowHeaders: true,
                height: 'calc(100vh - 350px)',
                width: '100%',
                stretchH: 'all',
                licenseKey: 'non-commercial-and-evaluation', // For testing/evaluation as per user request
                contextMenu: true,
                minSpareRows: 1,
                renderAllRows: false, // Performance for 6700 items
                autoWrapRow: true,
                manualColumnResize: true,
                dropdownMenu: true,
                filters: true,
            });
        });

        function processImport() {
            const data = hot.getData().filter(row => row[0] || row[1]); // Filter empty rows
            
            if (data.length === 0) {
                alert('No data to import.');
                return;
            }

            if (confirm(`Begin importing ${data.length} records?`)) {
                @this.call('saveItems', data).then(() => {
                    // Reset table on total success if desired, or keep to show errors
                });
            }
        }
        
        window.addEventListener('importCompleted', event => {
            const results = event.detail[0];
            if (results.rejected === 0) {
                hot.loadData([['', '', '', 'PCS', '', '', '', '', '', '', '']]);
            }
        });
    </script>

    <style>
        .handsontable th, .handsontable td {
            font-family: 'Inter', sans-serif !important;
            font-size: 11px !important;
            font-weight: 500 !important;
            border-color: #f1f3f5 !important;
        }
        .handsontable th {
            background-color: #f8fafc !important;
            color: #64748b !important;
            font-weight: 800 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            padding: 10px !important;
        }
        .handsontable td {
            padding: 8px !important;
            color: #1e293b !important;
        }
        .handsontable tr:hover td {
            background-color: #f1f5f9 !important;
        }
        .htMenu {
            z-index: 100 !important;
        }
    </style>
</div>
