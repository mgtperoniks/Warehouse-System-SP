@extends('layouts.app')

@section('content')
<main class="pt-24 px-4 pb-12 lg:px-8 min-h-screen">
    <div class="max-w-7xl mx-auto">
        <!-- Page Header -->
        <div class="mb-8">
            <a href="{{ route('items') }}" class="inline-flex items-center text-sm font-bold text-slate-500 hover:text-primary transition-colors mb-2">
                <span class="material-symbols-outlined text-sm mr-1">arrow_back</span>
                Back to Items
            </a>
            <h2 class="text-3xl font-extrabold tracking-tight text-on-surface">
                {{ $mode === 'create' ? 'Register New Item' : 'Edit Item Identity' }}
            </h2>
            <p class="text-on-surface-variant mt-1">Provide master data details, scan barcodes, and upload reference photos.</p>
        </div>

        <livewire:items.item-form :mode="$mode" :variant="$variant ?? null" />
    </div>
</main>
@endsection
