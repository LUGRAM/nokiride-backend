<?php

namespace App\Providers;

use App\Contracts\OtpProviderInterface;
use App\Services\Otp\FakeOtpProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OtpProviderInterface::class, function () {
            if (config('otp.provider') !== 'fake') {
                throw new \RuntimeException('Le fournisseur OTP configuré n’est pas encore disponible.');
            }

            return new FakeOtpProvider();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth-login', function (Request $request): Limit {
            $phone = Str::lower((string) $request->input('phone'));

            return Limit::perMinute(5)->by($phone.'|'.$request->ip());
        });

        RateLimiter::for('auth-register', fn (Request $request): Limit => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for(
            'otp-send',
            fn (Request $request): Limit => Limit::perMinute(3)->by((string) $request->input('phone').'|'.$request->ip())
        );
        RateLimiter::for(
            'otp-verify',
            fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->input('phone').'|'.$request->ip())
        );
        RateLimiter::for('public-api', fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for(
            'authenticated-api',
            fn (Request $request): Limit => Limit::perMinute(60)->by((string) ($request->user()?->id ?? $request->ip()))
        );
    }
}
