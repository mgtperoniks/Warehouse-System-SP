@extends('layouts.app')

@section('content')
<div class="pt-24 px-4 pb-6 lg:px-8">
    <!-- Bento-Style Hero Header -->
    <div class="grid grid-cols-1 md:grid-cols-12 gap-6 mb-8 max-w-7xl mx-auto">
        <!-- Large Image Container -->
        <div class="md:col-span-7 bg-surface-container-lowest rounded-3xl overflow-hidden shadow-sm relative group aspect-video md:aspect-auto h-[320px] md:h-[480px]">
            @php
                $primaryImage = $variant->images->where('is_primary', true)->first();
                $imagePath = $primaryImage ? asset('storage/' . $primaryImage->path) : asset('images/placeholders/item.svg');
            @endphp
            <img class="w-full h-full object-cover" alt="{{ $variant->item->name }}" src="{{ $imagePath }}"/>
            <div class="absolute bottom-4 right-4 bg-surface/90 backdrop-blur p-2 rounded-xl flex gap-2">
                <button class="material-symbols-outlined text-primary hover:text-primary-fixed-dim transition-colors">zoom_in</button>
            </div>
        </div>
        
        <!-- Item Identity Card -->
        <div class="md:col-span-5 flex flex-col gap-6">
            <div class="bg-surface-container-lowest p-8 rounded-3xl shadow-sm flex-1 flex flex-col justify-between border-t-4 border-primary">
                <div>
                    <div class="flex justify-between items-start mb-4">
                        <span class="px-3 py-1 bg-primary-fixed text-on-primary-fixed text-xs font-bold rounded-lg tracking-widest uppercase">Inventory</span>
                        <div class="flex gap-2">
                            <a href="{{ route('items.edit', $variant->id) }}" class="material-symbols-outlined text-outline hover:text-primary transition-colors cursor-pointer" title="Edit Item">edit</a>
                        </div>
                    </div>
                    <h2 class="text-4xl font-extrabold tracking-tighter text-on-surface mb-2">{{ $variant->item->name }}</h2>
                    <p class="text-secondary font-medium mb-6">{{ $variant->brand ?? 'No Brand' }}</p>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between py-2 border-b border-outline-variant/30">
                            <span class="text-sm text-outline font-bold uppercase tracking-wider">ERP CODE</span>
                            <span class="text-sm font-black text-on-surface bg-slate-100 px-3 py-1 rounded-md">{{ $variant->erp_code ?? '-' }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-outline-variant/30">
                            <span class="text-sm text-outline font-bold uppercase tracking-wider">SKU</span>
                            <span class="text-sm font-black text-on-surface">{{ $variant->sku ?? '-' }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <span class="text-sm text-outline font-bold uppercase tracking-wider">UNIT</span>
                            <span class="text-sm font-black text-on-surface">{{ $variant->unit ?? 'PCS' }}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Barcode Section -->
                @php
                    $primaryBarcode = $variant->primaryBarcode;
                @endphp
                <div class="mt-8 p-4 bg-white border-2 border-slate-200 rounded-xl flex flex-col items-center justify-center gap-2 shadow-inner">
                    @if($primaryBarcode)
                        <!-- Simplified barcode visual -->
                        <div class="w-full h-16 bg-[repeating-linear-gradient(90deg,#000,#000_2px,transparent_2px,transparent_4px,#000_4px,#000_5px,transparent_5px,transparent_8px)] opacity-80"></div>
                        <span class="font-mono text-lg tracking-[0.4em] font-black mt-2">{{ $primaryBarcode->barcode }}</span>
                    @else
                        <div class="text-slate-400 flex flex-col items-center">
                            <span class="material-symbols-outlined text-4xl mb-2">barcode</span>
                            <span class="text-sm font-bold">No Primary Barcode</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    
    <div class="max-w-7xl mx-auto">
        <!-- Metrics & Inventory Stats -->
        @php
            $currentStock = $variant->bins->sum('current_qty');
            $mainBin = $variant->bins->first();
        @endphp
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-surface-container-lowest p-6 rounded-3xl border-l-4 border-emerald-500 shadow-sm flex flex-col justify-center">
                <p class="text-xs font-bold uppercase tracking-widest text-outline mb-2">Current Stock</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-4xl font-black tracking-tight text-emerald-600">{{ number_format($currentStock) }}</span>
                    <span class="text-sm font-bold text-secondary">{{ $variant->unit ?? 'Units' }}</span>
                </div>
            </div>
            
            <div class="bg-surface-container-lowest p-6 rounded-3xl border-l-4 border-blue-500 shadow-sm flex flex-col justify-center">
                <p class="text-xs font-bold uppercase tracking-widest text-outline mb-2">Main Location</p>
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-blue-500 text-3xl">location_on</span>
                    <span class="text-2xl font-black tracking-tight">{{ $mainBin ? $mainBin->code : 'Not Stored' }}</span>
                </div>
            </div>
            
            <div class="bg-surface-container-lowest p-6 rounded-3xl border-l-4 border-amber-500 shadow-sm flex flex-col justify-center">
                <p class="text-xs font-bold uppercase tracking-widest text-outline mb-2">Suppliers Configured</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-4xl font-black tracking-tight text-amber-600">{{ $variant->suppliers->count() }}</span>
                    <span class="text-sm font-bold text-secondary">Vendors</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column: Details & Suppliers -->
            <div class="lg:col-span-1 space-y-8">
                <!-- Description -->
                <div class="bg-surface-container-lowest rounded-3xl shadow-sm p-6">
                    <h3 class="text-lg font-black tracking-tight mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-slate-400">description</span>
                        Description
                    </h3>
                    <p class="text-slate-600 text-sm leading-relaxed whitespace-pre-line">
                        {{ $variant->description ?? 'No description provided.' }}
                    </p>
                </div>

                <!-- Suppliers List -->
                <div class="bg-surface-container-lowest rounded-3xl shadow-sm p-6">
                    <h3 class="text-lg font-black tracking-tight mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-slate-400">local_shipping</span>
                        Registered Suppliers
                    </h3>
                    
                    @if($variant->suppliers->isEmpty())
                        <div class="text-slate-500 text-sm italic p-4 bg-slate-50 rounded-xl text-center border border-dashed border-slate-200">
                            No suppliers linked to this item.
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($variant->suppliers as $supplier)
                                <div class="p-3 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">
                                    <div class="font-bold text-slate-800 mb-1">{{ $supplier->name }}</div>
                                    <div class="flex justify-between items-center text-xs text-slate-500">
                                        <span>SKU: <span class="font-mono font-bold">{{ $supplier->pivot->supplier_sku ?? '-' }}</span></span>
                                        <span>Lead: <span class="font-bold text-emerald-600">{{ $supplier->pivot->lead_time_days ?? '?' }}d</span></span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                
                <!-- Secondary Barcodes -->
                <div class="bg-surface-container-lowest rounded-3xl shadow-sm p-6">
                    <h3 class="text-lg font-black tracking-tight mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-slate-400">qr_code</span>
                        All Barcodes
                    </h3>
                    <div class="space-y-2">
                        @foreach($variant->barcodes as $barcode)
                            <div class="flex justify-between items-center p-2 rounded-lg {{ $barcode->is_primary ? 'bg-primary/10 border border-primary/20' : 'bg-slate-50 border border-slate-100' }}">
                                <div class="font-mono text-sm font-bold {{ $barcode->is_primary ? 'text-primary' : 'text-slate-600' }}">
                                    {{ $barcode->barcode }}
                                </div>
                                <div class="flex gap-2 items-center">
                                    <span class="text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-full bg-slate-200 text-slate-600">{{ $barcode->type }}</span>
                                    @if($barcode->is_primary)
                                        <span class="material-symbols-outlined text-primary text-sm" title="Primary Barcode" style="font-variation-settings: 'FILL' 1;">star</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Right Column: Images & History -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Gallery Section -->
                <section class="bg-surface-container-lowest rounded-3xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-black tracking-tight flex items-center gap-2">
                            <span class="material-symbols-outlined text-slate-400">image</span>
                            Photo Gallery
                        </h3>
                        <span class="text-xs font-bold uppercase tracking-widest text-outline bg-slate-100 px-3 py-1 rounded-full">{{ $variant->images->count() }} IMAGES</span>
                    </div>
                    
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        @foreach($variant->images as $image)
                            <div class="aspect-square bg-slate-100 rounded-2xl overflow-hidden cursor-pointer relative group">
                                <img src="{{ asset('storage/' . $image->path) }}" alt="Item image" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110">
                                @if($image->is_primary)
                                    <div class="absolute top-2 left-2 bg-primary text-white text-[10px] font-black uppercase tracking-wider px-2 py-1 rounded-md shadow-sm">Main</div>
                                @endif
                            </div>
                        @endforeach
                        
                        <a href="{{ route('items.edit', $variant->id) }}#photos" class="aspect-square bg-slate-50 rounded-2xl border-2 border-dashed border-slate-300 flex flex-col items-center justify-center text-slate-400 hover:text-primary hover:border-primary hover:bg-primary/5 transition-all cursor-pointer">
                            <span class="material-symbols-outlined text-3xl mb-1">add_photo_alternate</span>
                            <span class="text-xs font-bold uppercase tracking-widest">Manage</span>
                        </a>
                    </div>
                </section>

                <!-- Movement History Mockup -->
                <section class="bg-surface-container-lowest rounded-3xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-black tracking-tight flex items-center gap-2">
                            <span class="material-symbols-outlined text-slate-400">sync_alt</span>
                            Recent Movements
                        </h3>
                        <button class="text-primary font-bold text-sm flex items-center gap-1 hover:bg-primary/10 px-3 py-1 rounded-lg transition-colors">
                            <span class="material-symbols-outlined text-sm">open_in_new</span> Full Report
                        </button>
                    </div>
                    
                    <div class="text-center py-12 border-2 border-dashed border-slate-200 rounded-2xl">
                        <span class="material-symbols-outlined text-5xl text-slate-300 mb-3">history_toggle_off</span>
                        <h4 class="text-slate-600 font-bold mb-1">Movement Log Integration Pending</h4>
                        <p class="text-sm text-slate-400">This item's in/out history will appear here once the Stock Movement module is fully linked.</p>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>
@endsection
