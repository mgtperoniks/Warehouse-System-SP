<div>
    @if($isOpen)
    <div class="fixed inset-0 z-[100] flex items-center justify-center">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" wire:click="closeModal"></div>
        
        <!-- Modal Content -->
        <div class="bg-surface rounded-3xl shadow-2xl relative z-10 w-full max-w-lg mx-4 flex flex-col overflow-hidden animate-in fade-in zoom-in duration-200">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-white">
                <h3 class="text-xl font-black text-slate-800 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">upload_file</span>
                    Import Master Data
                </h3>
                <button wire:click="closeModal" class="text-slate-400 hover:bg-slate-100 p-2 rounded-full transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            
            <!-- Body -->
            <div class="p-8 bg-slate-50 flex-1">
                @if($status)
                    <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-6 text-center text-emerald-800">
                        <span class="material-symbols-outlined text-4xl mb-2 text-emerald-500">task_alt</span>
                        <h4 class="font-black text-lg mb-1">Queue Successful</h4>
                        <p class="text-sm font-medium">{{ $status }}</p>
                        <button wire:click="closeModal" class="mt-4 px-6 py-2 bg-emerald-600 text-white font-bold rounded-xl shadow-lg hover:bg-emerald-700 active:scale-95 transition-all">Done</button>
                    </div>
                @else
                    <div class="bg-white border-2 border-dashed border-primary/30 rounded-2xl p-8 flex flex-col items-center justify-center relative hover:bg-primary/5 hover:border-primary transition-colors group">
                        <input type="file" wire:model="file" accept=".csv" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" />
                        
                        <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                            <span class="material-symbols-outlined text-primary text-3xl">post_add</span>
                        </div>
                        <h4 class="text-lg font-black text-slate-700">Select CSV File</h4>
                        <p class="text-sm text-slate-500 text-center mt-2">
                            Drop an Excel-exported CSV here.<br>
                            <span class="text-xs font-bold text-slate-400 mt-2 block">Requires columns: name, erp_code, sku, brand, unit, description, barcode, supplier_name</span>
                        </p>
                        
                        <div wire:loading wire:target="file" class="mt-4 flex items-center gap-2 bg-white px-4 py-2 rounded-full shadow border border-slate-100 absolute bottom-4">
                            <span class="material-symbols-outlined animate-spin text-primary text-sm">progress_activity</span>
                            <span class="text-xs font-bold text-slate-600">Uploading file...</span>
                        </div>
                    </div>

                    @error('file') <span class="block mt-3 text-error text-sm font-bold text-center">{{ $message }}</span> @enderror

                    @if($file && !$errors->has('file'))
                        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-center justify-between">
                            <div class="flex items-center gap-3 overflow-hidden">
                                <span class="material-symbols-outlined text-blue-500 shrink-0">draft</span>
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-slate-700 truncate">{{ $file->getClientOriginalName() }}</p>
                                    <p class="text-[10px] uppercase font-black tracking-widest text-blue-500 mt-0.5">{{ number_format($file->getSize() / 1024, 1) }} KB</p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 flex gap-3">
                            <button wire:click="closeModal" class="flex-1 py-3 bg-white border border-slate-200 text-slate-600 font-bold rounded-xl hover:bg-slate-50 active:scale-95 transition-all">Cancel</button>
                            <button wire:click="startImport" class="flex-1 py-3 bg-primary text-white font-bold rounded-xl shadow-lg shadow-primary/20 hover:bg-primary-fixed-variant active:scale-95 transition-all flex items-center justify-center gap-2">
                                <span wire:loading.remove wire:target="startImport" class="material-symbols-outlined text-sm">rocket_launch</span>
                                <span wire:loading wire:target="startImport" class="material-symbols-outlined animate-spin text-sm">progress_activity</span>
                                START IMPORT
                            </button>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
    @endif
</div>
