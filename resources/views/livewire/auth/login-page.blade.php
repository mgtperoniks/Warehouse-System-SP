<div class="flex min-h-screen">
    <!-- Left Section: Login Form -->
    <div class="w-full lg:w-1/2 flex flex-col justify-center px-8 lg:px-24 bg-white z-10">
        <div class="max-w-md w-full mx-auto">
            <!-- Logo & Brand -->
            <div class="mb-10">
                <div class="flex items-center gap-2 mb-6">
                    <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold text-xl shadow-lg">P</div>
                    <div class="h-8 w-px bg-slate-200"></div>
                </div>
                <h1 class="text-3xl font-black tracking-tighter text-slate-900 uppercase leading-none mb-1">PT. PERONI</h1>
                <h1 class="text-3xl font-black tracking-tighter text-slate-900 uppercase leading-none mb-4">KARYA SENTRA</h1>
                <div class="h-1 w-12 bg-blue-600 mb-6"></div>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-[0.2em]">Warehouse Management System</p>
            </div>

            <!-- Login Form -->
            <form wire:submit.prevent="login" class="space-y-6">
                <div>
                    <label for="email" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Email Address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <span class="material-symbols-outlined text-slate-400 text-sm">mail</span>
                        </div>
                        <input wire:model="email" type="email" id="email" required 
                            class="block w-full pl-11 pr-4 py-4 bg-slate-50 border-transparent rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600/50 transition-all text-sm font-medium"
                            placeholder="name@peroniks.com">
                    </div>
                    @error('email') <span class="text-xs text-red-500 mt-1 font-medium">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="password" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <span class="material-symbols-outlined text-slate-400 text-sm">lock</span>
                        </div>
                        <input wire:model="password" type="password" id="password" required 
                            class="block w-full pl-11 pr-4 py-4 bg-slate-50 border-transparent rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600/50 transition-all text-sm font-medium"
                            placeholder="••••••••">
                    </div>
                    @error('password') <span class="text-xs text-red-500 mt-1 font-medium">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer group">
                        <input wire:model="remember" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-600/20">
                        <span class="text-xs font-bold text-slate-600 group-hover:text-slate-900 transition-colors">Remember me</span>
                    </label>
                </div>

                <button type="submit" class="w-full py-4 bg-blue-600 text-white rounded-xl font-bold shadow-[0_8px_30px_rgb(37,99,235,0.3)] hover:bg-blue-700 active:scale-[0.98] transition-all flex items-center justify-center gap-2 group">
                    <span>Sign In</span>
                    <span class="material-symbols-outlined text-sm group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </button>
            </form>

            <div class="mt-20 pt-8 border-t border-slate-100 italic">
                <p class="text-[10px] text-slate-400 font-medium">
                    &copy; 2026 PPIC DEPT. PT Peroni Karya Sentra.<br>
                    All rights reserved.
                </p>
            </div>
        </div>
    </div>

    <!-- Right Section: Splash Image -->
    <div class="hidden lg:block lg:w-1/2 relative overflow-hidden">
        <img src="https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?q=80&w=2070&auto=format&fit=crop" 
             class="absolute inset-0 w-full h-full object-cover" alt="Industrial Warehouse">
        <div class="absolute inset-0 bg-blue-900/60 backdrop-blur-[2px]"></div>
        
        <div class="absolute bottom-20 left-20 right-20 text-white">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse shadow-[0_0_10px_#4ade80]"></div>
                <span class="text-[11px] font-black uppercase tracking-[0.3em]">System Online</span>
            </div>
            <h2 class="text-5xl font-black tracking-tighter leading-[1.1] mb-6 drop-shadow-2xl">
                Precision Manufacturing<br>& Real-time Monitoring
            </h2>
            <p class="text-lg text-blue-100 font-medium max-w-lg mb-10 leading-relaxed">
                Platform terintegrasi untuk memantau performa mesin, produktivitas operator, dan kualitas produksi secara real-time.
            </p>
            
            <div class="flex gap-12 pt-8 border-t border-white/20">
                <div>
                    <h4 class="text-3xl font-black tracking-tighter mb-1">100%</h4>
                    <p class="text-[10px] font-black uppercase tracking-widest text-blue-300">Uptime</p>
                </div>
                <div>
                    <h4 class="text-3xl font-black tracking-tighter mb-1">24/7</h4>
                    <p class="text-[10px] font-black uppercase tracking-widest text-blue-300">Monitoring</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Inject Material Symbols and Google Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        .font-inter { font-family: 'Inter', sans-serif; }
        body { font-family: 'Inter', sans-serif; }
    </style>
</div>
