<div class="pt-[52px] px-md pb-md">
    <div class="max-w-7xl mx-auto">
        <!-- Header Section -->
        <div class="mb-sm flex flex-col md:flex-row md:items-center justify-between gap-sm">
            <div>
                <h2 class="text-xl font-black tracking-tighter text-on-surface uppercase mb-0.5">PIC Master</h2>
                <p class="text-slate-500 font-bold uppercase tracking-widest text-[10px]">Manage recipients and warehouse personnel</p>
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
                        <span class="material-symbols-outlined text-primary text-lg">{{ $editingId ? 'person_edit' : 'person_add' }}</span>
                        {{ $editingId ? ($isEditingSystem ? 'Edit System Account' : 'Edit PIC') : 'Register PIC' }}
                    </h3>

                    <form wire:submit.prevent="save" class="space-y-sm">
                        <div class="space-y-1">
                            <label class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">
                                {{ $isEditingSystem ? 'Display Name *' : 'PIC Position *' }}
                            </label>
                            <input wire:model="name" type="text" class="w-full h-9 px-3 py-1.5 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md focus:ring-1 focus:ring-primary/20 focus:border-primary font-bold text-xs text-on-surface transition-all" placeholder="{{ $isEditingSystem ? 'e.g. Admin Sparepart' : 'e.g. SPV BAHAN BAKU' }}">
                            @error('name') <span class="text-red-500 text-[10px] font-bold ml-1">{{ $message }}</span> @enderror
                        </div>

                        @if($isEditingSystem)
                            <div class="space-y-1">
                                <label class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">Email Address *</label>
                                <input wire:model="email" type="email" class="w-full h-9 px-3 py-1.5 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md focus:ring-1 focus:ring-primary/20 focus:border-primary font-bold text-xs text-on-surface transition-all" placeholder="e.g. adminsp@company.com">
                                @error('email') <span class="text-red-500 text-[10px] font-bold ml-1">{{ $message }}</span> @enderror
                            </div>
                        @else
                            <div class="space-y-1">
                                <label class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">Department *</label>
                                <select wire:model="department_id" class="w-full h-9 px-3 py-1 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md focus:ring-1 focus:ring-primary/20 focus:border-primary font-bold text-xs text-on-surface transition-all">
                                    <option value="">Select Department...</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}">{{ $dept->name }} ({{ $dept->code }})</option>
                                    @endforeach
                                </select>
                                @error('department_id') <span class="text-red-500 text-[10px] font-bold ml-1">{{ $message }}</span> @enderror
                            </div>

                            <div class="space-y-1">
                                <label class="text-[9px] font-black uppercase tracking-widest text-slate-400 ml-1">Status</label>
                                <select wire:model="is_active" class="w-full h-9 px-3 py-1 bg-slate-50 border border-slate-200 dark:border-slate-800 rounded-md focus:ring-1 focus:ring-primary/20 focus:border-primary font-bold text-xs text-on-surface transition-all">
                                    <option value="1">ACTIVE</option>
                                    <option value="0">INACTIVE</option>
                                </select>
                                @error('is_active') <span class="text-red-500 text-[10px] font-bold ml-1">{{ $message }}</span> @enderror
                            </div>
                        @endif

                        <div class="flex gap-sm pt-3 border-t border-slate-100 dark:border-slate-800">
                            <button type="submit" class="h-9 flex-1 bg-primary text-white rounded-md font-black text-xs uppercase tracking-widest hover:brightness-110 active:scale-95 transition-all flex items-center justify-center gap-2 shadow-sm">
                                <span class="material-symbols-outlined text-sm">{{ $editingId ? 'save' : 'how_to_reg' }}</span>
                                {{ $editingId ? 'Update' : 'Register' }}
                            </button>
                            
                            @if($editingId)
                                <button type="button" wire:click="cancelEdit" class="h-9 px-3 bg-slate-100 border border-slate-200 dark:border-slate-800 text-slate-650 rounded-md font-bold text-[10px] uppercase tracking-widest hover:bg-slate-200 active:scale-95 transition-all flex items-center justify-center">
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
                    <input wire:model.live.debounce.300ms="search" type="text" class="flex-1 bg-transparent border-none outline-none focus:ring-0 font-bold text-xs text-slate-800 dark:text-slate-100" placeholder="Search PIC Position...">
                </div>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-md shadow-sm overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800">
                                <th class="px-md py-1.5 text-[9px] font-black uppercase tracking-widest text-slate-400">PIC Position</th>
                                <th class="px-md py-1.5 text-[9px] font-black uppercase tracking-widest text-slate-400">Department</th>
                                <th class="px-md py-1.5 text-[9px] font-black uppercase tracking-widest text-slate-400">Status</th>
                                <th class="px-md py-1.5 text-[9px] font-black uppercase tracking-widest text-slate-400 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-slate-800/50">
                            @forelse($users as $user)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                    <td class="px-md py-1.5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-7 h-7 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center text-slate-400 overflow-hidden shrink-0 font-black text-[9px] uppercase">
                                                {{ substr($user->name, 0, 2) }}
                                            </div>
                                            <div>
                                                <div class="font-black text-on-surface text-xs leading-tight">{{ $user->name }}</div>
                                                @if(in_array($user->name, ['Admin Sparepart', 'Manager PPIC', 'Auditor']) || in_array($user->email, ['adminsp@peroniks.com', 'managerppic@peroniks.com', 'auditor@peroniks.com']))
                                                    <span class="mt-1 inline-block bg-blue-100 dark:bg-blue-900/40 px-1.5 py-0.5 rounded text-[8px] font-black text-blue-700 dark:text-blue-300 uppercase tracking-wider">SYSTEM ACCOUNT</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-md py-1.5">
                                        <div class="flex flex-col">
                                            <span class="text-xs font-black text-slate-700 dark:text-slate-350 leading-tight">{{ $user->department?->name ?? 'N/A' }}</span>
                                            <span class="text-[9px] font-bold text-slate-400 tracking-widest uppercase mt-0.5">{{ $user->department?->code ?? '-' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-md py-1.5">
                                        @if($user->is_active)
                                            <span class="bg-emerald-100 dark:bg-emerald-900/40 px-2 py-0.5 rounded text-[9px] font-black text-emerald-700 dark:text-emerald-350 uppercase tracking-wider">ACTIVE</span>
                                        @else
                                            <span class="bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded text-[9px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-wider">INACTIVE</span>
                                        @endif
                                    </td>
                                    <td class="px-md py-1.5 text-right">
                                        <div class="flex justify-end items-center gap-1">
                                            @php
                                                $isSystem = in_array($user->name, ['Admin Sparepart', 'Manager PPIC', 'Auditor']) || in_array($user->email, ['adminsp@peroniks.com', 'managerppic@peroniks.com', 'auditor@peroniks.com']);
                                            @endphp
                                            @if(!$isSystem && $user->is_active)
                                                <button wire:click="deactivate({{ $user->id }})" class="px-2 py-1 text-[10px] font-bold text-red-500 hover:text-red-750 transition-colors uppercase tracking-wider" title="Deactivate PIC">
                                                    Deactivate
                                                </button>
                                            @endif
                                            <button wire:click="edit({{ $user->id }})" class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-primary transition-colors" title="Edit">
                                                <span class="material-symbols-outlined text-[18px]">edit</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-md py-12 text-center text-slate-400">
                                        <span class="material-symbols-outlined text-3xl mb-1 opacity-20">group</span>
                                        <p class="font-bold text-xs">No users found</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="p-sm border-t border-slate-50 dark:border-slate-800">
                        {{ $users->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
