<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    //protected $namespace = 'App\Http\Controllers';
    protected $namespace = null;

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        // Je Bearer-Token (App-Gerät) bzw. je IP – ohne by() teilten sich alle API-Clients ein gemeinsames Limit.
        // Die Gruppe läuft vor auth:sanctum, daher wird das Token nur gehasht, nicht geprüft.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->bearerToken()
                ? 'token:' . sha1($request->bearerToken())
                : 'ip:' . $request->ip());
        });

        // API v1: Login der Pädagogen-App – 6/min je E-Mail+IP, 60/min je IP (viele Geräte im Schul-NAT)
        RateLimiter::for('paed-app-login', function (Request $request) {
            return [
                Limit::perMinute(6)->by('login:' . mb_strtolower((string) $request->input('email')) . '|' . $request->ip()),
                Limit::perMinute(60)->by('login-ip:' . $request->ip()),
            ];
        });

        RateLimiter::for('calendar-write', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });
    }
}
