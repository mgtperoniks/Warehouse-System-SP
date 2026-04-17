<div>
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <!-- Left Column: Data Entry -->
        <div class="col-span-1 lg:col-span-7 space-y-6">
            
            <!-- Basic Info Card -->
            <div class="bg-surface-container-lowest rounded-3xl p-8 border border-slate-200 shadow-sm border-t-4 border-primary">
                <h3 class="text-xl font-bold text-on-surface mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">feed</span>
                    Basic Identity
                </h3>
                
                <div class="space-y-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Item Name <span class="text-error">*</span></label>
                        <input wire:model="name" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary font-bold text-on-surface transition-all" placeholder="e.g. Servo Motor AX-12">
                        @error('name') <span class="text-error text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">ERP Code</label>
                            <input wire:model="erp_code" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary font-mono font-bold text-slate-700 transition-all uppercase" placeholder="ERP-XXXX">
                            @error('erp_code') <span class="text-error text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Internal SKU</label>
                            <input wire:model="sku" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary font-mono font-bold text-slate-700 transition-all uppercase" placeholder="SKU-XXXX">
                            @error('sku') <span class="text-error text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Brand/Manufacturer</label>
                            <input wire:model="brand" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary font-bold text-on-surface transition-all" placeholder="Brand Name">
                            @error('brand') <span class="text-error text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Stock Unit</label>
                            <input wire:model="unit" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary font-bold text-on-surface transition-all" placeholder="PCS, SET, ROLE...">
                            @error('unit') <span class="text-error text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Technical Description</label>
                        <textarea wire:model="description" rows="3" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary text-sm transition-all" placeholder="Detailed technical specifications or notes..."></textarea>
                        @error('description') <span class="text-error text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <!-- Barcodes Card -->
            <div class="bg-surface-container-lowest rounded-3xl p-8 border border-slate-200 shadow-sm border-l-4 border-emerald-500">
                <h3 class="text-xl font-bold text-on-surface mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined text-emerald-500">qr_code_scanner</span>
                    Master Barcodes
                </h3>
                
                <div class="flex gap-3 mb-6">
                    <div class="flex-1 relative">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">barcode_scanner</span>
                        <input wire:model="newBarcode" wire:keydown.enter="addBarcode" type="text" class="w-full pl-12 pr-4 bg-slate-50 border border-slate-200 rounded-xl py-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 font-mono font-bold transition-all" placeholder="Scan or type barcode...">
                    </div>
                    <button wire:click="addBarcode" class="bg-slate-200 text-slate-700 hover:bg-emerald-500 hover:text-white px-6 rounded-xl font-bold transition-all active:scale-95 flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">add</span> Add
                    </button>
                </div>
                @error('newBarcode') <span class="text-error text-xs font-bold mb-4 block">{{ $message }}</span> @enderror

                @if(empty($barcodes))
                    <div class="text-center py-8 border-2 border-dashed border-slate-200 rounded-xl text-slate-400">
                        <span class="material-symbols-outlined text-4xl mb-2">qr_code</span>
                        <p class="text-sm font-bold">No barcodes recorded.</p>
                        <p class="text-xs mt-1">Scan physical barcodes so this item can be found during inbound/outbound.</p>
                    </div>
                @else
                    <div class="space-y-2">
                        @foreach($barcodes as $i => $bc)
                            <div class="flex items-center justify-between p-3 rounded-xl border {{ $i === $primaryBarcodeIndex ? 'bg-emerald-50 border-emerald-200' : 'bg-white border-slate-200' }}">
                                <div class="flex items-center gap-3">
                                    <button wire:click="setPrimaryBarcode({{ $i }})" class="w-6 h-6 rounded-full border-2 flex items-center justify-center {{ $i === $primaryBarcodeIndex ? 'border-emerald-500 text-emerald-500' : 'border-slate-300 text-transparent hover:border-emerald-500 transition-colors' }}">
                                        <span class="material-symbols-outlined text-[14px] {{ $i === $primaryBarcodeIndex ? 'opacity-100' : 'opacity-0' }}" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                    </button>
                                    <span class="font-mono font-bold text-slate-700">{{ $bc['code'] }}</span>
                                    @if($i === $primaryBarcodeIndex)
                                        <span class="text-[10px] font-black uppercase text-emerald-600 bg-emerald-100 px-2 py-0.5 rounded-lg tracking-widest">Primary</span>
                                    @endif
                                </div>
                                <button wire:click="removeBarcode({{ $i }})" class="text-slate-400 hover:text-red-500 transition-colors">
                                    <span class="material-symbols-outlined text-[20px]">delete</span>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            
            <div class="flex justify-end pt-4 pb-12 lg:pb-0">
                <button wire:click="save" wire:loading.attr="disabled" class="bg-primary hover:bg-primary-fixed-variant text-white px-10 py-4 rounded-2xl font-black text-lg shadow-lg shadow-primary/20 hover:shadow-primary/40 focus:ring-4 focus:ring-primary/30 transition-all active:scale-95 flex items-center gap-3 w-full lg:w-auto justify-center">
                    <span wire:loading.remove wire:target="save" class="material-symbols-outlined">save</span>
                    <span wire:loading wire:target="save" class="material-symbols-outlined animate-spin">progress_activity</span>
                    SAVE ITEM CONFIGURATION
                </button>
            </div>
            
        </div>

        <!-- Right Column: Image Assets Management -->
        <div class="col-span-1 lg:col-span-5 space-y-6">
            
            <!-- Upload Dropzone Area Based on User CSS Request -->
            <div class="bg-surface-container-lowest rounded-3xl p-6 border-2 border-dashed border-slate-300 flex flex-col items-center justify-center text-center group hover:border-primary hover:bg-slate-50 transition-colors relative" id="photo_dropzone">
                <input type="file" wire:model="photos" multiple accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" />
                
                <div class="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-primary text-3xl">cloud_upload</span>
                </div>
                <h3 class="text-lg font-black text-on-surface">Drag and drop item photos</h3>
                <p class="text-sm text-slate-500 mt-2 max-w-sm">Support for JPEG & PNG. Used for visual inventory verification.</p>
                
                <div class="mt-6 flex gap-3 z-0 relative">
                    <span class="px-3 py-1.5 bg-slate-100 rounded-lg text-xs font-bold text-slate-500 uppercase tracking-widest hidden sm:inline-block">Max: 25MB</span>
                    <span class="px-3 py-1.5 bg-slate-100 rounded-lg text-xs font-bold text-slate-500 uppercase tracking-widest">Recommended: 1:1</span>
                </div>
                
                <div wire:loading wire:target="photos" class="absolute inset-0 bg-white/80 backdrop-blur-sm z-20 flex flex-col items-center justify-center rounded-[calc(1.5rem-2px)]">
                    <span class="material-symbols-outlined animate-spin text-primary text-4xl mb-2">progress_activity</span>
                    <p class="font-bold text-slate-600">Uploading processing...</p>
                </div>
            </div>

            @error('photos.*') <span class="text-error text-xs font-bold mt-1 block">{{ $message }}</span> @enderror

            <!-- Existing Gallery Grid -->
            @if(!empty($existingPhotos) || !empty($photos))
            <div class="bg-surface-container-lowest rounded-3xl p-6 border border-slate-200 shadow-sm">
                <h3 class="font-black text-on-surface mb-4 flex items-center justify-between">
                    <span>Visual Reference Gallery</span>
                    <span class="text-xs font-bold bg-slate-100 px-2 py-1 rounded text-slate-500 uppercase tracking-widest">{{ count($existingPhotos) + count($photos) }} Media</span>
                </h3>
                
                <div class="grid grid-cols-2 gap-4">
                    <!-- New Uploads Previews -->
                    @foreach($photos as $index => $photo)
                    <div class="group relative bg-white rounded-2xl overflow-hidden aspect-square shadow-sm border-2 border-primary">
                        <img class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" src="{{ $photo->temporaryUrl() }}" />
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                            <button wire:click.prevent="removeUploadedPhoto({{ $index }})" class="bg-red-500/90 backdrop-blur-md p-2 rounded-full text-white hover:bg-red-600 shadow-lg transition-transform hover:scale-110 active:scale-95">
                                <span class="material-symbols-outlined text-[20px]">delete</span>
                            </button>
                        </div>
                        <div class="absolute top-2 left-2 bg-primary text-white text-[10px] font-black uppercase px-2 py-1 rounded-md shadow-sm">Pending Upload</div>
                    </div>
                    @endforeach

                    <!-- Existing Saved Images -->
                    @foreach($existingPhotos as $index => $image)
                    <div class="group relative bg-white rounded-2xl overflow-hidden aspect-square shadow-sm border border-slate-200">
                        <img class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 opacity-90" src="{{ asset('storage/' . $image['path']) }}" />
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                            <!-- In a full implementation we could have a set primary hook here too -->
                            <button wire:click.prevent="removeExistingPhoto({{ $image['id'] }})" class="bg-red-500/90 backdrop-blur-md p-2 rounded-full text-white hover:bg-red-600 shadow-lg transition-transform hover:scale-110 active:scale-95" onclick="return confirm('Are you sure you want to delete this image permanently?')">
                                <span class="material-symbols-outlined text-[20px]">delete</span>
                            </button>
                        </div>
                        @if($image['is_primary'])
                            <div class="absolute top-2 left-2 bg-slate-800 text-white text-[10px] font-black uppercase px-2 py-1 rounded-md shadow-sm">Main Display</div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Info Context -->
            <div class="bg-blue-50/50 rounded-2xl p-5 border border-blue-100/50">
                <div class="flex items-start gap-4">
                    <span class="material-symbols-outlined text-blue-500 text-3xl">policy</span>
                    <div>
                        <p class="text-sm font-black text-blue-800 mb-1">Upload Integrity Checking</p>
                        <p class="text-xs text-blue-600/80 leading-relaxed font-semibold">
                            Once items are confirmed and saved, the primary media will be synced permanently down to local scanners to ensure faster visual verification during stock opname.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
