<div class="p-md pt-14 min-h-screen bg-slate-100/30">
    <!-- Header Area -->
    <div class="flex items-center justify-between gap-sm mb-md bg-white dark:bg-slate-900 p-md rounded-md border border-slate-200 dark:border-slate-800 shadow-sm">
        <div class="flex items-center gap-3">
            <a href="{{ route('outstanding-purchases') }}" class="w-9 h-9 bg-slate-50 hover:bg-slate-100 border border-slate-200 dark:border-slate-800 rounded-md flex items-center justify-center text-slate-550 transition-all active:scale-95">
                <span class="material-symbols-outlined text-lg">arrow_back</span>
            </a>
            <div>
                <h2 class="text-sm font-black text-slate-900 tracking-tighter uppercase leading-none">PO Import Terminal</h2>
                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-1">Ready for Sync Stream</p>
            </div>
        </div>

        <!-- Excel File Upload -->
        <div class="flex items-center gap-3 bg-slate-50 dark:bg-slate-805/30 border border-slate-200 dark:border-slate-800 p-2 rounded-md">
            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Excel Pipeline:</span>
            <div x-data="{ uploading: false }" 
                 x-on:livewire-upload-start="uploading = true"
                 x-on:livewire-upload-finish="uploading = false"
                 x-on:livewire-upload-error="uploading = false"
                 class="flex items-center gap-2">
                
                <input type="file" wire:model="excelFile" class="hidden" id="excel-file-input" accept=".xlsx,.xls,.csv" />
                <button type="button" onclick="document.getElementById('excel-file-input').click()" 
                        class="h-9 px-3 border border-slate-200 dark:border-slate-800 bg-white hover:bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-200 rounded-md text-[9px] font-black uppercase tracking-widest flex items-center gap-2 active:scale-95 transition-all">
                    <span class="material-symbols-outlined text-sm">upload_file</span>
                    {{ $excelFile ? 'Change File' : 'Select Spreadsheet' }}
                </button>

                @if($excelFile)
                    <button type="button" wire:click="importExcel" 
                            class="h-9 px-4 bg-green-600 text-white rounded-md text-[9px] font-black uppercase tracking-widest flex items-center gap-1 active:scale-95 transition-all">
                        <span class="material-symbols-outlined text-sm">rocket_launch</span>
                        Sync Excel
                    </button>
                @endif

                <div x-show="uploading" class="text-[8px] font-black text-green-600 animate-pulse uppercase tracking-widest">
                    Uploading...
                </div>
            </div>
        </div>

        <!-- Compact Status -->
        <div class="flex justify-center px-4" x-data="{ processing: @entangle('isProcessing') }">
            <template x-if="processing">
                <div class="flex items-center gap-3 px-3 py-1.5 bg-green-50 rounded-sm border border-green-100">
                    <div class="w-3 h-3 border-2 border-green-200 border-t-green-600 rounded-full animate-spin"></div>
                    <span class="text-[9px] font-black text-green-600 uppercase tracking-widest">Processing Sync...</span>
                </div>
            </template>
            <template x-if="!processing && $wire.importResults">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                        <span class="text-[9px] font-black text-slate-600 uppercase tracking-widest">Success: <span class="text-green-600" x-text="$wire.importResults.success"></span></span>
                    </div>
                    <template x-if="$wire.importResults.failed > 0">
                        <div class="flex items-center gap-2 group relative">
                            <span class="w-2 h-2 rounded-full bg-red-500"></span>
                            <span class="text-[9px] font-black text-slate-600 uppercase tracking-widest cursor-help underline decoration-dotted">Errors: <span class="text-red-600" x-text="$wire.importResults.failed"></span></span>
                            <!-- Tooltip for errors -->
                            <div class="absolute top-full right-0 mt-2 w-72 bg-white border border-red-100 rounded-md shadow-xl p-3 z-50 hidden group-hover:block transition-all italic text-left">
                                <p class="text-[8px] font-black text-red-600 uppercase mb-2">Error Log:</p>
                                <div class="max-h-32 overflow-y-auto space-y-1">
                                    <template x-for="detail in $wire.importResults.errors">
                                        <p class="text-[8px] font-bold text-slate-500 uppercase border-b border-slate-50 pb-1">
                                            Row <span x-text="detail.row"></span>: PO <span x-text="detail.po_number"></span> (<span x-text="detail.erp_code"></span>) - <span x-text="detail.reason"></span>
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
            <a href="{{ route('outstanding-purchases') }}" class="h-11 px-4 flex items-center justify-center text-[10px] font-black text-slate-400 uppercase tracking-widest hover:text-slate-600 transition-all">
                Cancel
            </a>
            <button 
                id="saveButton"
                class="h-11 px-5 text-[10px] font-black text-white green-action-gradient rounded-md shadow-md shadow-green-200 active:scale-95 transition-all disabled:opacity-50 flex items-center justify-center"
                onclick="processImport()"
            >
                STREAM GRID DATA
            </button>
        </div>
    </div>

    <!-- Spreadsheet Area -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md overflow-hidden shadow-sm">
        <div id="hot-container" class="w-full"></div>
    </div>

    <!-- Footer Guide -->
    <div class="mt-3 flex items-center justify-between px-4">
        <p class="text-[8px] font-bold text-slate-400 uppercase tracking-widest">
            Mode: Write/Update / Deduplication Match Key: PO Number + ERP Code
        </p>
        <p class="text-[8px] font-black text-primary uppercase tracking-widest">
            Cols: SupplierCode(0), SupplierName(1), Reserved(2-3), PO Number(4), PO Date(5), ERP Code(6), Item Name(7), Base Unit(8), PO Qty(9)
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
                data: Array.from({ length: 30 }, () => ['', '', '', '', '', '', '', '', 'PCS', '', '', '', '', '']),
                colHeaders: [
                    'SUPPLIER CODE', 'SUPPLIER NAME', 'RESERVED (BLANK)', 'RESERVED (BLANK)',
                    'PO NUMBER', 'PO DATE (YYYY-MM-DD)', 'ERP CODE', 'ITEM NAME',
                    'BASE UNIT', 'PO QTY', 'RECEIVED QTY', 'CANCEL QTY', 'RETURN QTY', 'OUTSTANDING QTY'
                ],
                columns: [
                    { width: 110 }, // 0: Supplier Code
                    { width: 180 }, // 1: Supplier Name
                    { width: 120, readOnly: true }, // 2: Reserved
                    { width: 120, readOnly: true }, // 3: Reserved
                    { width: 110 }, // 4: PO Number
                    { width: 120 }, // 5: PO Date
                    { width: 100 }, // 6: ERP Code
                    { width: 200 }, // 7: Item Name
                    { width: 80 },  // 8: Base Unit
                    { width: 80, type: 'numeric' },  // 9: PO Qty
                    { width: 95, type: 'numeric', readOnly: true },  // 10: Received Qty
                    { width: 85, type: 'numeric', readOnly: true },  // 11: Cancel Qty
                    { width: 85, type: 'numeric', readOnly: true },  // 12: Return Qty
                    { width: 110, type: 'numeric', readOnly: true }  // 13: Outstanding Qty
                ],
                rowHeaders: true,
                height: 'calc(100vh - 280px)',
                width: '100%',
                stretchH: 'all',
                licenseKey: 'non-commercial-and-evaluation',
                contextMenu: true,
                minSpareRows: 5,
                minRows: 30,
                renderAllRows: false,
                autoWrapRow: true,
                manualColumnResize: true,
            });
        });

        function processImport() {
            // Must have PO number (4) or ERP code (6)
            const data = hot.getData().filter(row => row[4] || row[6]);
            
            if (data.length === 0) {
                alert('No data in the grid to stream.');
                return;
            }

            if (confirm(`Authorize streaming ${data.length} records into outstanding purchase ledger?`)) {
                @this.call('saveFromHandsontable', data);
            }
        }
        
        window.addEventListener('importCompleted', event => {
            const results = event.detail[0];
            if (results.failed === 0) {
                hot.loadData(Array.from({ length: 30 }, () => ['', '', '', '', '', '', '', '', 'PCS', '', '', '', '', '']));
                alert('Sync complete! Imported successfully.');
            } else {
                alert(`Sync completed with errors. Success: ${results.success}, Failed: ${results.failed}. Check logs for details.`);
            }
        });
    </script>

    <style>
        .handsontable th, .handsontable td {
            font-family: 'Inter', sans-serif !important;
            font-size: 10px !important;
            font-weight: 400 !important;
            border-color: #f1f3f5 !important;
        }
        .handsontable th {
            background-color: #f8fafc !important;
            color: #64748b !important;
            font-weight: 500 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.1em !important;
            padding: 6px !important;
        }
        .handsontable .relative .rowHeader {
            font-weight: 400 !important;
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
