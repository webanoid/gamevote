<?php

namespace App\Providers;

use App\Actions\Games\ResetLocalLibrary;
use App\Services\Essense\ApiKeyProvider;
use App\Services\Essense\EssenseClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ApiKeyProvider::class, fn () => new ApiKeyProvider(
            baseUrl: config('services.essense.url'),
            configuredKey: config('services.essense.key'),
            resetLocalLibrary: $this->app->make(ResetLocalLibrary::class),
        ));

        $this->app->singleton(EssenseClient::class, fn () => new EssenseClient(
            baseUrl: config('services.essense.url'),
            keys: $this->app->make(ApiKeyProvider::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        $trustedProxies = config('app.trusted_proxies');

        if (is_string($trustedProxies) && $trustedProxies !== '') {
            TrustProxies::at($trustedProxies === '*' ? '*' : array_map('trim', explode(',', $trustedProxies)));
        }

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
