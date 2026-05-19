<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force the application to use HTTP scheme in production to prevent 6032/HTTPS redirects
        if (config('app.env') === 'production') {
            \Illuminate\Support\Facades\URL::forceScheme('http');
            
            // Explicitly force the root URL, preserving the active port (e.g. :6031) on web requests
            $appUrl = config('app.url');
            if ($appUrl && $appUrl !== 'http://localhost') {
                if (app()->runningInConsole()) {
                    \Illuminate\Support\Facades\URL::forceRootUrl($appUrl);
                } else {
                    $request = request();
                    if ($request) {
                        $scheme = $request->getScheme();
                        $host = $request->getHost();
                        $port = $request->getPort();
                        
                        $rootUrl = $scheme . '://' . $host;
                        if ($port && !in_array($port, [80, 443])) {
                            $rootUrl .= ':' . $port;
                        }
                        
                        // Append subfolder path if configured in APP_URL
                        $path = parse_url($appUrl, PHP_URL_PATH);
                        if ($path) {
                            $rootUrl .= $path;
                        }
                        
                        \Illuminate\Support\Facades\URL::forceRootUrl($rootUrl);
                    } else {
                        \Illuminate\Support\Facades\URL::forceRootUrl($appUrl);
                    }
                }
            }
        }

        \Illuminate\Support\Facades\Blade::directive('money', function ($amount) {
            return "<?php echo 'Rp ' . number_format($amount, 0, ',', '.'); ?>";
        });
    }
}
