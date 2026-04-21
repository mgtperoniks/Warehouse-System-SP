<div class="relative min-h-screen flex items-center justify-center p-4 bg-slate-900 overflow-hidden font-inter text-slate-100">
    <!-- Tailwind CDN Fallback -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        inter: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>

    <!-- Background Image with Professional Overlay -->
    <div class="absolute inset-0 z-0">
        <img src="{{ asset('images/auth/warehouse.jpg') }}" 
             class="w-full h-full object-cover" 
             style="filter: brightness(0.45) contrast(1.1);"
             onerror="this.src='https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?q=80&w=2070&auto=format&fit=crop'"
             alt="Warehouse Background">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
    </div>

    <!-- Login Card: Compact Glassmorphism (Reverted to Glass Theme) -->
    <div class="relative z-10 w-full max-w-[400px] bg-white/10 backdrop-blur-2xl rounded-[2.5rem] shadow-[0_20px_50px_rgba(0,0,0,0.5)] overflow-hidden border border-white/20">
        <div class="px-8 py-10 flex flex-col items-center">
            
            <!-- Compact Logo Section: Glass Style -->
            <div class="mb-6 text-center w-full text-white">
                <div class="relative mx-auto mb-4 p-1.5 bg-white/90 rounded-full shadow-2xl overflow-hidden flex items-center justify-center" 
                     style="width: 100px; height: 100px;">
                    <img src="{{ asset('images/auth/logo.png') }}" 
                         style="width: 100%; height: 100%; object-fit: contain;"
                         onerror="this.src='https://placehold.co/200x200?text=LOGO'"
                         alt="Company Logo">
                </div>
                <h1 class="text-2xl font-black tracking-tighter uppercase leading-none drop-shadow-md">PT. PERONI</h1>
                <h1 class="text-2xl font-black tracking-tighter uppercase leading-none mb-3 drop-shadow-md">KARYA SENTRA</h1>
                <div class="h-1 w-12 bg-red-600 mx-auto rounded-full mb-4 shadow-[0_0_10px_rgba(220,38,38,0.5)]"></div>
                <p class="text-[9px] text-white/90 font-black uppercase tracking-[0.3em] bg-white/20 px-4 py-1.5 rounded-full border border-white/10 inline-block backdrop-blur-md">Warehouse System</p>
            </div>

            <!-- Compact Login Form -->
            <form wire:submit.prevent="login" class="w-full space-y-4">
                <div class="space-y-1.5">
                    <label for="email" class="block text-[9px] font-black text-white/70 uppercase tracking-widest ml-2">Email Access</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-white/50 group-focus-within:text-white transition-colors">
                            <span class="material-symbols-outlined text-[18px]">alternate_email</span>
                        </div>
                        <input wire:model="email" type="email" id="email" required 
                            class="block w-full pl-12 pr-4 py-3.5 bg-white/10 border border-white/10 rounded-xl focus:bg-white focus:text-slate-900 transition-all text-sm font-bold text-white placeholder-white/30 outline-none"
                            placeholder="name@peroniks.com">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label for="password" class="block text-[9px] font-black text-white/70 uppercase tracking-widest ml-2">Security Key</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-white/50 group-focus-within:text-white transition-colors">
                            <span class="material-symbols-outlined text-[18px]">lock_person</span>
                        </div>
                        <input wire:model="password" type="password" id="password" required 
                            class="block w-full pl-12 pr-4 py-3.5 bg-white/10 border border-white/10 rounded-xl focus:bg-white focus:text-slate-900 transition-all text-sm font-bold text-white placeholder-white/30 outline-none"
                            placeholder="••••••••">
                    </div>
                </div>

                <div class="flex items-center gap-2 px-1">
                    <div class="relative flex items-center">
                        <input wire:model="remember" type="checkbox" id="remember" class="peer hidden">
                        <div class="w-4 h-4 border border-white/30 rounded-md bg-white/5 group-hover:border-white transition-all peer-checked:bg-red-600 peer-checked:border-red-600 shadow-inner"></div>
                        <span class="material-symbols-outlined absolute inset-0 text-white text-[12px] flex items-center justify-center opacity-0 peer-checked:opacity-100 transition-opacity">check</span>
                    </div>
                    <label for="remember" class="text-[10px] font-bold text-white/70 hover:text-white transition-colors cursor-pointer uppercase tracking-wider">Remember Session</label>
                </div>

                <button type="submit" class="w-full py-4 bg-red-600 text-white rounded-xl font-black text-[11px] uppercase tracking-[0.2em] shadow-[0_10px_20px_rgba(220,38,38,0.4)] hover:bg-red-700 active:scale-95 transition-all flex items-center justify-center gap-2 mt-2">
                    <span>Sign In</span>
                    <span class="material-symbols-outlined text-[16px]">login</span>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-white/10 w-full text-center">
                <p class="text-[8px] text-white/40 font-black uppercase tracking-[0.2em] leading-relaxed">
                    &copy; 2026 PT PERONI KARYA SENTRA • PERONIK SYS
                </p>
            </div>
        </div>
    </div>

    <!-- Global Resources -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; height: 100vh; margin: 0; overflow: hidden; background-color: #020617; }
        input::placeholder { color: rgba(255, 255, 255, 0.2); }
    </style>
</div>
