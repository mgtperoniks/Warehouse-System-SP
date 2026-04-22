<div class="p-6 pt-24 min-h-screen bg-slate-100/50">
    <!-- Header Area -->
    <div class="flex items-center justify-between gap-4 mb-4 bg-white p-4 rounded-2xl border border-slate-200 industrial-shadow">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-slate-900 rounded-xl flex items-center justify-center text-green-400">
                <span class="material-symbols-outlined">table_chart</span>
            </div>
            <div>
                <h2 class="text-lg font-black text-slate-900 tracking-tighter uppercase leading-none">Bulk Import Terminal</h2>
                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-1">Ready for Data Stream</p>
            </div>
        </div>

        <!-- Compact Status -->
        <div class="flex-1 flex justify-center px-8" x-data="{ processing: @entangle('isProcessing') }">
            <template x-if="processing">
                <div class="flex items-center gap-3 px-4 py-2 bg-green-50 rounded-full border border-green-100">
                    <div class="w-3 h-3 border-2 border-green-200 border-t-green-600 rounded-full animate-spin"></div>
                    <span class="text-[9px] font-black text-green-600 uppercase tracking-widest">Processing Batch...</span>
                </div>
            </template>
            <template x-if="!processing && $wire.importResults">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                        <span class="text-[9px] font-black text-slate-600 uppercase tracking-widest">Success: <span class="text-green-600" x-text="$wire.importResults.success"></span></span>
                    </div>
                    <template x-if="$wire.importResults.rejected > 0">
                        <div class="flex items-center gap-2 group relative">
                            <span class="w-2 h-2 rounded-full bg-red-500"></span>
                            <span class="text-[9px] font-black text-slate-600 uppercase tracking-widest cursor-help underline decoration-dotted">Rejected: <span class="text-red-600" x-text="$wire.importResults.rejected"></span></span>
                            <!-- Tooltip for errors -->
                            <div class="absolute top-full left-0 mt-2 w-64 bg-white border border-red-100 rounded-xl shadow-xl p-3 z-50 hidden group-hover:block transition-all italic">
                                <p class="text-[8px] font-black text-red-600 uppercase mb-2">Error Log:</p>
                                <div class="max-h-32 overflow-y-auto space-y-1">
                                    <template x-for="detail in $wire.importResults.details">
                                        <p class="text-[8px] font-bold text-slate-500 uppercase border-b border-slate-50 pb-1">
                                            R<span x-text="detail.row"></span>: <span x-text="detail.erp_code"></span> - <span x-text="detail.reason"></span>
                                        </p>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>
        
        <div class="flex items-center gap-2">
            <a href="{{ route('items') }}" class="px-4 py-2 text-[10px] font-black text-slate-400 uppercase tracking-widest hover:text-slate-600 transition-all">
                Cancel
            </a>
            <button 
                id="saveButton"
                class="px-5 py-2 text-[10px] font-black text-white green-action-gradient rounded-xl shadow-lg shadow-green-200 active:scale-95 transition-all disabled:opacity-50"
                onclick="processImport()"
            >
                EXECUTE STREAM
            </button>
        </div>
    </div>

    <!-- Spreadsheet Area -->
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden industrial-shadow">
        <div id="hot-container" class="w-full"></div>
    </div>

    <!-- Footer Guide -->
    <div class="mt-3 flex items-center justify-between px-4">
        <p class="text-[8px] font-bold text-slate-400 uppercase tracking-widest">
            Mode: Write-Only / Batch 100 / ERP Unique Constraint Active
        </p>
        <p class="text-[8px] font-black text-primary uppercase tracking-widest">
            Column Index: Name(0), ERP(1), SKU(2), Unit(3), Brand(4), Supplier(5), Bin(6), Stock(7), Price(8), Desc(9), Barcode(10)
        </p>
    </div>

    <!-- Handsontable Assets -->
    <link rel="stylesheet" href="{{ asset('vendor/handsontable/handsontable.full.min.css') }}" />
    <script src="{{ asset('vendor/handsontable/handsontable.full.min.js') }}"></script>

    <script>
        let hot;

        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('#hot-container');
            
            hot = new Handsontable(container, {
                data: Array.from({ length: 30 }, () => ['', '', '', 'PCS', '', '', '', '', '', '', '']),
                colHeaders: [
                    'NAME', 'ERP', 'SKU', 'UNIT', 'BRAND', 
                    'SUPPLIER', 'BIN', 'STOCK', 'PRICE', 'DESC', 'BARCODE'
                ],
                columns: [
                    { width: 220 }, // Name
                    { width: 100 }, // ERP
                    { width: 100 }, // SKU
                    { width: 60 },  // Unit
                    { width: 100 }, // Brand
                    { width: 120 }, // Supplier
                    { width: 80 },  // Bin
                    { width: 60, type: 'numeric' }, // Stock
                    { width: 80, type: 'numeric' }, // Price
                    { width: 180 }, // Desc
                    { width: 120 }, // Barcode
                ],
                rowHeaders: true,
                height: 'calc(100vh - 220px)',
                width: '100%',
                stretchH: 'all',
                licenseKey: 'non-commercial-and-evaluation',
                contextMenu: true,
                minSpareRows: 5,
                minRows: 30, // Ensure at least 30 rows are shown
                renderAllRows: false,
                autoWrapRow: true,
                manualColumnResize: true,
            });
        });

        function processImport() {
            const data = hot.getData().filter(row => row[0] || row[1]); 
            
            if (data.length === 0) {
                alert('No data to stream.');
                return;
            }

            if (confirm(`Authorize streaming ${data.length} records?`)) {
                @this.call('saveItems', data);
            }
        }
        
        window.addEventListener('importCompleted', event => {
            const results = event.detail[0];
            if (results.rejected === 0) {
                hot.loadData(Array.from({ length: 30 }, () => ['', '', '', 'PCS', '', '', '', '', '', '', '']));
            }
        });
    </script>

    <style>
        .handsontable th, .handsontable td {
            font-family: 'Inter', sans-serif !important;
            font-size: 10px !important;
            font-weight: 400 !important; /* Thinner font */
            border-color: #f1f3f5 !important;
        }
        .handsontable th {
            background-color: #f8fafc !important;
            color: #64748b !important;
            font-weight: 500 !important; /* Thinner header */
            text-transform: uppercase !important;
            letter-spacing: 0.1em !important;
            padding: 6px !important;
        }
        .handsontable .relative .rowHeader {
            font-weight: 400 !important; /* Thinner row numbers */
            color: #94a3b8 !important;
        }
        .handsontable td {
            padding: 4px 8px !important;
            color: #475569 !important;
        }
        .handsontable tr:hover td {
            background-color: #f8fafc !important;
        }
        .htMenu {
            z-index: 100 !important;
        }
    </style>
</div>
