<div class="relative min-h-screen flex items-center justify-center p-4 lg:p-8 bg-slate-900 overflow-hidden font-inter">
    <!-- Background Image with Professional Overlay -->
    <div class="absolute inset-0 z-0">
        <img src="{{ asset('images/auth/warehouse.jpg') }}" 
             class="w-full h-full object-cover scale-110" 
             onerror="this.src='https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?q=80&w=2070&auto=format&fit=crop'"
             alt="Warehouse Background">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-900/80 via-slate-800/40 to-slate-900/80 backdrop-blur-[3px]"></div>
    </div>

    <!-- Login Card: Glassmorphism Ultra -->
    <div class="relative z-10 w-full max-w-[440px] bg-white/10 backdrop-blur-xl rounded-[3rem] shadow-[0_32px_64px_rgba(0,0,0,0.5)] overflow-hidden border border-white/20">
        <div class="px-10 py-14 flex flex-col items-center">
            
            <!-- Logo Section: Polished -->
            <div class="mb-12 text-center w-full">
                <div class="relative w-28 h-28 mx-auto mb-6 p-1 bg-white rounded-full shadow-2xl border-4 border-white/30 overflow-hidden flex items-center justify-center">
                    <img src="{{ asset('images/auth/logo.png') }}" 
                         class="w-full h-full object-contain p-2"
                         onerror="this.src='https://placehold.co/200x200?text=LOGO'"
                         alt="Company Logo">
                </div>
                <h1 class="text-3xl font-black tracking-tighter text-white uppercase leading-none drop-shadow-md">PT. PERONI</h1>
                <h1 class="text-3xl font-black tracking-tighter text-white uppercase leading-none mb-4 drop-shadow-md">KARYA SENTRA</h1>
                <div class="h-1.5 w-16 bg-red-600 mx-auto rounded-full shadow-[0_0_15px_rgba(220,38,38,0.5)] mb-6"></div>
                <p class="text-[10px] text-white/70 font-black uppercase tracking-[0.3em] bg-white/10 px-6 py-1.5 rounded-full border border-white/10 inline-block">Warehouse Management System</p>
            </div>

            <!-- Login Form: Refined Inputs -->
            <form wire:submit.prevent="login" class="w-full space-y-6">
                <!-- Email Input -->
                <div class="space-y-2">
                    <label for="email" class="block text-[10px] font-black text-white/50 uppercase tracking-[0.2em] ml-2">Email Address</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none text-white/40 group-focus-within:text-red-500 transition-colors">
                            <span class="material-symbols-outlined text-[20px]">alternate_email</span>
                        </div>
                        <input wire:model="email" type="email" id="email" required 
                            class="block w-full pl-14 pr-6 py-5 bg-white/5 border border-white/10 rounded-2xl focus:bg-white focus:text-slate-900 focus:ring-0 focus:border-red-500 transition-all text-sm font-semibold text-white placeholder-white/20"
                            placeholder="name@peroniks.com">
                    </div>
                    @error('email') <span class="text-[10px] text-red-400 mt-1 font-bold ml-2 uppercase tracking-tight">{{ $message }}</span> @enderror
                </div>

                <!-- Password Input -->
                <div class="space-y-2">
                    <label for="password" class="block text-[10px] font-black text-white/50 uppercase tracking-[0.2em] ml-2">Password</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none text-white/40 group-focus-within:text-red-500 transition-colors">
                            <span class="material-symbols-outlined text-[20px]">lock_person</span>
                        </div>
                        <input wire:model="password" type="password" id="password" required 
                            class="block w-full pl-14 pr-6 py-5 bg-white/5 border border-white/10 rounded-2xl focus:bg-white focus:text-slate-900 focus:ring-0 focus:border-red-500 transition-all text-sm font-semibold text-white placeholder-white/20"
                            placeholder="••••••••">
                    </div>
                    @error('password') <span class="text-[10px] text-red-400 mt-1 font-bold ml-2 uppercase tracking-tight">{{ $message }}</span> @enderror
                </div>

                <!-- Action Row -->
                <div class="flex items-center justify-between px-2">
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <div class="relative">
                            <input wire:model="remember" type="checkbox" class="peer hidden">
                            <div class="w-5 h-5 border-2 border-white/20 rounded-lg group-hover:border-red-500 peer-checked:bg-red-600 peer-checked:border-red-600 transition-all"></div>
                            <span class="material-symbols-outlined absolute inset-0 text-white text-[14px] flex items-center justify-center opacity-0 peer-checked:opacity-100 transition-opacity">check</span>
                        </div>
                        <span class="text-[11px] font-bold text-white/60 group-hover:text-white transition-colors">Remember active session</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="w-full py-5 bg-red-600 text-white rounded-2xl font-black text-xs uppercase tracking-[0.2em] shadow-[0_15px_30px_rgba(220,38,38,0.4)] hover:bg-red-700 active:scale-95 transition-all flex items-center justify-center gap-3 group mt-4">
                    <span>Sign In to System</span>
                    <span class="material-symbols-outlined text-[18px] group-hover:translate-x-1 transition-transform">login</span>
                </button>
            </form>

            <!-- Footer Section -->
            <div class="mt-14 pt-8 border-t border-white/5 w-full text-center">
                <p class="text-[9px] text-white/30 font-black uppercase tracking-[0.2em]">
                    &copy; 2026 PPIC DEPT. PT Peroni Karya Sentra.<br>
                    Authenticated Maintenance & Logistics
                </p>
            </div>
        </div>
    </div>

    <!-- Inject Global Styles -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0f172a; }
        .font-inter { font-family: 'Inter', sans-serif; }
    </style>
</div>
