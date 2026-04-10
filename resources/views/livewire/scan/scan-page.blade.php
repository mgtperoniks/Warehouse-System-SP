<div class="mt-16 flex flex-col lg:flex-row flex-1 p-6 lg:p-8 gap-8">
    <!-- Left Panel: Scan & Info -->
    <section class="flex-1 space-y-6">
        <!-- Last Action Confirmation Toast -->
        @if($message)
            <div class="{{ $messageType === 'success' ? 'bg-[#ecfdf5] border-[#10b981]' : 'bg-[#fff1f2] border-[#e11d48]' }} border-l-4 p-4 rounded-xl flex items-center justify-between shadow-sm animate-in fade-in slide-in-from-top-4 duration-500">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined {{ $messageType === 'success' ? 'text-[#10b981]' : 'text-[#e11d48]' }}">
                        {{ $messageType === 'success' ? 'check_circle' : 'error' }}
                    </span>
                    <p class="text-sm font-bold text-slate-800">{{ $message }}</p>
                </div>
            </div>
        @endif

        <!-- Scan Input Container (Visual Priority) -->
        <div class="bg-primary p-1 rounded-2xl shadow-xl">
            <div class="bg-surface-container-lowest p-6 rounded-[calc(1rem-2px)]">
                <label class="block text-[10px] font-black text-primary uppercase tracking-[0.2em] mb-4">Awaiting Scan...</label>
                <div class="flex items-center gap-4">
                    <div class="flex-1 relative">
                        <span class="material-symbols-outlined absolute left-5 top-1/2 -translate-y-1/2 text-primary text-3xl">barcode_scanner</span>
                        <input 
                            wire:model="barcode" 
                            wire:keydown.enter="handleScan"
                            autofocus 
                            class="w-full pl-16 pr-6 py-6 bg-surface-container-high rounded-2xl border-2 border-transparent focus:border-primary focus:ring-0 text-1xl md:text-2xl font-black placeholder:text-slate-300 transition-all font-mono" 
                            placeholder="Scan Item Barcode" 
                            type="text"/>
                    </div>
                    <button wire:click="handleScan" class="bg-primary text-white w-16 h-16 md:w-20 md:h-20 rounded-2xl shadow-lg flex items-center justify-center hover:brightness-110 active:scale-95 transition-all">
                        <span class="material-symbols-outlined text-4xl">keyboard_return</span>
                    </button>
                    <!-- Camera Toggle Button -->
                    <button onclick="startScanner()" type="button" class="bg-slate-200 text-slate-700 w-16 h-16 md:w-20 md:h-20 rounded-2xl shadow-lg flex items-center justify-center hover:bg-slate-300 active:scale-95 transition-all">
                        <span class="material-symbols-outlined text-4xl" style="font-variation-settings: 'FILL' 1;">photo_camera</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Scanner Container (Hidden by default, wire:ignore protects the video stream from Livewire re-renders) -->
        <div id="scanner-container" class="hidden mt-4 bg-black rounded-2xl overflow-hidden relative border-4 border-slate-200 shadow-xl" wire:ignore>
            <div id="reader" style="width: 100%;"></div>
            <button type="button" onclick="stopScanner()" class="absolute top-4 right-4 bg-red-500 text-white px-4 py-2 text-sm font-bold rounded-xl shadow-lg z-50 hover:bg-red-600 flex items-center gap-2 transition-all">
                <span class="material-symbols-outlined text-sm">close</span> Cancel Scan
            </button>
        </div>

        <!-- Item Info Card (Simplified & Flash Success) -->
        @if($currentItem)
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-1 bg-surface-container-lowest rounded-2xl overflow-hidden shadow-lg success-flash">
                <img alt="Product Image" class="w-full h-full object-cover min-h-[160px] md:min-h-[240px]" src="{{ $currentItem->images->where('is_primary', true)->first()->path ?? 'https://lh3.googleusercontent.com/aida-public/AB6AXuCOPfxQ4n9tWVXDRBANxCsUr8cvkDn_wmB10Qrl_DkKLpc7DTZuLEZ-DX8_YnNafiqMhJcF-Ou0J6C-YXpqK06s2HegwbqiCRdSZ1TwMjMv6lkDeTp-rtb_eW_Ft8v1c3ClYJ5efpWK8rNjQxbuGlxbew3OHwawVxSsq21W5oUch5Ghyvpvdl9xOrc2YewYTGqMpHdKSsdDk2S3QYJEVY_Q0Hgaql815G7YxHDxgt7Ssn7FY-U0D4sHO9Y1-R34-cj8S3oPTVSomiU' }}"/>
            </div>
            <div class="md:col-span-2 bg-surface-container-lowest p-6 md:p-8 rounded-2xl shadow-lg flex flex-col justify-between success-flash">
                <div class="space-y-4">
                    <div class="flex justify-between items-start flex-col md:flex-row gap-4 md:gap-0">
                        <div>
                            <h2 class="text-3xl md:text-4xl font-black tracking-tight text-on-surface mb-1">{{ $currentItem->item->name }}</h2>
                            <p class="text-lg text-slate-500 font-bold flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary">inventory_2</span>
                                Stok System: {{ \App\Models\Bin::where('item_variant_id', $currentItem->id)->sum('current_qty') }}
                            </p>
                            <p class="text-sm text-slate-400 mt-1">Brand: {{ $currentItem->brand ?? '-' }}</p>
                        </div>
                        <span class="bg-primary-container text-white px-4 py-2 rounded-xl text-sm font-black">{{ $currentItem->sku }}</span>
                    </div>
                </div>
                
                <div class="mt-8 flex flex-col sm:flex-row items-center gap-6">
                    <div class="w-full sm:flex-1">
                        <label class="block text-center text-xs font-black text-slate-400 uppercase tracking-widest mb-3">Quantity to Add</label>
                        <div class="flex items-center justify-between bg-surface-container-high rounded-2xl p-2 h-20 md:h-24">
                            <button wire:click="$set('qty', {{ $qty > 1 ? $qty - 1 : 1 }})" class="h-16 w-16 md:h-20 md:w-20 flex items-center justify-center text-primary hover:bg-slate-200 rounded-xl transition-all">
                                <span class="material-symbols-outlined text-4xl font-black">remove</span>
                            </button>
                            <!-- Binding the qty property logically to the input -->
                            <input wire:model="qty" wire:keydown.enter="addToCart" class="w-full text-center bg-transparent border-none focus:ring-0 text-4xl md:text-5xl font-black text-on-surface" type="number" min="1"/>
                            <button wire:click="$set('qty', {{ (int)$qty + 1 }})" class="h-16 w-16 md:h-20 md:w-20 flex items-center justify-center text-primary hover:bg-slate-200 rounded-xl transition-all">
                                <span class="material-symbols-outlined text-4xl font-black">add</span>
                            </button>
                        </div>
                    </div>
                    <button wire:click="addToCart" class="w-full sm:w-auto h-20 md:h-24 px-10 bg-primary text-white rounded-2xl font-black text-xl flex items-center justify-center gap-4 shadow-xl hover:shadow-primary/25 hover:translate-y-[-2px] transition-all active:translate-y-0">
                        <span class="material-symbols-outlined text-3xl">add_shopping_cart</span>
                        COMMIT
                    </button>
                </div>
            </div>
        </div>
        @endif
    </section>

    <!-- Right Panel: Transaction Cart -->
    <aside class="w-full lg:w-96 flex flex-col bg-surface-container-low rounded-2xl overflow-hidden shadow-sm relative border-l-0">
        <div class="p-6 border-b border-slate-200 flex items-center justify-between bg-white/50">
            <div>
                <h3 class="font-black text-lg tracking-tight">Active Batch</h3>
                <p class="text-xs text-slate-500">Session Cart</p>
            </div>
            <!-- Reactive count mapping -->
            <span class="bg-blue-600 text-white font-black text-xs px-3 py-1.5 rounded-full">{{ count($cart) }} ITEMS</span>
        </div>
        
        <div class="flex-1 overflow-y-auto min-h-[300px] p-4 space-y-4">
            @forelse($cart as $index => $item)
                <!-- Cart Item mapping based on the array pushed by ScanPage.php addToCart() -->
                <div class="bg-surface-container-lowest p-4 rounded-xl shadow-sm border-l-4 {{ $loop->last ? 'border-[#10b981] success-flash' : 'border-slate-300 opacity-80' }}">
                    <div class="flex justify-between mb-2">
                        <span class="font-bold text-sm">{{ $item['name'] }}</span>
                        <!-- mapped removeFromCart($index) wire attribute -->
                        <button wire:click="removeFromCart({{ $index }})" class="text-slate-400 hover:text-red-500 transition-colors">
                            <span class="material-symbols-outlined text-sm">close</span>
                        </button>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-mono text-slate-500">{{ $item['barcode'] }}</span>
                        <span class="text-sm font-black text-primary">x {{ $item['qty'] }}</span>
                    </div>
                </div>
            @empty
                <div class="h-full flex flex-col items-center justify-center opacity-50 py-10">
                    <span class="material-symbols-outlined text-5xl mb-2 text-slate-400">shopping_cart</span>
                    <p class="text-sm font-bold text-slate-500">Cart is empty</p>
                </div>
            @endforelse
        </div>

        <!-- Sticky Submit Button Area -->
        <div class="p-6 bg-surface-container-low border-t border-slate-200">
            <!-- Submit mapped carefully enforcing backend rules (disabled if empty) -->
            <button 
                wire:click="submit" 
                class="w-full bg-[#10b981] text-white py-5 rounded-2xl font-black text-lg flex items-center justify-center gap-3 shadow-[0_12px_24px_rgba(16,185,129,0.3)] hover:bg-[#059669] active:scale-[0.98] transition-all disabled:opacity-50 disabled:grayscale disabled:cursor-not-allowed"
                @if(empty($cart)) disabled @endif>
                <span class="material-symbols-outlined">print</span>
                FINISH &amp; PRINT
            </button>
        </div>
    </aside>
</div>

<!-- HTML5 QR Code Library and Implementation -->
<script src="https://unpkg.com/html5-qrcode"></script>
<script>
    let html5QrCode;

    function startScanner() {
        // Show UI
        document.getElementById('scanner-container').classList.remove('hidden');
        
        // Initialize if empty
        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode("reader");
        }

        // Start specific facing mode (Rear Camera)
        html5QrCode.start(
            { facingMode: "environment" },
            { 
                fps: 15, 
                qrbox: { width: 250, height: 250 } 
            },
            (decodedText) => {
                // Success Scan Pipeline
                stopScanner();
                
                // Directly communicate with this specific Livewire component instance
                @this.set('barcode', decodedText);
                @this.call('handleScan');
            },
            (errorMessage) => {
                // Ignore silent background scan failures
            }
        ).catch(err => {
            console.error("Camera startup error", err);
            alert("Could not start camera. Please ensure permissions are granted.");
            stopScanner();
        });
    }

    function stopScanner() {
        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop().then(() => {
                document.getElementById('scanner-container').classList.add('hidden');
            }).catch(err => console.error("Error stopping scanner", err));
        } else {
            document.getElementById('scanner-container').classList.add('hidden');
        }
    }
</script>
