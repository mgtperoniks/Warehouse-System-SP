<div class="relative min-h-screen flex items-center justify-center p-6 bg-slate-100 overflow-hidden">
    <!-- Background Image with Overlay -->
    <div class="absolute inset-0 z-0">
        <img src="{{ asset('images/auth/warehouse.jpg') }}" 
             class="w-full h-full object-cover" 
             onerror="this.src='https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?q=80&w=2070&auto=format&fit=crop'"
             alt="Warehouse Background">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-[2px]"></div>
    </div>

    <!-- Login Card -->
    <div class="relative z-10 w-full max-w-md bg-white/95 backdrop-blur-md rounded-[2.5rem] shadow-[0_20px_50px_rgba(0,0,0,0.3)] overflow-hidden border border-white/20">
        <div class="px-8 pt-12 pb-10 flex flex-col items-center">
            
            <!-- Logo Section -->
            <div class="mb-10 text-center">
                <div class="relative w-32 h-32 mx-auto mb-6 p-2 bg-white rounded-full shadow-inner border border-slate-100 overflow-hidden">
                    <img src="{{ asset('images/auth/logo.png') }}" 
                         class="w-full h-full object-contain"
                         onerror="this.src='https://placehold.co/200x200?text=LOGO'"
                         alt="Company Logo">
                </div>
                <h1 class="text-3xl font-black tracking-tighter text-slate-900 uppercase leading-[0.9]">PT. PERONI</h1>
                <h1 class="text-3xl font-black tracking-tighter text-slate-900 uppercase leading-[0.9] mb-4">KARYA SENTRA</h1>
                <div class="h-1 w-12 bg-red-600 mx-auto mb-4"></div>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-[0.2em] px-4 py-1 border border-slate-100 rounded-full inline-block">Warehouse Management System</p>
            </div>

            <!-- Login Form -->
            <form wire:submit.prevent="login" class="w-full space-y-5">
                <div class="space-y-1">
                    <label for="email" class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Email Address</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-red-600">
                            <span class="material-symbols-outlined text-sm">mail</span>
                        </div>
                        <input wire:model="email" type="email" id="email" required 
                            class="block w-full pl-11 pr-4 py-4 bg-slate-50 border-transparent rounded-[1.25rem] focus:bg-white focus:ring-4 focus:ring-red-600/5 focus:border-red-600/30 transition-all text-sm font-medium"
                            placeholder="name@peroniks.com">
                    </div>
                    @error('email') <span class="text-[10px] text-red-500 mt-1 font-bold ml-1 uppercase tracking-tighter">{{ $message }}</span> @enderror
                </div>

                <div class="space-y-1">
                    <label for="password" class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Password</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-red-600">
                            <span class="material-symbols-outlined text-sm">lock</span>
                        </div>
                        <input wire:model="password" type="password" id="password" required 
                            class="block w-full pl-11 pr-4 py-4 bg-slate-50 border-transparent rounded-[1.25rem] focus:bg-white focus:ring-4 focus:ring-red-600/5 focus:border-red-600/30 transition-all text-sm font-medium"
                            placeholder="••••••••">
                    </div>
                    @error('password') <span class="text-[10px] text-red-500 mt-1 font-bold ml-1 uppercase tracking-tighter">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center gap-2 px-1">
                    <input wire:model="remember" type="checkbox" id="remember" 
                        class="w-4 h-4 rounded-md border-slate-200 text-red-600 focus:ring-red-600/20 cursor-pointer">
                    <label for="remember" class="text-[11px] font-bold text-slate-500 hover:text-slate-900 transition-colors cursor-pointer">Remember me</label>
                </div>

                <button type="submit" class="w-full py-4 bg-slate-900 text-white rounded-[1.25rem] font-black text-sm uppercase tracking-widest shadow-xl hover:bg-red-600 active:scale-[0.98] transition-all flex items-center justify-center gap-2 group mt-4">
                    <span>Sign In</span>
                    <span class="material-symbols-outlined text-sm group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </button>
            </form>

            <!-- Footer -->
            <div class="mt-12 pt-6 border-t border-slate-100 w-full text-center">
                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">
                    &copy; 2026 PPIC DEPT. PT Peroni Karya Sentra.<br>
                    All rights reserved.
                </p>
            </div>
        </div>
    </div>

    <!-- Inject Resources -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</div>
