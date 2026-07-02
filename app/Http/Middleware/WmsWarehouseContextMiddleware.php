<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WmsWarehouseContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (str_contains($request->getPathInfo(), 'upload-file')) {
            \Illuminate\Support\Facades\Log::info('LIVEWIRE_UPLOAD_DEBUG', [
                'fullUrl' => $request->fullUrl(),
                'url' => $request->url(),
                'host' => $request->getHost(),
                'getHttpHost' => $request->getHttpHost(),
                'port' => $request->getPort(),
                'scheme' => $request->getScheme(),
                'query' => $request->query(),
                'headers' => $request->headers->all(),
                'hasValidSignature' => $request->hasValidSignature(),
                'app_url' => config('app.url'),
                'url_root' => url('/'),
                'server_port' => $request->server('SERVER_PORT'),
                'server_http_host' => $request->server('HTTP_HOST'),
                'request_uri' => $request->server('REQUEST_URI'),
                'query_string' => $request->server('QUERY_STRING'),
                'signature_param' => $request->query('signature'),
                'expires_param' => $request->query('expires'),
            ]);
        }

        if (auth()->check()) {
            $user = auth()->user();
            $activeWarehouseId = session('active_warehouse_id');

            $isValid = false;
            if ($activeWarehouseId) {
                $isValid = $user->warehouses()->where('warehouses.id', $activeWarehouseId)->exists();
            }

            if (!$isValid) {
                $firstWarehouse = $user->warehouses()->first();
                if (!$firstWarehouse) {
                    abort(403, 'User has no mapped warehouses.');
                }

                session()->put('active_warehouse_id', $firstWarehouse->id);
                session()->put('active_warehouse_code', $firstWarehouse->code);
                session()->put('active_warehouse_name', $firstWarehouse->name);
            }
        }

        return $next($request);
    }
}
