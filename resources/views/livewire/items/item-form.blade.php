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

            <!-- Storage & Inventory Card -->
            <div class="bg-surface-container-lowest rounded-3xl p-8 border border-slate-200 shadow-sm border-l-4 border-blue-500">
                <h3 class="text-xl font-bold text-on-surface mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined text-blue-500">inventory_2</span>
                    Storage & Inventory
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Bin Location Code</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">location_on</span>
                            <input wire:model="bin_code" type="text" class="w-full pl-12 pr-4 bg-slate-50 border border-slate-200 rounded-xl py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono font-bold transition-all uppercase" placeholder="e.g. A-01-05">
                        </div>
                        @error('bin_code') <span class="text-error text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        <p class="text-[10px] text-slate-400 mt-1 font-bold italic">Update this whenever the item's primary storage spot changes.</p>
                    </div>

                    @if($mode === 'create')
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Initial Stock Quantity</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">box_add</span>
                            <input wire:model="initial_stock" type="number" class="w-full pl-12 pr-4 bg-slate-50 border border-slate-200 rounded-xl py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-bold transition-all" placeholder="0">
                        </div>
                        @error('initial_stock') <span class="text-error text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        <p class="text-[10px] text-slate-400 mt-1 font-bold italic">This can only be set during initial registration.</p>
                    </div>
                    @endif
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
            
            <!-- Upload Dropzone Area -->
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
                    <div class="group relative bg-white rounded-2xl overflow-hidden aspect-square shadow-sm border-2 {{ $primaryPhotoIndex === $index ? 'border-primary' : 'border-slate-100' }}">
                        <img class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" src="{{ $photo->temporaryUrl() }}" />
                        <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-2 px-4 shadow-inner">
                            <button @click.prevent="openCropper('new', {{ $index }}, '{{ $photo->temporaryUrl() }}')" class="w-full bg-primary text-white py-2 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-primary-fixed-variant flex items-center justify-center gap-2 shadow-lg scale-95 hover:scale-100 transition-all">
                                <span class="material-symbols-outlined text-[16px]">crop_free</span> Interactive Crop
                            </button>
                            @if($primaryPhotoIndex !== $index)
                                <button wire:click.prevent="setPrimaryNew({{ $index }})" class="w-full bg-white/90 backdrop-blur-md py-2 rounded-xl text-primary font-black text-[10px] uppercase tracking-widest hover:bg-white flex items-center justify-center gap-2">
                                    <span class="material-symbols-outlined text-[16px]">star</span> Set Primary
                                </button>
                            @endif
                            <button wire:click.prevent="removeUploadedPhoto({{ $index }})" class="w-full bg-red-400/20 backdrop-blur-md py-2 rounded-xl text-red-600 font-black text-[10px] uppercase tracking-widest hover:bg-red-500 hover:text-white flex items-center justify-center gap-2 transition-colors">
                                <span class="material-symbols-outlined text-[16px]">delete</span> Remove
                            </button>
                        </div>
                        @if($primaryPhotoIndex === $index)
                            <div class="absolute top-2 left-2 bg-primary text-white text-[10px] font-black uppercase px-2 py-1 rounded-md shadow-sm flex items-center gap-1">
                                <span class="material-symbols-outlined text-[12px]" style="font-variation-settings: 'FILL' 1;">star</span> Primary
                            </div>
                        @else
                            <div class="absolute top-2 left-2 bg-slate-800 text-white text-[10px] font-black uppercase px-2 py-1 rounded-md shadow-sm">Preview</div>
                        @endif
                        
                        <div class="absolute bottom-2 right-2 flex gap-1">
                            <span class="bg-amber-100 text-amber-700 text-[8px] font-black px-1.5 py-0.5 rounded uppercase tracking-tighter shadow-sm border border-amber-200">Pending Crop</span>
                        </div>
                    </div>
                    @endforeach

                    <!-- Existing Saved Images -->
                    @foreach($existingPhotos as $index => $image)
                    <div class="group relative bg-white rounded-2xl overflow-hidden aspect-square shadow-sm border {{ $primaryImageId == $image['id'] ? 'border-primary ring-2 ring-primary/20' : 'border-slate-100' }}">
                        <img class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 opacity-90" src="{{ asset('storage/' . $image['path']) }}?v={{ \Carbon\Carbon::parse($image['updated_at'] ?? now())->timestamp }}" />
                        <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-2 px-4 shadow-inner">
                            <button @click.prevent="openCropper('existing', {{ $image['id'] }}, '{{ asset('storage/' . $image['path']) }}?v={{ \Carbon\Carbon::parse($image['updated_at'] ?? now())->timestamp }}')" class="w-full bg-primary text-white py-2 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-primary-fixed-variant flex items-center justify-center gap-2 shadow-lg scale-95 hover:scale-100 transition-all">
                                <span class="material-symbols-outlined text-[16px]">crop_free</span> Interactive Crop
                            </button>
                            @if($primaryImageId != $image['id'])
                            <button wire:click.prevent="setPrimaryExisting({{ $image['id'] }})" class="w-full bg-white/90 backdrop-blur-md py-2 rounded-xl text-primary font-black text-[10px] uppercase tracking-widest hover:bg-white flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-sm">star</span> Set Primary
                            </button>
                            @endif
                            <button wire:click.prevent="removeExistingPhoto({{ $image['id'] }})" class="w-full bg-red-500/90 backdrop-blur-md py-2 rounded-xl text-white font-black text-[10px] uppercase tracking-widest hover:bg-red-600 flex items-center justify-center gap-2" onclick="return confirm('Are you sure you want to delete this image permanently?')">
                                <span class="material-symbols-outlined text-sm">delete</span> Delete
                            </button>
                        </div>
                        @if($primaryImageId == $image['id'])
                            <div class="absolute top-2 left-2 bg-primary text-white text-[10px] font-black uppercase px-2 py-1 rounded-md shadow-sm flex items-center gap-1">
                                <span class="material-symbols-outlined text-[12px]" style="font-variation-settings: 'FILL' 1;">star</span> Primary
                            </div>
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
    <!-- Cropping Modal -->
    <!-- Ultra-Stable Cropping Modal (Z-9999, No Animations) -->
    <div x-show="cropping" 
         class="fixed inset-0 z-[9999] flex items-center justify-center p-2 sm:p-4 bg-slate-900/90"
         x-cloak>
        <div class="bg-white rounded-[1.5rem] overflow-hidden max-w-xl w-full shadow-2xl flex flex-col h-[85vh]">
            <div class="p-4 md:p-6 border-b border-slate-100 flex items-center justify-between bg-white shrink-0">
                <div>
                    <h3 class="text-lg md:text-xl font-black text-slate-800 tracking-tight">Interactive Square Crop</h3>
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mt-1">Adjust position to center your product</p>
                </div>
            </div>
            
            <div class="flex-1 bg-slate-100 relative overflow-hidden flex items-center justify-center">
                <!-- Status layer (Click-through) -->
                <div x-show="processing" class="absolute inset-0 z-50 flex flex-col items-center justify-center pointer-events-none bg-slate-100/50">
                    <span class="material-symbols-outlined animate-spin text-primary text-3xl mb-2">progress_activity</span>
                    <p class="text-[8px] font-black text-slate-400 uppercase">Engine Loading...</p>
                </div>
                
                <img x-ref="cropperImage" class="block max-w-full" style="max-height: 50vh;" />
            </div>
            
            <div class="p-4 md:p-6 bg-slate-50 border-t border-slate-100 flex flex-col gap-3 shrink-0">
                <button @click="saveCrop()" type="button" class="w-full bg-primary text-white py-5 rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl flex items-center justify-center gap-3">
                    <span class="material-symbols-outlined text-lg">check_circle</span>
                    Apply & Save Image
                </button>
                <button @click="cropping = false; if($refs.cropperImage && $refs.cropperImage._cropper) $refs.cropperImage._cropper.destroy();" type="button" class="w-full py-3 font-black text-[10px] uppercase tracking-widest text-slate-400">
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
