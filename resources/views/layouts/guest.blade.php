<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Warehouse System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased bg-slate-50">
    {{ $slot }}

    @if(config('app.debug'))
    <!-- Production LAN Port Preservation & Asset Resolution Diagnostic Overlay -->
    <div class="fixed bottom-4 right-4 z-50 p-3 bg-slate-950/95 border border-slate-800 rounded-lg text-[9px] font-mono text-slate-400 shadow-2xl max-w-sm uppercase tracking-wider backdrop-blur-md">
        <div class="border-b border-slate-800 pb-1.5 mb-1.5 flex justify-between items-center">
            <span class="font-black text-emerald-400 text-[10px]">🌐 PORT DIAGNOSTICS</span>
            <span class="px-1.5 py-0.5 rounded bg-emerald-950 border border-emerald-800 text-emerald-400 font-bold">ACTIVE</span>
        </div>
        <div class="space-y-1">
            <div>
                <span class="text-slate-500 block text-[8px] font-black">APP BASE URL [url('/')]:</span>
                <span class="font-bold text-white selection:bg-emerald-800 selection:text-white" id="diag-url-base">{{ url('/') }}</span>
            </div>
            <div>
                <span class="text-slate-500 block text-[8px] font-black">DASHBOARD ROUTE:</span>
                <span class="font-bold text-white selection:bg-emerald-800 selection:text-white" id="diag-url-route">{{ route('dashboard') }}</span>
            </div>
            <div>
                <span class="text-slate-500 block text-[8px] font-black">LIVEWIRE ENDPOINT:</span>
                <span class="font-bold text-white selection:bg-emerald-800 selection:text-white" id="diag-url-livewire">{{ url('/livewire/update') }}</span>
            </div>
            <div>
                <span class="text-slate-500 block text-[8px] font-black">VITE DIRECTIVE SOURCE:</span>
                <span class="font-bold text-white" id="diag-vite-src">@if(file_exists(public_path('hot'))) ⚠️ VITE DEV-SERVER ACTIVE (public/hot exists!) @else ✅ STATIC PRODUCTION ASSETS (manifest.json) @endif</span>
            </div>
            @if(file_exists(public_path('hot')))
            <div class="mt-2 p-1.5 bg-red-950 border border-red-800 rounded text-red-400 text-[8px] font-bold leading-normal">
                🚨 CRITICAL WARNING: 'public/hot' was found! This forces the app to request assets from the Vite HMR server, breaking port preservation. Please delete public/hot in production.
            </div>
            @endif
        </div>
    </div>
    @endif
</body>
</html>
