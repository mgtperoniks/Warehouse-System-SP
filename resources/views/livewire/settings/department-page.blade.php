<div class="pt-[52px] px-md pb-md">
    <div class="max-w-7xl mx-auto">
        <!-- Header Section -->
        <div class="mb-sm flex flex-col md:flex-row md:items-center justify-between gap-sm">
            <div>
                <h2 class="text-xl font-black tracking-tighter text-on-surface uppercase mb-0.5">Departments</h2>
                <p class="text-slate-500 font-bold uppercase tracking-widest text-[10px]">Manage organizational units and cost centers</p>
            </div>
            
            @if(session()->has('message'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(function() { show = false; }, 3000)" class="bg-emerald-500 text-white px-4 py-1.5 rounded-md shadow-md border-b-2 border-emerald-700 flex items-center gap-2 animate-bounce">
                    <span class="material-symbols-outlined text-sm">check_circle</span>
                    <span class="font-bold text-xs">{{ session('message') }}</span>
                </div>
            @endif

            @if(session()->has('error'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(function() { show = false; }, 5000)" class="bg-red-500 text-white px-4 py-1.5 rounded-md shadow-md border-b-2 border-red-700 flex items-center gap-2 animate-bounce">
                    <span class="material-symbols-outlined text-sm">error</span>
                    <span class="font-bold text-xs">{{ session('error') }}</span>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-md">
            <!-- Left: Form -->
            <div class="lg:col-span-4">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md p-sm shadow-sm sticky top-[64px]">
                    <h3 class="text-xs font-black tracking-tight mb-sm flex items-center gap-2 uppercase text-slate-700 dark:text-slate-350">
                        <span class="material-symbols-outlined text-primary text-lg">{{ $editingId ? 'edit_note' : 'add_circle' }}</span>
                        {{ $editingId ? 'Edit Department' : 'Create Department' }}
                    </h3>

                    <form wire:submit.prevent="save" class="space-y-sm">
                        <div class="space-y-1">
                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">Department Name</label>
                            <input wire:model="name" type="text" class="w-full h-9 px-3 py-1.5 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md focus:ring-1 focus:ring-primary/20 focus:border-primary font-bold text-xs text-on-surface transition-all" placeholder="e.g. Production Engineering">
                            @error('name') <span class="text-red-500 text-[10px] font-bold ml-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="space-y-1">
                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">Code / Alias</label>
                            <input wire:model="code" type="text" class="w-full h-9 px-3 py-1.5 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md focus:ring-1 focus:ring-primary/20 focus:border-primary font-bold text-xs text-on-surface transition-all uppercase" placeholder="e.g. PROD-ENG">
                            @error('code') <span class="text-red-500 text-[10px] font-bold ml-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="space-y-1">
                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">Status</label>
                            <select wire:model="is_active" class="w-full h-9 px-3 py-1 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md focus:ring-1 focus:ring-primary/20 focus:border-primary font-bold text-xs text-on-surface transition-all">
                                <option value="1">ACTIVE</option>
                                <option value="0">INACTIVE</option>
                            </select>
                            @error('is_active') <span class="text-red-500 text-[10px] font-bold ml-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex gap-sm pt-3 border-t border-slate-100 dark:border-slate-800">
                            <button type="submit" class="h-9 flex-1 bg-primary text-white rounded-md font-black text-xs uppercase tracking-widest hover:brightness-110 active:scale-95 transition-all flex items-center justify-center gap-2 shadow-sm">
                                <span class="material-symbols-outlined text-sm">{{ $editingId ? 'save' : 'add' }}</span>
                                {{ $editingId ? 'Update' : 'Create' }}
                            </button>
                            
                            @if($editingId)
                                <button type="button" wire:click="cancelEdit" class="h-9 px-3 bg-slate-100 border border-slate-200 dark:border-slate-800 text-slate-660 rounded-md font-bold text-[10px] uppercase tracking-widest hover:bg-slate-200 active:scale-95 transition-all flex items-center justify-center">
                                    Cancel
                                </button>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right: Table -->
            <div class="lg:col-span-8 flex flex-col gap-sm">
                <!-- Search Box -->
                <div class="h-9 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md px-md shadow-sm flex items-center gap-2">
                    <span class="material-symbols-outlined text-slate-400 text-lg">search</span>
                    <input wire:model.live.debounce.300ms="search" type="text" class="flex-1 bg-transparent border-none outline-none focus:ring-0 font-bold text-xs text-slate-800 dark:text-slate-100" placeholder="Search departments...">
                </div>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md shadow-sm overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800">
                                <th class="px-md py-1.5 text-[9px] font-black uppercase tracking-widest text-slate-400">ID</th>
                                <th class="px-md py-1.5 text-[9px] font-black uppercase tracking-widest text-slate-400">Department</th>
                                <th class="px-md py-1.5 text-[9px] font-black uppercase tracking-widest text-slate-400">Code</th>
                                <th class="px-md py-1.5 text-[9px] font-black uppercase tracking-widest text-slate-400">Status</th>
                                <th class="px-md py-1.5 text-[9px] font-black uppercase tracking-widest text-slate-400 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-slate-800/50">
                            @forelse($departments as $dept)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                    <td class="px-md py-1.5 text-xs font-bold text-slate-400">{{ $dept->id }}</td>
                                    <td class="px-md py-1.5">
                                        <div class="font-black text-on-surface text-xs leading-tight">{{ $dept->name }}</div>
                                    </td>
                                    <td class="px-md py-1.5">
                                        <span class="bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded text-[10px] font-black text-slate-600 dark:text-slate-330 uppercase">{{ $dept->code }}</span>
                                    </td>
                                    <td class="px-md py-1.5">
                                        @if($dept->is_active)
                                            <span class="bg-emerald-100 dark:bg-emerald-900/40 px-2 py-0.5 rounded text-[9px] font-black text-emerald-700 dark:text-emerald-350 uppercase tracking-wider">ACTIVE</span>
                                        @else
                                            <span class="bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded text-[9px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-wider">INACTIVE</span>
                                        @endif
                                    </td>
                                    <td class="px-md py-1.5 text-right">
                                        <div class="flex justify-end gap-1">
                                            <button wire:click="edit({{ $dept->id }})" class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-primary transition-colors">
                                                <span class="material-symbols-outlined text-[18px]">edit</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-md py-12 text-center text-slate-400">
                                        <span class="material-symbols-outlined text-3xl mb-1 opacity-20">corporate_fare</span>
                                        <p class="font-bold text-xs">No departments found</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="p-sm border-t border-slate-50 dark:border-slate-800">
                        {{ $departments->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
