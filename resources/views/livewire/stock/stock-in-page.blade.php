<div>
    <!-- Success/Error Feedback Banner -->
    <div x-data="{ show: false, message: '', type: 'success' }"
         x-on:message-dispatched.window="show = true; message = $event.detail.message; type = $event.detail.type; setTimeout(() => show = false, 5000)"
         x-show="show"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 -translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-4"
         class="fixed top-16 left-0 right-0 z-[60] px-6 py-2"
         style="display: none;">
        <div :class="type === 'success' ? 'bg-green-600' : 'bg-red-600'"
             class="max-w-3xl mx-auto shadow-2xl rounded-xl text-white py-3 px-6 flex items-center justify-between border-b border-black/10">
            <div class="flex items-center space-x-3">
                <span class="material-symbols-outlined text-xl" x-text="type === 'success' ? 'check_circle' : 'error'"></span>
                <div class="flex flex-col">
                    <span class="text-[10px] font-black uppercase tracking-[0.2em] opacity-80 leading-none" x-text="type === 'success' ? 'Status: Success' : 'Status: Error'"></span>
                    <span class="text-xs font-bold uppercase tracking-wider" x-text="message"></span>
                </div>
            </div>
            <button @click="show = false" class="text-white hover:bg-white/10 p-1 rounded-full transition-colors">
                <span class="material-symbols-outlined text-sm">close</span>
            </button>
        </div>
    </div>

    <!-- Last Action Feedback Banner - Linked to $lastAction -->
    @if($lastAction)
    <div class="fixed top-16 left-0 right-0 z-40 bg-primary text-white py-3 px-6 flex items-center justify-between shadow-lg border-b border-primary/30">
        <div class="flex items-center space-x-3">
            <span class="material-symbols-outlined text-xl">history</span>
            <div class="flex flex-col">
                <span class="text-[10px] font-black uppercase tracking-[0.2em] opacity-80 leading-none">Last Activity Recorded</span>
                <span class="text-xs font-bold uppercase tracking-wider">{{ $lastAction }}</span>
            </div>
        </div>
        <button wire:click="$set('lastAction', '')" class="text-white hover:bg-white/10 p-1 rounded-full transition-colors">
            <span class="material-symbols-outlined text-sm">close</span>
        </button>
    </div>
    @endif

    <main class="pt-32 px-4 md:ml-64 max-w-4xl mx-auto space-y-6 pb-48">
        <!-- Header & Scan Section -->
        <section class="space-y-4">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold tracking-tight text-on-surface">Receiving Dock</h1>
                    <p class="text-xs font-bold text-outline uppercase tracking-widest mt-1">Terminal 01 • INBOUND TRANSACTION</p>
                </div>

                <div class="flex items-center gap-4">
                    <!-- Reference / PO Number -->
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg">description</span>
                        <input wire:model="reference" type="text" 
                               class="bg-surface-container-high border-none rounded-xl pl-10 pr-4 py-3 text-sm focus:ring-2 focus:ring-primary w-48 md:w-64 font-bold" 
                               placeholder="Ref / PO / Note..."/>
                    </div>

                    <!-- Auto Add Toggle -->
                    <div class="flex items-center space-x-3 bg-surface-container-high px-4 py-3 rounded-xl border border-outline-variant/30">
                        <span class="text-[10px] font-black uppercase tracking-widest text-outline">Auto Add</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model.live="autoAddMode" class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Scan Input Area -->
            <div class="relative group">
                <div class="bg-surface-container-high rounded-[2rem] p-4 flex items-center shadow-sm border-2 border-transparent transition-all duration-300 focus-within:border-primary/50 focus-within:bg-white focus-within:shadow-2xl">
                    <div class="flex-1 bg-surface-container-lowest rounded-full h-16 flex items-center px-6 space-x-4 border-2 border-primary">
                        <span class="material-symbols-outlined text-primary text-3xl {{ $autoAddMode ? 'animate-pulse' : '' }}">barcode_scanner</span>
                        <input 
                            wire:model="barcode" 
                            wire:keydown.enter="handleScan"
                            id="barcode-input-stock-in"
                            autofocus 
                            class="bg-transparent border-none focus:ring-0 w-full text-xl font-black placeholder:text-outline/30 text-on-surface font-mono" 
                            placeholder="READY TO SCAN ITEM BARCODE..." 
                            type="text"/>
                        <div class="hidden sm:flex items-center bg-surface-container-low px-3 py-1.5 rounded-lg border border-outline-variant/30 text-[10px] font-black text-outline">
                            <span class="material-symbols-outlined text-xs mr-1">keyboard_return</span> ENTER
                        </div>
                    </div>
                    <button wire:click="handleScan" class="ml-4 h-16 w-16 bg-primary text-white rounded-full flex items-center justify-center shadow-lg hover:brightness-110 active:scale-95 transition-all">
                        <span class="material-symbols-outlined text-2xl">arrow_forward</span>
                    </button>
                </div>
            </div>
        </section>

        <!-- New Item Flow Overlay -->
        @if($isNewItem)
        <div class="fixed inset-0 z-[100] flex items-center justify-center px-4 bg-slate-900/60 backdrop-blur-md">
            <div class="bg-surface rounded-[2.5rem] w-full max-w-lg p-8 space-y-8 shadow-[0_32px_64px_rgba(0,0,0,0.3)] border border-white/20 animate-in fade-in zoom-in duration-300">
                <div class="flex items-center space-x-5">
                    <div class="h-16 w-16 rounded-[1.5rem] bg-amber-100 flex items-center justify-center border border-amber-200 shadow-inner">
                        <span class="material-symbols-outlined text-amber-600 text-3xl">add_box</span>
                    </div>
                    <div>
                        <h3 class="font-black text-2xl tracking-tighter">Register New Identity</h3>
                        <p class="text-xs font-bold text-outline uppercase tracking-widest mt-1">Barcode: <span class="text-amber-700 bg-amber-50 px-2 rounded">{{ $barcode }}</span></p>
                    </div>
                </div>

                <div class="space-y-5">
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-on-surface uppercase tracking-widest flex items-center ml-1">
                            ERP Identity Code <span class="text-error ml-1">*</span>
                        </label>
                        <input wire:model="erpCode" class="w-full h-16 rounded-2xl border-none bg-surface-container-low px-5 font-mono font-black text-lg focus:ring-4 focus:ring-primary/20 transition-all" placeholder="e.g. 5.10.880.XXX" type="text"/>
                        @error('erpCode') <p class="text-[10px] text-error font-bold mt-1 ml-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-on-surface uppercase tracking-widest flex items-center ml-1">
                            Full Item Name <span class="text-error ml-1">*</span>
                        </label>
                        <input wire:model="itemName" class="w-full h-16 rounded-2xl border-none bg-surface-container-low px-5 font-bold text-lg focus:ring-4 focus:ring-primary/20 transition-all" placeholder="e.g. BEARING NSK 6204ZZ" type="text"/>
                        @error('itemName') <p class="text-[10px] text-error font-bold mt-1 ml-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex flex-col space-y-3 pt-4">
                    <button wire:click="createNewItem" class="w-full h-16 bg-primary text-white rounded-2xl font-black text-xl shadow-xl hover:shadow-primary/30 active:scale-95 transition-all flex items-center justify-center gap-3">
                        <span class="material-symbols-outlined">save</span>
                        CREATE &amp; CONTINUE
                    </button>
                    <button wire:click="$set('isNewItem', false)" class="w-full h-16 bg-surface-container-highest text-on-surface rounded-2xl font-bold flex items-center justify-center gap-2 active:scale-95 transition-all">
                        CANCEL
                    </button>
                </div>
            </div>
        </div>
        @endif

        <!-- Item Detail & Add Zone -->
        @if($currentItem)
        <section class="bg-surface-container-low rounded-[3rem] p-6 space-y-8 border border-outline-variant/30 animate-in fade-in slide-in-from-bottom-4 duration-500 shadow-xl">
            <!-- Product Preview Header -->
            <div class="bg-surface-container-lowest rounded-[2rem] p-5 flex items-center space-x-6 shadow-sm">
                <div class="h-32 w-32 rounded-[1.5rem] bg-surface-container overflow-hidden shrink-0 ring-4 ring-black/5 shadow-inner">
                    <img alt="Item Image" class="w-full h-full object-cover" src="{{ $currentItem->images->where('is_primary', true)->first()->path ?? 'https://lh3.googleusercontent.com/aida-public/AB6AXuCOPfxQ4n9tWVXDRBANxCsUr8cvkDn_wmB10Qrl_DkKLpc7DTZuLEZ-DX8_YnNafiqMhJcF-Ou0J6C-YXpqK06s2HegwbqiCRdSZ1TwMjMv6lkDeTp-rtb_eW_Ft8v1c3ClYJ5efpWK8rNjQxbuGlxbew3OHwawVxSsq21W5oUch5Ghyvpvdl9xOrc2YewYTGqMpHdKSsdDk2S3QYJEVY_Q0Hgaql815G7YxHDxgt7Ssn7FY-U0D4sHO9Y1-R34-cj8S3oPTVSomiU' }}"/>
                </div>
                <div class="flex-1 space-y-1">
                    <span class="inline-block px-3 py-1 bg-green-100 text-green-700 text-[10px] font-black uppercase tracking-[0.2em] rounded-lg">Master Identity Verified</span>
                    <h3 class="text-3xl font-black leading-tight tracking-tight">{{ $currentItem->item->name }}</h3>
                    <div class="flex items-center gap-4">
                        <p class="text-sm font-black text-primary font-mono tracking-widest uppercase">{{ $currentItem->erp_code }}</p>
                        <div class="h-4 w-px bg-outline/20"></div>
                        <p class="text-xs font-bold text-outline uppercase tracking-wider">Brand: {{ $currentItem->brand ?? 'GENERAL' }}</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Selectors -->
                <div class="space-y-6">
                    <!-- Bin Selection - CRITICAL -->
                    <div class="space-y-3">
                        <label class="block text-xs font-black text-on-surface uppercase tracking-widest ml-1 flex justify-between">
                            Destination Bin <span class="text-primary font-mono lowercase">@required</span>
                        </label>
                        <div class="relative group">
                            <span class="material-symbols-outlined absolute left-4 top-11 text-primary text-2xl z-10">warehouse</span>
                            <select wire:model="bin_id" 
                                    class="w-full pl-14 pr-10 py-5 bg-white border-2 {{ $errors->has('bin_id') ? 'border-error' : 'border-transparent focus:border-primary' }} rounded-2xl font-black text-xl text-on-surface appearance-none transition-all shadow-sm">
                                <option value="">... SELECT DESTINATION ...</option>
                                @foreach($bins as $bin)
                                    <option value="{{ $bin->id }}">{{ $bin->code }} (Stock: {{ $bin->current_qty }})</option>
                                @endforeach
                            </select>
                            <span class="material-symbols-outlined absolute right-4 top-11 text-outline pointer-events-none">expand_circle_down</span>
                            @error('bin_id') <p class="text-[10px] text-error font-bold mt-2 ml-1 uppercase letter-spacing-widest">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- Supplier Selection -->
                    <div class="space-y-3">
                        <label class="block text-xs font-black text-outline uppercase tracking-widest ml-1">Source Supplier</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-4 top-11 text-outline text-2xl z-10">factory</span>
                            <select wire:model="supplier_id" 
                                    class="w-full pl-14 pr-10 py-5 bg-white/50 border-none rounded-2xl font-bold text-base text-on-surface appearance-none focus:ring-2 focus:ring-primary/20 transition-all">
                                <option value="">... NO SUPPLIER ...</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                            <span class="material-symbols-outlined absolute right-4 top-11 text-outline/40 pointer-events-none">unfold_more</span>
                        </div>
                    </div>
                </div>

                <!-- Quantity Control & Action -->
                <div class="flex flex-col justify-between space-y-6">
                    <div class="bg-white rounded-3xl p-6 shadow-sm flex flex-col items-center">
                        <span class="text-[10px] font-black text-outline uppercase tracking-[0.2em] mb-4">Entry Quantity</span>
                        <div class="flex items-center space-x-8">
                            <button wire:click="$set('qty', {{ $qty > 1 ? $qty - 1 : 1 }})" class="h-16 w-16 rounded-2xl bg-surface-container flex items-center justify-center active:scale-90 transition-all hover:bg-surface-container-highest">
                                <span class="material-symbols-outlined text-3xl font-black">remove</span>
                            </button>
                            <input wire:model="qty" type="number" 
                                   class="text-7xl font-black text-on-surface tracking-tighter w-40 text-center border-none focus:ring-0 p-0"
                                   wire:keydown.enter="addToCart"/>
                            <button wire:click="$set('qty', {{ (int)$qty + 1 }})" class="h-16 w-16 rounded-2xl bg-surface-container flex items-center justify-center active:scale-90 transition-all hover:bg-surface-container-highest">
                                <span class="material-symbols-outlined text-3xl font-black text-primary">add</span>
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <button wire:click="generateInternalBarcode" class="flex flex-col items-center justify-center py-4 bg-white border-2 border-dashed border-outline-variant/50 rounded-2xl text-xs font-black uppercase tracking-widest text-outline hover:border-primary/30 hover:text-primary transition-all">
                            <span class="material-symbols-outlined text-2xl mb-1">barcode</span>
                            Gen Label
                        </button>
                        <button class="flex flex-col items-center justify-center py-4 bg-white border-2 border-dashed border-outline-variant/50 rounded-2xl text-xs font-black uppercase tracking-widest text-outline hover:border-primary/30 hover:text-primary transition-all">
                            <span class="material-symbols-outlined text-2xl mb-1">print</span>
                            Print Info
                        </button>
                    </div>
                </div>
            </div>

            <button wire:click="addToCart" class="w-full h-24 bg-primary text-white rounded-[1.5rem] font-black text-2xl flex items-center justify-center space-x-4 shadow-[0_20px_40px_rgba(0,61,155,0.3)] hover:scale-[1.01] active:scale-98 transition-all group">
                <span class="material-symbols-outlined text-3xl group-hover:rotate-12 transition-transform">add_task</span>
                <span>ADD TO RECEIVING LIST</span>
            </button>
        </section>
        @endif

        <!-- Batch Summary Section -->
        <section class="space-y-6 pb-24">
            <div class="flex justify-between items-end px-2">
                <div>
                    <h2 class="text-2xl font-black tracking-tight">Active Batch Manifest</h2>
                    <p class="text-xs font-bold text-outline uppercase tracking-widest">Pending Commitment to Inventory</p>
                </div>
                <span class="bg-primary text-white font-black text-xs px-4 py-2 rounded-full">{{ count($cart) }} UNIQUE LINES</span>
            </div>

            <div class="grid grid-cols-1 gap-4">
                @forelse($cart as $key => $item)
                <div class="bg-surface-container-low p-6 rounded-[2rem] flex items-center justify-between group border-2 border-transparent hover:border-primary/20 transition-all shadow-sm">
                    <div class="flex items-center space-x-6">
                        <div class="h-12 w-1.5 bg-primary rounded-full"></div>
                        <div>
                            <h4 class="font-black text-lg tracking-tight">{{ $item['name'] }}</h4>
                            <div class="flex items-center gap-3 mt-1">
                                <span class="text-[10px] font-black bg-surface-container-highest text-on-surface px-2 py-0.5 rounded uppercase tracking-tighter">{{ $item['erp_code'] }}</span>
                                <span class="text-[10px] font-black text-primary uppercase tracking-widest flex items-center">
                                    <span class="material-symbols-outlined text-[12px] mr-1">location_on</span> {{ $item['bin_name'] }}
                                </span>
                                @if($item['supplier_name'] !== 'N/A')
                                <span class="text-[10px] font-bold text-outline uppercase tracking-widest">• {{ $item['supplier_name'] }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-8">
                        <div class="text-right">
                            <p class="text-3xl font-black text-on-surface tracking-tighter">{{ number_format($item['qty']) }}</p>
                            <p class="text-[10px] font-black text-outline uppercase tracking-[0.2em] leading-none">Units</p>
                        </div>
                        <button wire:click="removeFromCart('{{ $key }}')" class="h-12 w-12 flex items-center justify-center text-outline/30 hover:text-error hover:bg-error/5 rounded-full transition-all">
                            <span class="material-symbols-outlined font-bold">delete</span>
                        </button>
                    </div>
                </div>
                @empty
                <div class="py-20 flex flex-col items-center justify-center bg-surface-container-low/50 rounded-[3rem] border-4 border-dashed border-outline-variant/20 italic text-outline grayscale opacity-50">
                    <span class="material-symbols-outlined text-6xl mb-4">move_to_inbox</span>
                    <p class="text-xl font-bold">Waiting for scans...</p>
                    <p class="text-sm">Items added to batch will appear here</p>
                </div>
                @endforelse
            </div>
        </section>
    </main>

    <!-- Fixed Submission Bar -->
    @if(count($cart) > 0)
    <div class="fixed bottom-0 md:left-64 right-0 bg-surface-container-lowest/90 backdrop-blur-xl px-8 py-6 flex items-center justify-between z-50 border-t border-outline-variant/30 shadow-[0_-20px_40px_rgba(0,0,0,0.05)]">
        <div class="flex flex-col">
            <p class="text-[10px] font-black text-outline uppercase tracking-[0.2em]">Batch Total Quantity</p>
            <p class="text-3xl font-black tracking-tighter text-on-surface">
                {{ number_format(collect($cart)->sum('qty')) }} <span class="text-sm text-outline font-bold ml-1 uppercase">Units</span>
            </p>
        </div>
        
        <div class="flex items-center gap-6">
            <div class="text-right hidden xl:block">
                <p class="text-[10px] font-black text-outline uppercase tracking-widest">Draft Operation</p>
                <p class="text-sm font-bold text-primary">Ready to Commit</p>
            </div>
            <button wire:click="submit" 
                    class="bg-on-surface text-surface h-20 px-16 rounded-[1.5rem] font-black text-2xl tracking-tighter shadow-2xl hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-4">
                <span class="material-symbols-outlined text-3xl">publish</span>
                CONFIRM &amp; SYNC STOCK
            </button>
        </div>
    </div>
    @endif

    <script>
        document.addEventListener('livewire:initialized', () => {
            window.addEventListener('scan-completed', event => {
                // Play Success Feedback
                if (navigator.vibrate) navigator.vibrate(100);
                playBeep(880, 0.1);
            });

            window.addEventListener('scan-success', event => {
                playBeep(440, 0.05);
            });

            function playBeep(freq, duration) {
                try {
                    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                    const oscillator = audioCtx.createOscillator();
                    const gainNode = audioCtx.createGain();
                    oscillator.connect(gainNode);
                    gainNode.connect(audioCtx.destination);
                    oscillator.type = 'sine';
                    oscillator.frequency.value = freq;
                    gainNode.gain.setValueAtTime(0.05, audioCtx.currentTime);
                    oscillator.start();
                    oscillator.stop(audioCtx.currentTime + duration);
                } catch(e) {}
            }
        });

        window.addEventListener('focus-barcode-input', event => {
            setTimeout(() => {
                const input = document.getElementById('barcode-input-stock-in');
                if (input) {
                    input.focus();
                    input.select();
                }
            }, 50);
        });

        // Global hotkey for focus
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === '/') {
                document.getElementById('barcode-input-stock-in')?.focus();
            }
        });
    </script>
</div>
