@extends('layouts.app')

@section('content')
<!-- Lightbox & Compact View Wrapper -->
<div x-data="lightboxStore()" 
     @keydown.escape.window="close()" 
     @keydown.arrow-left.window="prev()" 
     @keydown.arrow-right.window="next()"
     class="pt-24 px-4 pb-6 lg:px-8 bg-slate-50/30 min-h-screen">

    <!-- Hero / Compact Operational Identity Card -->
    <div class="max-w-7xl mx-auto mb-6 bg-surface-container-lowest border border-slate-200 rounded-lg p-4 shadow-sm flex flex-col md:flex-row gap-5 items-start relative">
        <!-- Compact Thumbnail -->
        <div class="flex-shrink-0 relative group">
            @php
                $primaryImage = $variant->images->where('is_primary', true)->first();
                $imagePath = $primaryImage ? asset('storage/' . $primaryImage->path) : null;
                $galleryImages = $variant->images->map(fn($img) => asset('storage/' . $img->path))->toArray();
            @endphp
            
            @if($imagePath)
                <div @click="openLightbox(0)" class="w-36 h-36 rounded-md overflow-hidden border border-slate-200 cursor-pointer shadow-sm relative group bg-slate-50">
                    <img class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" alt="{{ $variant->item->name }}" src="{{ $imagePath }}"/>
                    <div class="absolute inset-0 bg-black/10 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                        <span class="material-symbols-outlined text-white text-md bg-black/40 p-1.5 rounded-full">zoom_in</span>
                    </div>
                </div>
            @else
                <div class="w-36 h-36 rounded-md border border-slate-200 bg-slate-50 flex flex-col items-center justify-center text-slate-350 select-none">
                    <span class="material-symbols-outlined text-2xl mb-1">image_not_supported</span>
                    <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">No Photo</span>
                </div>
            @endif
        </div>

        <!-- Identity Specifications Panel -->
        <div class="flex-1 min-w-0 w-full">
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-3">
                <div>
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                        <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[10px] font-black rounded uppercase tracking-wider border border-slate-200">Asset Profile</span>
                        @if($variant->brand)
                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">{{ $variant->brand }}</span>
                        @endif
                    </div>
                    <h1 class="text-xl font-black tracking-tight text-slate-800 leading-tight">{{ $variant->item->name }}</h1>
                </div>
                
                <!-- Compact Action Toolbar -->
                <div class="flex items-center gap-1.5 flex-shrink-0 bg-slate-50 border border-slate-200 p-1 rounded-lg self-start">
                    <a href="{{ route('items.edit', $variant->id) }}" class="flex items-center justify-center w-8 h-8 rounded text-slate-500 hover:bg-slate-200 hover:text-slate-800 transition-colors" title="Edit Item">
                        <span class="material-symbols-outlined text-lg">edit</span>
                    </a>
                    <a href="{{ route('barcode.printing', ['searchString' => $variant->erp_code ?? $variant->sku]) }}" class="flex items-center justify-center w-8 h-8 rounded text-slate-500 hover:bg-slate-200 hover:text-slate-800 transition-colors" title="Print Barcode Label">
                        <span class="material-symbols-outlined text-lg">print</span>
                    </a>
                    <a href="#history" class="flex items-center justify-center w-8 h-8 rounded text-slate-500 hover:bg-slate-200 hover:text-slate-800 transition-colors" title="View History">
                        <span class="material-symbols-outlined text-lg">history</span>
                    </a>
                </div>
            </div>

            <!-- Monospace Operational Matrix -->
            @php
                $currentStock = $variant->bins->sum('current_qty');
                $mainBin = $variant->bins->first();
            @endphp
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-y-1.5 gap-x-6 text-[11px] font-mono text-slate-600 border-t border-slate-100 pt-3">
                <div class="flex items-center py-0.5 border-b border-dashed border-slate-100 sm:border-none">
                    <span class="w-24 text-slate-400 font-bold uppercase tracking-wider flex-shrink-0">ERP CODE</span>
                    <span class="font-black text-slate-800 bg-slate-100 px-2 py-0.5 rounded border border-slate-200/60 break-all select-all">{{ $variant->erp_code ?? '-' }}</span>
                </div>
                <div class="flex items-center py-0.5 border-b border-dashed border-slate-100 sm:border-none">
                    <span class="w-24 text-slate-400 font-bold uppercase tracking-wider flex-shrink-0">SKU</span>
                    <span class="font-black text-slate-800 select-all">{{ $variant->sku ?? '-' }}</span>
                </div>
                <div class="flex items-center py-0.5 border-b border-dashed border-slate-100 sm:border-none">
                    <span class="w-24 text-slate-400 font-bold uppercase tracking-wider flex-shrink-0">UNIT</span>
                    <span class="font-black text-slate-800">{{ $variant->unit ?? 'PCS' }}</span>
                </div>
                <div class="flex items-center py-0.5 border-b border-dashed border-slate-100 sm:border-none">
                    <span class="w-24 text-slate-400 font-bold uppercase tracking-wider flex-shrink-0">LOCATION</span>
                    <span class="font-black text-blue-600 bg-blue-50 px-2 py-0.5 rounded border border-blue-100/60 uppercase">{{ $mainBin ? $mainBin->code : 'NOT STORED' }}</span>
                </div>
                <div class="flex items-center py-0.5">
                    <span class="w-24 text-slate-400 font-bold uppercase tracking-wider flex-shrink-0">TOTAL QTY</span>
                    <span class="font-black text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100/60">{{ number_format($currentStock) }} {{ $variant->unit ?? 'PCS' }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Compact Horizontal KPI Strip -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6 max-w-7xl mx-auto">
        <div class="bg-surface-container-lowest border border-slate-200 p-3 rounded-lg shadow-sm flex items-center gap-3">
            <div class="w-9 h-9 bg-emerald-50 rounded-lg flex items-center justify-center border border-emerald-100 flex-shrink-0">
                <span class="material-symbols-outlined text-emerald-650 text-md">inventory_2</span>
            </div>
            <div class="min-w-0">
                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Current Stock</p>
                <p class="text-sm font-black text-emerald-600 truncate">{{ number_format($currentStock) }} <span class="text-[10px] font-bold text-slate-400 font-mono">{{ $variant->unit ?? 'PCS' }}</span></p>
            </div>
        </div>
        
        <div class="bg-surface-container-lowest border border-slate-200 p-3 rounded-lg shadow-sm flex items-center gap-3">
            <div class="w-9 h-9 bg-blue-50 rounded-lg flex items-center justify-center border border-blue-100 flex-shrink-0">
                <span class="material-symbols-outlined text-blue-650 text-md">location_on</span>
            </div>
            <div class="min-w-0">
                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Main Location</p>
                <p class="text-sm font-black text-slate-800 truncate font-mono uppercase">{{ $mainBin ? $mainBin->code : 'NOT STORED' }}</p>
            </div>
        </div>

        <div class="bg-surface-container-lowest border border-slate-200 p-3 rounded-lg shadow-sm flex items-center gap-3">
            <div class="w-9 h-9 bg-purple-50 rounded-lg flex items-center justify-center border border-purple-100 flex-shrink-0">
                <span class="material-symbols-outlined text-purple-650 text-md">verified</span>
            </div>
            <div class="min-w-0">
                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Last Audit</p>
                <p class="text-sm font-black text-purple-600 truncate font-mono">
                    {{ $variant->last_opname_at ? \Carbon\Carbon::parse($variant->last_opname_at)->format('d M Y') : 'NEVER' }}
                </p>
            </div>
        </div>
        
        <div class="bg-surface-container-lowest border border-slate-200 p-3 rounded-lg shadow-sm flex items-center gap-3">
            <div class="w-9 h-9 bg-amber-50 rounded-lg flex items-center justify-center border border-amber-100 flex-shrink-0">
                <span class="material-symbols-outlined text-amber-650 text-md">local_shipping</span>
            </div>
            <div class="min-w-0">
                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Suppliers</p>
                <p class="text-sm font-black text-amber-600 truncate font-mono">{{ $variant->suppliers->count() }} <span class="text-[10px] font-bold text-slate-400">VENDORS</span></p>
            </div>
        </div>
    </div>

    <!-- Main Content: Dense Two-Column Split Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 max-w-7xl mx-auto">
        <!-- Left Side: Detail Attributes & Registered Codes (Col Span 1) -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Description Card -->
            <div class="bg-surface-container-lowest border border-slate-200 rounded-lg shadow-sm p-4">
                <h3 class="text-xs font-black uppercase tracking-widest mb-3 flex items-center gap-2 text-slate-550 border-b border-slate-100 pb-2">
                    <span class="material-symbols-outlined text-slate-400 text-md">description</span>
                    Description
                </h3>
                <p class="text-slate-600 text-[11px] leading-relaxed whitespace-pre-line bg-slate-50 border border-slate-200/60 p-3 rounded-lg font-bold">
                    {{ $variant->description ?? 'No description provided.' }}
                </p>
            </div>

            <!-- Inventory Planning Metadata Card -->
            <div class="bg-surface-container-lowest border border-slate-200 rounded-lg shadow-sm p-4">
                <h3 class="text-xs font-black uppercase tracking-widest mb-3 flex items-center gap-2 text-slate-550 border-b border-slate-100 pb-2">
                    <span class="material-symbols-outlined text-purple-500 text-md">assignment</span>
                    Inventory Planning
                </h3>
                <div class="grid grid-cols-2 gap-3 text-[11px] font-mono text-slate-655 font-bold">
                    <div class="bg-slate-50 border border-slate-200/60 p-2.5 rounded-lg flex flex-col gap-1">
                        <span class="text-slate-400 font-bold uppercase tracking-wider text-[9px]">Procurement</span>
                        <span class="font-black text-slate-800 uppercase flex items-center gap-1">
                            @if(($variant->procurement_type ?? 'LOCAL') === 'LOCAL')
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Local
                            @else
                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span> Import
                            @endif
                        </span>
                    </div>
                    <div class="bg-slate-50 border border-slate-200/60 p-2.5 rounded-lg flex flex-col gap-1">
                        <span class="text-slate-400 font-bold uppercase tracking-wider text-[9px]">Inventory Class</span>
                        <span class="font-black text-slate-800 uppercase flex items-center gap-1">
                            @if(($variant->inventory_class ?? 'CONSUMABLE') === 'CONSUMABLE')
                                <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> Consumable
                            @else
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Sparepart
                            @endif
                        </span>
                    </div>
                    <div class="bg-slate-50 border border-slate-200/60 p-2.5 rounded-lg flex flex-col gap-1">
                        <span class="text-slate-400 font-bold uppercase tracking-wider text-[9px]">Lead Time</span>
                        <span class="font-black text-slate-850 text-xs">{{ $variant->lead_time_days ?? 30 }} Days</span>
                    </div>
                    <div class="bg-slate-50 border border-slate-200/60 p-2.5 rounded-lg flex flex-col gap-1">
                        <span class="text-slate-400 font-bold uppercase tracking-wider text-[9px]">Days Left</span>
                        <span class="font-black text-slate-850 text-xs">
                            @if($daysLeft === null)
                                <span class="text-slate-400 font-bold text-[11px]">—</span>
                            @else
                                {{ number_format($daysLeft, 1) }} Days
                            @endif
                        </span>
                    </div>
                    <div class="bg-slate-50 border border-slate-200/60 p-2.5 rounded-lg flex flex-col gap-1">
                        <span class="text-slate-400 font-bold uppercase tracking-wider text-[9px]">Weekly Average (28d)</span>
                        <span class="font-black text-slate-850 text-xs">{{ number_format($weeklyAvg, 2) }}</span>
                    </div>
                    <div class="bg-slate-50 border border-slate-200/60 p-2.5 rounded-lg flex flex-col gap-1">
                        <span class="text-slate-400 font-bold uppercase tracking-wider text-[9px]">Monthly Average (90d)</span>
                        <span class="font-black text-slate-850 text-xs">{{ number_format($monthlyAvg, 2) }}</span>
                    </div>
                    <div class="bg-slate-50 border border-slate-200/60 p-2.5 rounded-lg flex flex-col gap-1">
                        <span class="text-slate-400 font-bold uppercase tracking-wider text-[9px]">Six Month Average (180d)</span>
                        <span class="font-black text-slate-850 text-xs">{{ number_format($sixMonthAvg, 2) }}</span>
                    </div>
                    <div class="bg-slate-50 border border-slate-200/60 p-2.5 rounded-lg flex flex-col gap-1">
                        <span class="text-slate-400 font-bold uppercase tracking-wider text-[9px]">Trend</span>
                        <span class="font-black text-xs uppercase flex items-center gap-1">
                            @if($trend === 'Increasing')
                                <span class="text-emerald-600 flex items-center gap-1 text-[10px]">Increasing ↑</span>
                            @elseif($trend === 'Decreasing')
                                <span class="text-rose-600 flex items-center gap-1 text-[10px]">Decreasing ↓</span>
                            @else
                                <span class="text-slate-500 flex items-center gap-1 text-[10px]">Stable →</span>
                            @endif
                        </span>
                    </div>
                    <div class="bg-slate-50 border border-slate-200/60 p-2.5 rounded-lg flex flex-col gap-1">
                        <span class="text-slate-400 font-bold uppercase tracking-wider text-[9px]">Planning Status</span>
                        <span class="font-black text-xs uppercase flex items-center gap-1">
                            @if($healthStatus === 'CRITICAL')
                                <span class="text-rose-650 flex items-center gap-1 text-[10px] animate-pulse">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> CRITICAL
                                </span>
                            @elseif($healthStatus === 'REORDER NOW')
                                <span class="text-amber-600 flex items-center gap-1 text-[10px]">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> REORDER NOW
                                </span>
                            @elseif($healthStatus === 'WATCHLIST')
                                <span class="text-yellow-700 flex items-center gap-1 text-[10px]">
                                    <span class="w-1.5 h-1.5 rounded-full bg-yellow-500"></span> WATCHLIST
                                </span>
                            @elseif($healthStatus === 'HEALTHY')
                                <span class="text-emerald-650 flex items-center gap-1 text-[10px]">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> HEALTHY
                                </span>
                            @else
                                <span class="text-slate-500 flex items-center gap-1 text-[10px]">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> NO CONSUMPTION
                                </span>
                            @endif
                        </span>
                    </div>
                    <div class="bg-slate-50 border border-slate-200/60 p-2.5 rounded-lg flex flex-col gap-1">
                        <span class="text-slate-400 font-bold uppercase tracking-wider text-[9px]">Projected Empty Date</span>
                        <span class="font-black text-slate-850 text-xs">{{ $projectedEmptyDate ?? '—' }}</span>
                    </div>
                </div>
            </div>

            <!-- Registered Barcodes (Scanner-First) -->
            <div class="bg-surface-container-lowest border border-slate-200 rounded-lg shadow-sm p-4">
                <h3 class="text-xs font-black uppercase tracking-widest mb-3 flex items-center gap-2 text-slate-550 border-b border-slate-100 pb-2">
                    <span class="material-symbols-outlined text-slate-400 text-md">qr_code</span>
                    Registered Barcodes
                </h3>
                <div class="grid grid-cols-1 gap-1.5 max-h-[160px] overflow-y-auto pr-1">
                    @foreach($variant->barcodes as $barcode)
                        <div class="flex justify-between items-center p-2 rounded {{ $barcode->is_primary ? 'bg-primary/5 border border-primary/20' : 'bg-slate-50 border border-slate-200/60' }}">
                            <div class="font-mono text-[11px] font-black {{ $barcode->is_primary ? 'text-primary' : 'text-slate-700' }} tracking-wide select-all">
                                {{ $barcode->barcode }}
                            </div>
                            <div class="flex gap-1.5 items-center">
                                <span class="text-[9px] font-black uppercase tracking-widest px-1.5 py-0.5 rounded bg-slate-200 text-slate-500 font-mono">{{ $barcode->type }}</span>
                                @if($barcode->is_primary)
                                    <span class="material-symbols-outlined text-primary text-xs" title="Primary Barcode" style="font-variation-settings: 'FILL' 1;">star</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Compact Supplier Registry -->
            <div class="bg-surface-container-lowest border border-slate-200 rounded-lg shadow-sm p-4">
                <h3 class="text-xs font-black uppercase tracking-widest mb-3 flex items-center gap-2 text-slate-550 border-b border-slate-100 pb-2">
                    <span class="material-symbols-outlined text-slate-400 text-md">local_shipping</span>
                    Registered Suppliers
                </h3>
                
                @if($variant->suppliers->isEmpty())
                    <div class="text-slate-400 text-[10.5px] italic p-3 bg-slate-50 rounded-lg text-center border border-dashed border-slate-200 font-bold">
                        No registered suppliers linked.
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-1.5 max-h-[180px] overflow-y-auto pr-1">
                        @foreach($variant->suppliers as $supplier)
                            <div class="p-2 border border-slate-200/60 rounded bg-slate-50 flex items-center justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="font-black text-[11px] text-slate-700 truncate leading-tight">{{ $supplier->name }}</div>
                                    <div class="text-[9px] text-slate-400 font-mono leading-none mt-0.5">VNDR ID: {{ $supplier->id }}</div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0 text-[10px]">
                                    <span class="font-mono text-slate-500 bg-slate-200/60 px-1.5 py-0.5 rounded font-bold">SKU: <span class="font-black text-slate-700">{{ $supplier->pivot->supplier_sku ?? '-' }}</span></span>
                                    <span class="font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-100/60">Lead: {{ $supplier->pivot->lead_time_days ?? '?' }}d</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- Right Side: Gallery & Movements (Col Span 2) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Compact Photo Gallery -->
            <section class="bg-surface-container-lowest border border-slate-200 rounded-lg shadow-sm p-4">
                <div class="flex items-center justify-between mb-3 border-b border-slate-100 pb-2">
                    <h3 class="text-xs font-black uppercase tracking-widest flex items-center gap-2 text-slate-550">
                        <span class="material-symbols-outlined text-slate-400 text-md">image</span>
                        Photo Gallery
                    </h3>
                    <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded-full">{{ $variant->images->count() }} IMAGES</span>
                </div>
                
                <div class="grid grid-cols-4 sm:grid-cols-6 gap-2">
                    @foreach($variant->images as $index => $image)
                        <div @click="openLightbox({{ $index }})" class="aspect-square bg-slate-50 border border-slate-200 rounded-md overflow-hidden cursor-pointer relative group">
                            <img src="{{ asset('storage/' . $image->path) }}" alt="Item image" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105">
                            @if($image->is_primary)
                                <div class="absolute top-1 left-1 bg-primary text-white text-[8px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded-sm shadow-sm leading-none">Main</div>
                            @endif
                        </div>
                    @endforeach
                    
                    <a href="{{ route('items.edit', $variant->id) }}#photos" class="aspect-square bg-slate-50 rounded-md border border-dashed border-slate-350 flex flex-col items-center justify-center text-slate-400 hover:text-primary hover:border-primary hover:bg-primary/5 transition-all cursor-pointer select-none">
                        <span class="material-symbols-outlined text-md mb-0.5">add_photo_alternate</span>
                        <span class="text-[8px] font-black uppercase tracking-widest">Manage</span>
                    </a>
                </div>

            <!-- Dense Recent Movements -->
            <section class="bg-surface-container-lowest border border-slate-200 rounded-lg shadow-sm p-4" id="history">
                <div class="flex items-center justify-between mb-3 border-b border-slate-100 pb-2">
                    <h3 class="text-xs font-black uppercase tracking-widest flex items-center gap-2 text-slate-550">
                        <span class="material-symbols-outlined text-slate-400 text-md">sync_alt</span>
                        Recent Movements
                    </h3>
                    <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded-full font-mono font-bold">LOG ENGINE</span>
                </div>
                
                @if($movements->isEmpty())
                    <div class="text-center py-6 border border-dashed border-slate-200 rounded-lg bg-slate-50/50">
                        <span class="material-symbols-outlined text-2xl text-slate-350 mb-1">history_toggle_off</span>
                        <h4 class="text-slate-700 text-[11px] font-black uppercase tracking-wider mb-0.5">No Movements Recorded</h4>
                        <p class="text-[9.5px] text-slate-400 max-w-[420px] mx-auto leading-relaxed font-bold">This item has no recorded stock in, out, or adjustment movements.</p>
                    </div>
                @else
                    <!-- Monospace Stream -->
                    <div class="space-y-1 max-h-[280px] overflow-y-auto pr-1">
                        @foreach($movements as $mov)
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between py-1.5 px-2 rounded hover:bg-slate-50 border-b border-slate-100 text-slate-650 font-mono text-[10.5px] leading-none gap-1 sm:gap-2">
                                
                                <div class="flex items-center gap-2 flex-wrap">
                                    <!-- Fixed Timestamp -->
                                    <span class="text-slate-400 select-none flex-shrink-0" style="width: 90px;">
                                        {{ $mov->created_at->format('d M H:i') }}
                                    </span>
                                    
                                    <!-- Soft Subtle Badge -->
                                    <span class="flex-shrink-0 text-center" style="width: 42px;">
                                        @if($mov->type === 'IN')
                                            <span class="inline-block px-1.5 py-0.5 bg-emerald-50 text-emerald-700 font-bold rounded border border-emerald-100 text-[9px] uppercase tracking-wide">IN</span>
                                        @elseif($mov->type === 'OUT')
                                            <span class="inline-block px-1.5 py-0.5 bg-rose-50 text-rose-700 font-bold rounded border border-rose-100 text-[9px] uppercase tracking-wide">OUT</span>
                                        @elseif($mov->type === 'ADJUSTMENT')
                                            <span class="inline-block px-1.5 py-0.5 bg-purple-50 text-purple-700 font-bold rounded border border-purple-100 text-[9px] uppercase tracking-wide">ADJ</span>
                                        @elseif($mov->type === 'REVERSAL')
                                            <span class="inline-block px-1.5 py-0.5 bg-slate-100 text-slate-600 font-bold rounded border border-slate-200 text-[9px] uppercase tracking-wide">REV</span>
                                        @else
                                            <span class="inline-block px-1.5 py-0.5 bg-blue-50 text-blue-700 font-bold rounded border border-blue-100 text-[9px] uppercase tracking-wide">{{ $mov->type }}</span>
                                        @endif
                                    </span>
                                    
                                    <!-- Fixed Location -->
                                    <span class="font-bold text-slate-500 flex-shrink-0 uppercase" style="width: 80px;">
                                        [{{ $mov->bin ? $mov->bin->code : 'UNASSIGND' }}]
                                    </span>
                                </div>

                                <div class="flex items-center gap-4 flex-grow w-full sm:w-auto justify-between sm:justify-end">
                                    <!-- Truncated Reference -->
                                    <span class="text-slate-400 truncate flex-grow text-left sm:max-w-[220px]" title="{{ $mov->reference }}">
                                        @if($mov->supplier)
                                            {{ $mov->reference ?: 'STOCK_IN' }} ({{ $mov->supplier->name }})
                                        @else
                                            {{ $mov->reference ?: 'SYSTEM_GEN' }}
                                        @endif
                                    </span>
                                    
                                    <div class="flex items-center gap-3 flex-shrink-0">
                                        <!-- Qty (Right Aligned, Strong Emphasis) -->
                                        <span class="font-black text-right text-[11px] tracking-wide" style="width: 50px;">
                                            @if($mov->qty > 0)
                                                <span class="text-emerald-600">+{{ number_format($mov->qty) }}</span>
                                            @elseif($mov->qty < 0)
                                                <span class="text-rose-600">{{ number_format($mov->qty) }}</span>
                                            @else
                                                <span class="text-slate-400">0</span>
                                            @endif
                                        </span>
                                        
                                        <!-- Operator (Fixed Width) -->
                                        <span class="text-slate-400 font-bold text-right truncate uppercase" style="width: 65px;" title="Operator: {{ $mov->operator ? $mov->operator->name : ($mov->created_by ?: 'System') }}">
                                            {{ substr($mov->operator ? $mov->operator->name : ($mov->created_by ?: 'System'), 0, 8) }}
                                        </span>
                                    </div>
                                </div>
                                
                            </div>
                        @endforeach
                    </div>
                    
                    <!-- View Full History (Subtle Industrial) -->
                    <div class="mt-3 pt-2 border-t border-slate-100 flex justify-end">
                        <button type="button" 
                                onclick="alert('Full History module is scheduled for Phase 2 implementation. The current direct logs contain all transactional records.')"
                                class="flex items-center gap-1 px-2.5 py-1 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-500 hover:text-slate-700 text-[9px] font-black uppercase tracking-wider rounded transition-colors active:scale-95">
                            <span class="material-symbols-outlined text-[12px] font-bold">history</span>
                            View Full History
                        </button>
                    </div>
                @endif
            </section>
        </div>
    </div>

    <!-- Fullscreen Lightbox Modal (Alpine.js Built-in) -->
    <div x-show="open" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="fixed inset-0 z-50 flex flex-col items-center justify-center bg-black/90 backdrop-blur-sm p-4" 
         style="display: none;">
         
        <!-- Close button (>44px click target) -->
        <button @click="close()" class="absolute top-4 right-4 w-12 h-12 flex items-center justify-center text-white/70 hover:text-white bg-black/40 hover:bg-black/60 rounded-full transition-colors z-50">
            <span class="material-symbols-outlined text-2xl">close</span>
        </button>
        
        <!-- Left Arrow (>44px click target) -->
        <button x-show="images.length > 1" @click="prev()" class="absolute left-4 top-1/2 -translate-y-1/2 w-12 h-12 flex items-center justify-center text-white/70 hover:text-white bg-black/40 hover:bg-black/60 rounded-full transition-colors z-50">
            <span class="material-symbols-outlined text-2xl">arrow_back</span>
        </button>
        
        <!-- Right Arrow (>44px click target) -->
        <button x-show="images.length > 1" @click="next()" class="absolute right-4 top-1/2 -translate-y-1/2 w-12 h-12 flex items-center justify-center text-white/70 hover:text-white bg-black/40 hover:bg-black/60 rounded-full transition-colors z-50">
            <span class="material-symbols-outlined text-2xl">arrow_forward</span>
        </button>
        
        <!-- Center Image Display -->
        <div class="max-w-4xl max-h-[75vh] w-full flex items-center justify-center p-4">
            <img :src="images[activeIndex]" class="max-w-full max-h-[75vh] object-contain rounded shadow-2xl border border-white/10 select-none">
        </div>
        
        <!-- Bottom Thumbnail Strip -->
        <div x-show="images.length > 1" class="flex gap-2 mt-4 max-w-full overflow-x-auto p-2 bg-black/30 rounded-xl">
            <template x-for="(img, idx) in images" :key="idx">
                <div @click="activeIndex = idx" 
                     :class="{'border-primary scale-105': activeIndex === idx, 'border-white/20': activeIndex !== idx}"
                     class="w-12 h-12 rounded border-2 overflow-hidden cursor-pointer transition-all flex-shrink-0 bg-slate-900">
                    <img :src="img" class="w-full h-full object-cover">
                </div>
            </template>
        </div>
    </div>
</div>

<script>
    function lightboxStore() {
        return {
            open: false,
            activeIndex: 0,
            images: {!! json_encode($galleryImages) !!},
            openLightbox(index) {
                if (this.images.length === 0) return;
                this.activeIndex = index;
                this.open = true;
            },
            close() {
                this.open = false;
            },
            next() {
                if (this.images.length === 0) return;
                this.activeIndex = (this.activeIndex + 1) % this.images.length;
            },
            prev() {
                if (this.images.length === 0) return;
                this.activeIndex = (this.activeIndex - 1 + this.images.length) % this.images.length;
            }
        }
    }
</script>
@endsection
