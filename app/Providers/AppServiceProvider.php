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
            
            // Explicitly force the root URL to match APP_URL from .env
            if (config('app.url') !== 'http://localhost') {
                \Illuminate\Support\Facades\URL::forceRootUrl(config('app.url'));
            }
        }

        \Illuminate\Support\Facades\Blade::directive('money', function ($amount) {
            return "<?php echo 'Rp ' . number_format($amount, 0, ',', '.'); ?>";
        });
    }
}
