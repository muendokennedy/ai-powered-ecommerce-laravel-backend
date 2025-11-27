<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated as BaseRedirectIfAuthenticated;

class RedirectIfAuthenticated extends BaseRedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$guards): Response
    {

        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {

                if($guard === 'sanctum'){
                    continue;
                }

                if($guard === 'web'){
                    return redirect()->route('client.home');
                }

                if($guard === 'admin'){
                    return redirect()->route('admin.dashboard');
                }
            }
        }
        return parent::handle($request, $next, ...$guards);
    }
}
