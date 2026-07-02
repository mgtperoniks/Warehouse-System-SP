<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Livewire\Features\SupportFileUploads\FileUploadController;

class LivewireDebugController extends FileUploadController
{
    public function handle()
    {
        $request = request();
        
        \Illuminate\Support\Facades\Log::info('LIVEWIRE_UPLOAD_DEBUG', [
            'fullUrl' => $request->fullUrl(),
            'url' => $request->url(),
            'host' => $request->getHost(),
            'getHttpHost' => $request->getHttpHost(),
            'port' => $request->getPort(),
            'scheme' => $request->getScheme(),
            'query' => $request->query(),
            'headers' => $request->headers->all(),
            'server' => $request->server(),
            'hasValidSignature' => $request->hasValidSignature(),
            'app_url' => config('app.url'),
            'url_root' => url('/'),
            'expires' => $request->query('expires'),
            'signature' => $request->query('signature'),
            'REQUEST_URI' => $request->server('REQUEST_URI'),
            'QUERY_STRING' => $request->server('QUERY_STRING'),
            'HTTP_HOST' => $request->server('HTTP_HOST'),
            'SERVER_PORT' => $request->server('SERVER_PORT'),
        ]);

        return parent::handle();
    }
}
