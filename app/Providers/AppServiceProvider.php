<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Auth\Middleware\Authenticate as AuthMiddleware;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated as RedirectIfAuthMiddleware;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //

        $this->app->singleton(ExceptionHandler::class, \App\Exceptions\Handler::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RedirectIfAuthMiddleware::redirectUsing(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*') || auth('admin')->check()) {
                return route('admin.dashboard');
            }

                return route('client.home');

        });

  

        // AuthMiddleware::redirectUsing(function (Request $request) {
        //     if ($request->expectsJson()) {
        //         return null; 
        //     }

        //     if ($request->is('admin') || $request->is('admin/*')) {
        //         return route('admin.login');
        //     }

        //     return route('client.login');
        // });

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

            $this->booted(function(){

            AuthMiddleware::redirectUsing(function ($request) {

            // If request expects JSON (typical for API / Sanctum), return JSON 401
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Unauthenticated'
                ], 401);
            }

            // Get the first guard applied to this route
            $middlewares = $request->route()?->gatherMiddleware() ?? [];
            $guard = null;

            foreach ($middlewares as $middleware) {
                if (str_starts_with($middleware, 'auth:')) {
                    $guard = explode(':', $middleware)[1];
                    break;
                }
            }

            // Decide redirect based on guard
            return match ($guard) {
                'admin'     => route('admin.login'),
                'student'   => route('student.login'),
                'sanctum'   => null, // Sanctum always returns JSON, no redirect
                default     => route('login'),
            };
        });
        });
    }
}
