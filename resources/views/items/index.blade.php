@extends('layouts.app')

@section('content')
<div class="pt-24 px-4 pb-6 lg:px-8 min-h-screen flex flex-col">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-black tracking-tight text-slate-900">Items Catalog</h1>
            <p class="text-sm font-bold text-slate-500 mt-1 uppercase tracking-widest">Master Data Management</p>
        </div>
        <div class="flex flex-wrap gap-3 w-full md:w-auto" x-data>
            <button @click="$dispatch('openImportModal')" class="flex-1 md:flex-none justify-center bg-surface-container-high text-primary px-5 py-3 rounded-2xl font-black text-sm flex items-center gap-2 hover:bg-slate-200 transition-colors active:scale-95 shadow-sm">
                <span class="material-symbols-outlined">upload_file</span>
                IMPORT
            </button>
            <a href="{{ route('items.create') }}" class="flex-1 md:flex-none justify-center bg-primary text-white px-6 py-3 rounded-2xl font-black text-sm flex items-center gap-2 shadow-lg shadow-primary/25 hover:-translate-y-0.5 hover:shadow-primary/40 active:translate-y-0 active:scale-95 transition-all">
                <span class="material-symbols-outlined">add</span>
                ADD ITEM
            </a>
        </div>
    </div>

    <!-- Livewire List Component -->
    <livewire:items.item-list />

    <!-- Import Modal -->
    <livewire:items.import-modal />
</div>
@endsection
