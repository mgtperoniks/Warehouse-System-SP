<div>
    <!-- Cropper.js Assets -->
    <link rel="stylesheet" href="{{ asset('assets/css/cropper.min.css') }}" />
    <script src="{{ asset('assets/js/cropper.min.js') }}"></script>

    <!-- Alpine Data Wrap -->
    <div x-data="{ 
            cropping: false,
            processing: false,
            editingIndex: null,
            editingType: 'new',
            
            openCropper: function(type, index, url) {
                var self = this;
                if (typeof Cropper === 'undefined') {
                    alert('Error: Cropper.js library missing.');
                    return;
                }

                this.editingType = type;
                this.editingIndex = index;
                this.cropping = true;
                this.processing = true;
                
                this.$nextTick(function() {
                    var image = self.$refs.cropperImage;
                    if (!image) return;

                    if (image._cropper) {
                        image._cropper.destroy();
                        image._cropper = null;
                    }

                    function startEngine() {
                        if (image._cropper) return; // Prevent double initialization
                        image._cropper = new Cropper(image, {
                            aspectRatio: 1,
                            viewMode: 2,
                            dragMode: 'move',
                            checkOrientation: true,
                            autoCropArea: 1,
                            minContainerWidth: 250,
                            minContainerHeight: 250,
                            ready: function() {
                                self.processing = false;
                            }
                        });
                    }

                    image.onload = startEngine;
                    
                    // CRITICAL CORS FIX: Force URL to be strictly relative to the current IP/Hostname
                    var parser = document.createElement('a');
                    parser.href = url;
                    image.src = parser.pathname + parser.search;

                    if (image.complete) startEngine();
                });
            },
            
            saveCrop: function() {
                var self = this;
                var cropper = this.$refs.cropperImage ? this.$refs.cropperImage._cropper : null;
                if (!cropper || this.editingIndex === null) return;
                
                this.processing = true;
                try {
                    cropper.getCroppedCanvas({
                        maxWidth: 1200,
                        maxHeight: 1200,
                        imageSmoothingQuality: 'high'
                    }).toBlob(function(blob) {
                        try {
                            if (!blob) throw new Error('Image buffer is empty');
                            
                            // Essential: Convert Blob to File for Livewire compatibility
                            var file = new File([blob], 'crop_' + Date.now() + '.jpg', { type: 'image/jpeg' });

                            if (self.editingType === 'new') {
                                @this.upload('photos.' + self.editingIndex, file, 
                                    function(uploadedFilename) {
                                        self.cropping = false;
                                        self.processing = false;
                                        self.editingIndex = null;
                                    }, 
                                    function() {
                                        alert('Upload failed. Connection might be unstable.');
                                        self.processing = false;
                                    }
                                );
                            } else {
                                @this.upload('croppedExistingPhoto', file, 
                                    function(uploadedFilename) {
                                        @this.applyExistingCrop(self.editingIndex).then(function() {
                                            self.cropping = false;
                                            self.processing = false;
                                            self.editingIndex = null;
                                        }).catch(function(err) {
                                            alert('Server rejected the target crop update.');
                                            self.processing = false;
                                        });
                                    }, 
                                    function() {
                                        alert('Upload failed. Connection might be unstable.');
                                        self.processing = false;
                                    }
                                );
                            }
                        } catch (e) {
                            alert('Blob processing failed: ' + e.message);
                            self.processing = false;
                        }
                    }, 'image/jpeg', 0.85);
                } catch (e) {
                    alert('CRITICAL ERROR: ' + e.name + ' - ' + e.message);
                    console.error('CROPPER EXCEPTION:', e);
                    this.processing = false;
                }
            }
         }">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-md items-start">
        
        <!-- Left Column: Data Entry -->
        <div class="col-span-1 lg:col-span-7 space-y-md">
            
            <!-- Basic Info Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md p-md shadow-sm">
                <h3 class="text-sm font-black text-on-surface mb-md flex items-center gap-2 uppercase tracking-wide">
                    <span class="material-symbols-outlined text-primary text-xl">feed</span>
                    Basic Identity
                </h3>
                
                <div class="space-y-sm">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Item Name <span class="text-error">*</span></label>
                        <input wire:model="name" type="text" class="w-full h-11 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md px-4 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary font-bold text-on-surface transition-all text-sm" placeholder="e.g. Servo Motor AX-12">
                        @error('name') <span class="text-error text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-sm">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">ERP Code</label>
                            <input wire:model.live="erp_code" type="text" class="w-full h-11 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md px-4 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary font-mono font-bold text-slate-700 transition-all uppercase text-sm" placeholder="ERP-XXXX">
                            @error('erp_code') <span class="text-error text-xs font-bold mt-1 block">{{ $message }}</span> @enderror

                            @if($erpFamily)
                                <div class="mt-2 bg-emerald-50/50 dark:bg-emerald-950/15 border border-emerald-100 dark:border-emerald-900/60 p-3 rounded-lg flex flex-col gap-1.5 text-xs text-slate-700 dark:text-slate-350">
                                    <div class="flex justify-between items-center">
                                        <span class="font-bold uppercase tracking-wider text-[9px] text-slate-450">ERP Family</span>
                                        <span class="font-mono font-black text-emerald-700 dark:text-emerald-450">{{ $erpFamily }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="font-bold uppercase tracking-wider text-[9px] text-slate-450">Last Barcode</span>
                                        <span class="font-mono font-bold">{{ $lastBarcode }}</span>
                                    </div>
                                    <div class="flex justify-between items-center border-t border-emerald-100/50 dark:border-emerald-900/40 pt-1.5 mt-0.5">
                                        <span class="font-bold uppercase tracking-wider text-[9px] text-emerald-600 dark:text-emerald-400">Suggested</span>
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-black text-emerald-600 dark:text-emerald-400">{{ $suggestedBarcode }}</span>
                                            <button type="button" wire:click="$set('newBarcode', '{{ $suggestedBarcode }}')" class="px-1.5 py-0.5 bg-emerald-500 hover:bg-emerald-600 text-white rounded text-[8px] font-black uppercase tracking-widest transition-all">Use</button>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Internal SKU</label>
                            <input wire:model="sku" type="text" class="w-full h-11 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md px-4 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary font-mono font-bold text-slate-700 transition-all uppercase text-sm" placeholder="SKU-XXXX">
                            @error('sku') <span class="text-error text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-sm">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Brand/Manufacturer</label>
                            <input wire:model="brand" type="text" class="w-full h-11 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md px-4 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary font-bold text-on-surface transition-all text-sm" placeholder="Brand Name">
                            @error('brand') <span class="text-error text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Stock Unit</label>
                            <input wire:model="unit" type="text" class="w-full h-11 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md px-4 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary font-bold text-on-surface transition-all text-sm" placeholder="PCS, SET, ROLE...">
                            @error('unit') <span class="text-error text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Technical Description</label>
                        <textarea wire:model="description" rows="3" class="w-full bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md px-4 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm transition-all" placeholder="Detailed technical specifications or notes..."></textarea>
                        @error('description') <span class="text-error text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <!-- Storage & Inventory Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md p-md shadow-sm">
                <h3 class="text-sm font-black text-on-surface mb-md flex items-center gap-2 uppercase tracking-wide">
                    <span class="material-symbols-outlined text-blue-500 text-xl">inventory_2</span>
                    Storage & Inventory
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-sm">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Bin Location Code</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-lg">location_on</span>
                            <input wire:model="bin_code" type="text" class="w-full h-11 pl-10 pr-4 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 font-mono font-bold transition-all uppercase text-sm" placeholder="e.g. A-01-05">
                        </div>
                        @error('bin_code') <span class="text-error text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        <p class="text-[9px] text-slate-400 mt-1 font-bold italic">Update this whenever the item's primary storage spot changes.</p>
                    </div>

                    @if($mode === 'create')
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Initial Stock Quantity</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-lg">box_add</span>
                            <input wire:model="initial_stock" type="number" class="w-full h-11 pl-10 pr-4 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 font-bold transition-all text-sm" placeholder="0">
                        </div>
                        @error('initial_stock') <span class="text-error text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        <p class="text-[9px] text-slate-400 mt-1 font-bold italic">This can only be set during initial registration.</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Inventory Planning Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md p-md shadow-sm">
                <h3 class="text-sm font-black text-on-surface mb-md flex items-center gap-2 uppercase tracking-wide">
                    <span class="material-symbols-outlined text-purple-500 text-xl">assignment</span>
                    Inventory Planning
                </h3>
                
                <div class="space-y-sm">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-sm">
                        <!-- Procurement Type -->
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Procurement</label>
                            <div class="flex items-center gap-6 mt-2">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" wire:model="procurement_type" value="LOCAL" class="w-4 h-4 text-primary border-slate-350 focus:ring-primary/20">
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Local</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" wire:model="procurement_type" value="IMPORT" class="w-4 h-4 text-primary border-slate-350 focus:ring-primary/20">
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Import</span>
                                </label>
                            </div>
                            @error('procurement_type') <span class="text-error text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Inventory Class -->
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Inventory Class</label>
                            <div class="flex items-center gap-6 mt-2">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" wire:model="inventory_class" value="CONSUMABLE" class="w-4 h-4 text-primary border-slate-350 focus:ring-primary/20">
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Consumable</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" wire:model="inventory_class" value="SPAREPART" class="w-4 h-4 text-primary border-slate-350 focus:ring-primary/20">
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Sparepart</span>
                                </label>
                            </div>
                            @error('inventory_class') <span class="text-error text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Lead Time Days -->
                    <div class="pt-2">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Lead Time</label>
                        <div class="flex items-center gap-2">
                            <div class="relative w-32">
                                <input wire:model="lead_time_days" type="number" class="w-full h-11 pr-12 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md px-4 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary font-bold text-on-surface transition-all text-sm" placeholder="30">
                                <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400 pointer-events-none">Days</span>
                            </div>
                        </div>
                        @error('lead_time_days') <span class="text-error text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        <p class="text-[9px] text-slate-400 mt-1.5 font-bold italic">Estimated procurement lead time from Purchase Order until material arrives at warehouse.</p>
                    </div>
                </div>
            </div>

            <!-- Barcodes Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md p-md shadow-sm">
                <h3 class="text-sm font-black text-on-surface mb-md flex items-center gap-2 uppercase tracking-wide">
                    <span class="material-symbols-outlined text-emerald-500 text-xl">qr_code_scanner</span>
                    Master Barcodes
                </h3>


                <div class="flex gap-sm mb-md">
                    <div class="flex-1 relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-lg">barcode_scanner</span>
                        <input wire:model="newBarcode" wire:keydown.enter="addBarcode" type="text" class="w-full h-11 pl-10 pr-4 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 font-mono font-bold transition-all text-sm" placeholder="Scan or type barcode...">
                    </div>
                    <button wire:click="addBarcode" class="h-11 bg-slate-100 hover:bg-emerald-500 hover:text-white px-5 rounded-md font-bold transition-all active:scale-95 flex items-center gap-2 text-xs border border-slate-200 dark:border-slate-800">
                        <span class="material-symbols-outlined text-sm">add</span> Add
                    </button>
                </div>
                @error('newBarcode') <span class="text-error text-xs font-bold mb-4 block">{{ $message }}</span> @enderror

                @if(empty($barcodes))
                    <div class="text-center py-6 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-md text-slate-400">
                        <span class="material-symbols-outlined text-3xl mb-2">qr_code</span>
                        <p class="text-xs font-bold">No barcodes recorded.</p>
                        <p class="text-[10px] mt-1">Scan physical barcodes so this item can be found during inbound/outbound.</p>
                    </div>
                @else
                    <div class="space-y-sm">
                        @foreach($barcodes as $i => $bc)
                            <div class="flex items-center justify-between p-sm rounded-md border {{ $i === $primaryBarcodeIndex ? 'bg-emerald-50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-800' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800' }}">
                                <div class="flex items-center gap-3">
                                    <button wire:click="setPrimaryBarcode({{ $i }})" class="w-5 h-5 rounded-full border flex items-center justify-center {{ $i === $primaryBarcodeIndex ? 'border-emerald-500 text-emerald-500' : 'border-slate-300 text-transparent hover:border-emerald-500 transition-colors' }}">
                                        <span class="material-symbols-outlined text-[12px] {{ $i === $primaryBarcodeIndex ? 'opacity-100' : 'opacity-0' }}" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                    </button>
                                    <span class="font-mono font-bold text-slate-700 text-xs">{{ $bc['code'] }}</span>
                                    @if($i === $primaryBarcodeIndex)
                                        <span class="text-[9px] font-black uppercase text-emerald-600 bg-emerald-100 px-2 py-0.5 rounded tracking-widest">Primary</span>
                                    @endif
                                </div>
                                <button wire:click="removeBarcode({{ $i }})" class="text-slate-400 hover:text-red-500 transition-colors">
                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            
            <div class="flex justify-end pt-4 pb-12 lg:pb-0">
                <button wire:click="save" wire:loading.attr="disabled" class="h-11 bg-primary hover:bg-primary-fixed-variant text-white px-6 rounded-md font-black text-xs uppercase tracking-widest shadow-md hover:shadow-lg focus:ring-4 focus:ring-primary/20 transition-all active:scale-95 flex items-center gap-2 w-full lg:w-auto justify-center">
                    <span wire:loading.remove wire:target="save" class="material-symbols-outlined text-sm">save</span>
                    <span wire:loading wire:target="save" class="material-symbols-outlined animate-spin text-sm">progress_activity</span>
                    SAVE ITEM CONFIGURATION
                </button>
            </div>
            
        </div>

        <!-- Right Column: Image Assets Management -->
        <div class="col-span-1 lg:col-span-5 space-y-md">
            
            <!-- Upload Dropzone Area -->
            <div class="bg-white dark:bg-slate-900 rounded-md p-md border-2 border-dashed border-slate-300 dark:border-slate-800 flex flex-col items-center justify-center text-center group hover:border-primary transition-colors relative" id="photo_dropzone">
                <input type="file" wire:model="photos" multiple accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" />
                
                <div class="w-12 h-12 bg-primary/10 rounded-md flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-primary text-2xl">cloud_upload</span>
                </div>
                <h3 class="text-md font-black text-on-surface">Drag and drop item photos</h3>
                <p class="text-xs text-slate-500 mt-2 max-w-sm">Support for JPEG & PNG. Used for visual inventory verification.</p>
                
                <div class="mt-4 flex gap-2 z-0 relative">
                    <span class="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 rounded-sm text-[9px] font-bold text-slate-500 uppercase tracking-widest hidden sm:inline-block">Max: 25MB</span>
                    <span class="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 rounded-sm text-[9px] font-bold text-slate-500 uppercase tracking-widest">Recommended: 1:1</span>
                </div>
                
                <div wire:loading wire:target="photos" class="absolute inset-0 bg-white/80 dark:bg-slate-900/80 backdrop-blur-sm z-20 flex flex-col items-center justify-center rounded-md">
                    <span class="material-symbols-outlined animate-spin text-primary text-2xl mb-2">progress_activity</span>
                    <p class="font-bold text-slate-600 text-xs">Uploading processing...</p>
                </div>
            </div>

            @error('photos.*') <span class="text-error text-xs font-bold mt-1 block">{{ $message }}</span> @enderror

            <!-- Existing Gallery Grid -->
            @if(!empty($existingPhotos) || !empty($photos))
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md p-md shadow-sm">
                <h3 class="font-black text-on-surface mb-md flex items-center justify-between text-xs uppercase tracking-wide">
                    <span>Visual Reference Gallery</span>
                    <span class="text-[10px] font-bold bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded text-slate-500 uppercase tracking-widest">{{ count($existingPhotos) + count($photos) }} Media</span>
                </h3>
                
                <div class="grid grid-cols-2 gap-sm">
                    <!-- New Uploads Previews -->
                    @foreach($photos as $index => $photo)
                    <div class="group relative bg-slate-50 dark:bg-slate-800 rounded-md overflow-hidden aspect-square shadow-sm border {{ $primaryPhotoIndex === $index ? 'border-primary' : 'border-slate-200 dark:border-slate-850' }}">
                        <img class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" src="{{ $photo->temporaryUrl() }}" />
                        <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-1.5 px-3 shadow-inner">
                            <button @click.prevent="openCropper('new', {{ $index }}, '{{ $photo->temporaryUrl() }}')" class="h-9 px-3 w-full bg-primary text-white rounded-md font-black text-[9px] uppercase tracking-widest hover:bg-primary-fixed-variant flex items-center justify-center gap-1.5 shadow-sm scale-95 hover:scale-100 transition-all">
                                <span class="material-symbols-outlined text-sm">crop_free</span> Interactive Crop
                            </button>
                            @if($primaryPhotoIndex !== $index)
                                <button wire:click.prevent="setPrimaryNew({{ $index }})" class="h-9 px-3 w-full bg-white/90 backdrop-blur-md rounded-md text-primary font-black text-[9px] uppercase tracking-widest hover:bg-white flex items-center justify-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm">star</span> Set Primary
                                </button>
                            @endif
                            <button wire:click.prevent="removeUploadedPhoto({{ $index }})" class="h-9 px-3 w-full bg-red-400/20 backdrop-blur-md rounded-md text-red-600 font-black text-[9px] uppercase tracking-widest hover:bg-red-500 hover:text-white flex items-center justify-center gap-1.5 transition-colors">
                                <span class="material-symbols-outlined text-sm">delete</span> Remove
                            </button>
                        </div>
                        @if($primaryPhotoIndex === $index)
                            <div class="absolute top-1.5 left-1.5 bg-primary text-white text-[9px] font-black uppercase px-2 py-0.5 rounded shadow-sm flex items-center gap-1">
                                <span class="material-symbols-outlined text-[10px]" style="font-variation-settings: 'FILL' 1;">star</span> Primary
                            </div>
                        @else
                            <div class="absolute top-1.5 left-1.5 bg-slate-800 text-white text-[9px] font-black uppercase px-2 py-0.5 rounded shadow-sm">Preview</div>
                        @endif
                        
                        <div class="absolute bottom-1.5 right-1.5 flex gap-1">
                            <span class="bg-amber-100 text-amber-700 text-[8px] font-black px-1.5 py-0.5 rounded uppercase tracking-tighter shadow-sm border border-amber-200">Pending Crop</span>
                        </div>
                    </div>
                    @endforeach

                    <!-- Existing Saved Images -->
                    @foreach($existingPhotos as $index => $image)
                    <div class="group relative bg-slate-50 dark:bg-slate-800 rounded-md overflow-hidden aspect-square shadow-sm border {{ $primaryImageId == $image['id'] ? 'border-primary ring-2 ring-primary/20' : 'border-slate-200 dark:border-slate-850' }}">
                        <img class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 opacity-90" src="{{ asset('storage/' . $image['path']) }}?v={{ \Carbon\Carbon::parse($image['updated_at'] ?? now())->timestamp }}" />
                        <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-1.5 px-3 shadow-inner">
                            <button @click.prevent="openCropper('existing', {{ $image['id'] }}, '{{ asset('storage/' . $image['path']) }}?v={{ \Carbon\Carbon::parse($image['updated_at'] ?? now())->timestamp }}')" class="h-9 px-3 w-full bg-primary text-white rounded-md font-black text-[9px] uppercase tracking-widest hover:bg-primary-fixed-variant flex items-center justify-center gap-1.5 shadow-sm scale-95 hover:scale-100 transition-all">
                                <span class="material-symbols-outlined text-sm">crop_free</span> Interactive Crop
                            </button>
                            @if($primaryImageId != $image['id'])
                            <button wire:click.prevent="setPrimaryExisting({{ $image['id'] }})" class="h-9 px-3 w-full bg-white/90 backdrop-blur-md rounded-md text-primary font-black text-[9px] uppercase tracking-widest hover:bg-white flex items-center justify-center gap-1.5">
                                <span class="material-symbols-outlined text-sm">star</span> Set Primary
                            </button>
                            @endif
                            <button wire:click.prevent="removeExistingPhoto({{ $image['id'] }})" class="h-9 px-3 w-full bg-red-500/90 backdrop-blur-md rounded-md text-white font-black text-[9px] uppercase tracking-widest hover:bg-red-600 flex items-center justify-center gap-1.5" onclick="return confirm('Are you sure you want to delete this image permanently?')">
                                <span class="material-symbols-outlined text-sm">delete</span> Delete
                            </button>
                        </div>
                        @if($primaryImageId == $image['id'])
                            <div class="absolute top-1.5 left-1.5 bg-primary text-white text-[9px] font-black uppercase px-2 py-0.5 rounded shadow-sm flex items-center gap-1">
                                <span class="material-symbols-outlined text-[10px]" style="font-variation-settings: 'FILL' 1;">star</span> Primary
                            </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Info Context -->
            <div class="bg-blue-50/50 dark:bg-blue-950/20 rounded-md p-md border border-blue-100 dark:border-blue-800">
                <div class="flex items-start gap-4">
                    <span class="material-symbols-outlined text-blue-500 text-2xl">policy</span>
                    <div>
                        <p class="text-xs font-black text-blue-800 dark:text-blue-200 mb-1">Upload Integrity Checking</p>
                        <p class="text-[10px] text-blue-600/80 dark:text-blue-300 leading-relaxed font-semibold">
                            Once items are confirmed and saved, the primary media will be synced permanently down to local scanners to ensure faster visual verification during stock opname.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <!-- Cropping Modal -->
    <!-- Ultra-Stable Cropping Modal (Z-9999, No Animations) -->
    <div x-show="cropping" 
         class="fixed inset-0 z-[9999] flex items-center justify-center p-2 sm:p-4 bg-slate-900/90"
         x-cloak>
        <div class="bg-white dark:bg-slate-900 rounded-md overflow-hidden max-w-xl w-full shadow-2xl flex flex-col h-[85vh]">
            <div class="p-md border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-white dark:bg-slate-900 shrink-0">
                <div>
                    <h3 class="text-sm font-black text-slate-850 dark:text-slate-100 tracking-tight uppercase">Interactive Square Crop</h3>
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mt-1">Adjust position to center your product</p>
                </div>
            </div>
            
            <div class="flex-1 bg-slate-100 dark:bg-slate-800 relative overflow-hidden flex items-center justify-center">
                <!-- Status layer (Click-through) -->
                <div x-show="processing" class="absolute inset-0 z-50 flex flex-col items-center justify-center pointer-events-none bg-slate-100/50 dark:bg-slate-900/50">
                    <span class="material-symbols-outlined animate-spin text-primary text-2xl mb-2">progress_activity</span>
                    <p class="text-[8px] font-black text-slate-400 uppercase">Engine Loading...</p>
                </div>
                
                <img x-ref="cropperImage" class="block max-w-full" style="max-height: 50vh;" />
            </div>
            
            <div class="p-md bg-slate-50 dark:bg-slate-800 border-t border-slate-100 dark:border-slate-800 flex flex-col gap-2 shrink-0">
                <button @click="saveCrop()" type="button" class="h-11 w-full bg-primary text-white rounded-md font-black text-xs uppercase tracking-widest shadow-md flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-sm">check_circle</span>
                    Apply & Save Image
                </button>
                <button @click="cropping = false; if($refs.cropperImage && $refs.cropperImage._cropper) $refs.cropperImage._cropper.destroy();" type="button" class="h-11 w-full text-slate-400 hover:text-slate-500 rounded-md font-black text-[10px] uppercase tracking-widest flex items-center justify-center">
                    Cancel / Keep Original
                </button>
            </div>
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }
        .cropper-view-box,
        .cropper-face {
            border-radius: 5%;
        }
        .cropper-line, .cropper-point {
            background-color: #003d9b;
        }
    </style>
    </div> <!-- Close Alpine Data Wrap -->
</div>
