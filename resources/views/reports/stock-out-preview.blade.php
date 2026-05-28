@extends('layouts.app')

@section('content')
<div class="pt-[52px] px-md pb-md min-h-screen bg-slate-50/30 flex items-center justify-center">
    @php
        $tx = $transactions->first();
    @endphp

    @if($tx)
        <div class="w-full max-w-[550px] bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 md:p-8 shadow-xl animate-in fade-in zoom-in duration-300 space-y-6">
            
            <!-- Header section with success badge -->
            <div class="text-center space-y-3">
                <div class="w-14 h-14 bg-emerald-100 dark:bg-emerald-950/20 text-emerald-600 rounded-full flex items-center justify-center mx-auto shadow-sm">
                    <span class="material-symbols-outlined text-3xl font-black" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                </div>
                <div class="space-y-1">
                    <span class="inline-block bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 font-black text-[9px] uppercase tracking-widest px-2.5 py-0.5 rounded-full border border-emerald-200/50">
                        Stock Out Completed
                    </span>
                    <h1 class="text-xl font-black text-slate-900 dark:text-slate-100 tracking-tight mt-1">SPAREPART WAREHOUSE</h1>
                </div>
            </div>

            <div class="border-t border-b border-dashed border-slate-200 dark:border-slate-800 py-4 space-y-2">
                <div class="flex justify-between items-center text-xs">
                    <span class="text-slate-400 font-bold uppercase tracking-widest text-[9px]">Transaction Code</span>
                    <span class="font-mono font-black text-slate-850 dark:text-slate-200 text-xs">{{ $tx->code }}</span>
                </div>
                <div class="flex justify-between items-center text-xs">
                    <span class="text-slate-400 font-bold uppercase tracking-widest text-[9px]">Date &amp; Time</span>
                    <span class="font-bold text-slate-700 dark:text-slate-300">{{ $tx->created_at->format('d M Y H:i:s') }}</span>
                </div>
                <div class="flex justify-between items-center text-xs">
                    <span class="text-slate-400 font-bold uppercase tracking-widest text-[9px]">Department</span>
                    <span class="font-black text-slate-900 dark:text-slate-200 uppercase">{{ $tx->department->name ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between items-center text-xs">
                    <span class="text-slate-400 font-bold uppercase tracking-widest text-[9px]">Operator / PIC</span>
                    <span class="font-bold text-slate-700 dark:text-slate-300">{{ $tx->user->name ?? 'N/A' }}</span>
                </div>
                @if($tx->reference)
                <div class="flex justify-between items-center text-xs">
                    <span class="text-slate-400 font-bold uppercase tracking-widest text-[9px]">Reference</span>
                    <span class="font-bold text-slate-650 dark:text-slate-400 font-mono">{{ $tx->reference }}</span>
                </div>
                @endif
            </div>

            <!-- Item list section -->
            <div class="space-y-3">
                <h3 class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Ingested Items</h3>
                <div class="space-y-2.5 max-h-[250px] overflow-y-auto pr-1">
                    @foreach($tx->items as $item)
                        <div class="flex justify-between items-center bg-slate-50 dark:bg-slate-800/40 p-3 rounded-xl border border-slate-100 dark:border-slate-800/60">
                            <div class="min-w-0 flex-1 pr-4">
                                <span class="font-black text-xs text-slate-800 dark:text-slate-250 leading-snug block truncate">
                                    {{ strtoupper($item->item_name_snapshot ?? $item->variant->item->name ?? 'N/A') }}
                                </span>
                                <span class="text-[9px] font-mono text-slate-400 block mt-0.5">
                                    {{ $item->erp_code_snapshot ?? $item->variant->erp_code ?? 'N/A' }}
                                </span>
                            </div>
                            <span class="bg-slate-100 dark:bg-slate-850 text-slate-800 dark:text-slate-200 border border-slate-250/20 font-black text-xs px-2.5 py-1 rounded-lg shrink-0">
                                x{{ $item->qty }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Footer indicator -->
            <div class="flex items-center justify-center gap-1.5 bg-emerald-50/50 dark:bg-emerald-950/10 border border-emerald-100/50 py-2.5 px-4 rounded-xl text-[10px] text-emerald-600 dark:text-emerald-400 font-black uppercase tracking-wider">
                <span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1;">cloud_done</span>
                ERP Inventory Transaction Synced Successfully
            </div>

            <!-- Buttons section -->
            <div class="grid grid-cols-2 gap-3 pt-2">
                <button onclick="printThermal()" class="h-11 bg-green-600 hover:bg-green-700 text-white rounded-xl font-black text-xs uppercase tracking-widest shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-lg">print</span>
                    Print Receipt
                </button>
                <a href="{{ route('scan') }}" class="h-11 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-350 rounded-xl font-black text-xs uppercase tracking-widest shadow-sm transition-all flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-lg">add_box</span>
                    New Session
                </a>
            </div>

        </div>

        <script>
            function printThermal() {
                window.open(
                    '{{ route("reports.stock-out.print", ["code" => $tx->code]) }}',
                    '_blank',
                    'width=400,height=600'
                );
            }
        </script>
    @else
        <div class="w-full max-w-[500px] bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-8 shadow-xl text-center space-y-4">
            <div class="w-12 h-12 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto">
                <span class="material-symbols-outlined text-2xl">question_mark</span>
            </div>
            <h1 class="text-lg font-black text-slate-900 dark:text-white uppercase tracking-tight">No Transaction Found</h1>
            <p class="text-xs text-slate-500">The requested stock-out transaction code could not be resolved in our database records.</p>
            <a href="{{ route('scan') }}" class="inline-flex items-center justify-center px-6 h-10 bg-primary text-white font-black text-xs uppercase tracking-widest rounded-lg shadow-sm hover:translate-y-[-1px] active:translate-y-0 transition-all">
                Back to Scan
            </a>
        </div>
    @endif
</div>
@endsection
