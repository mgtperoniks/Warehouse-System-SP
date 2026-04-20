<div class="pt-24 px-4 pb-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <!-- Header Section -->
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-3xl font-black tracking-tighter text-on-surface uppercase mb-1">Departments</h2>
                <p class="text-slate-500 font-bold uppercase tracking-widest text-xs">Manage organizational units and cost centers</p>
            </div>
            
            @if(session()->has('message'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(function() { show = false; }, 3000)" class="bg-emerald-500 text-white px-6 py-3 rounded-2xl shadow-lg border-b-4 border-emerald-700 flex items-center gap-3 animate-bounce">
                    <span class="material-symbols-outlined">check_circle</span>
                    <span class="font-bold text-sm">{{ session('message') }}</span>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Left: Form -->
            <div class="lg:col-span-4">
                <div class="bg-surface-container-lowest rounded-3xl p-6 shadow-sm border-t-4 border-primary sticky top-24">
                    <h3 class="text-lg font-black tracking-tight mb-6 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">{{ $editingId ? 'edit_note' : 'add_circle' }}</span>
                        {{ $editingId ? 'Edit Department' : 'Create New Department' }}
                    </h3>

                    <form wire:submit.prevent="save" class="space-y-5">
                        <div class="space-y-2">
                            <label class="text-xs font-black uppercase tracking-widest text-slate-500 ml-1">Department Name</label>
                            <input wire:model="name" type="text" class="w-full px-4 py-3 bg-slate-100 border-none rounded-xl focus:ring-2 focus:ring-primary font-bold text-sm" placeholder="e.g. Production Engineering">
                            @error('name') <span class="text-red-500 text-[10px] font-bold ml-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-black uppercase tracking-widest text-slate-500 ml-1">Code / Alias</label>
                            <input wire:model="code" type="text" class="w-full px-4 py-3 bg-slate-100 border-none rounded-xl focus:ring-2 focus:ring-primary font-bold text-sm uppercase" placeholder="e.g. PROD-ENG">
                            @error('code') <span class="text-red-500 text-[10px] font-bold ml-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex gap-3 pt-4 border-t border-slate-100">
                            <button type="submit" class="flex-1 bg-primary text-white py-3 rounded-xl font-black text-sm hover:brightness-110 active:scale-95 transition-all flex items-center justify-center gap-2 shadow-sm">
                                <span class="material-symbols-outlined text-[20px]">{{ $editingId ? 'save' : 'add' }}</span>
                                {{ $editingId ? 'Update' : 'Create' }}
                            </button>
                            
                            @if($editingId)
                                <button type="button" wire:click="cancelEdit" class="px-4 bg-slate-200 text-slate-600 rounded-xl font-bold text-sm hover:bg-slate-300 transition-colors">
                                    Cancel
                                </button>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right: Table -->
            <div class="lg:col-span-8 flex flex-col gap-6">
                <!-- Search Box -->
                <div class="bg-surface-container-lowest rounded-3xl p-4 shadow-sm flex items-center gap-4 border-l-4 border-primary">
                    <span class="material-symbols-outlined text-slate-400 ml-2">search</span>
                    <input wire:model.live.debounce.300ms="search" type="text" class="flex-1 bg-transparent border-none focus:ring-0 font-bold text-sm" placeholder="Search departments...">
                </div>

                <div class="bg-surface-container-lowest rounded-3xl shadow-sm overflow-hidden border border-slate-100">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">ID</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">Department</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">Code</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @forelse($departments as $dept)
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4 text-xs font-bold text-slate-400">{{ $dept->id }}</td>
                                    <td class="px-6 py-4">
                                        <div class="font-black text-on-surface text-sm">{{ $dept->name }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="bg-slate-100 px-2 py-1 rounded-lg text-xs font-black text-slate-600">{{ $dept->code }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button wire:click="edit({{ $dept->id }})" class="p-2 text-slate-400 hover:text-primary transition-colors">
                                                <span class="material-symbols-outlined text-[18px]">edit</span>
                                            </button>
                                            <button wire:confirm="Are you sure you want to delete this department?" wire:click="delete({{ $dept->id }})" class="p-2 text-slate-400 hover:text-red-500 transition-colors">
                                                <span class="material-symbols-outlined text-[18px]">delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-slate-400">
                                        <span class="material-symbols-outlined text-4xl mb-2 opacity-20">corporate_fare</span>
                                        <p class="font-bold text-sm">No departments found</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="p-4 border-t border-slate-50">
                        {{ $departments->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
