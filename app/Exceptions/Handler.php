<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;

class Handler extends ExceptionHandler
{
    //

    protected function unauthenticated($request, AuthenticationException $exception)
{


     if ($this->shouldReturnJson($request, $exception)) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }


        // 2. Otherwise determine guard
        $guard = $exception->guards()[0] ?? null;

        // 3. Choose login route by guard
        switch ($guard) {
            case 'admin':
                $route = 'admin.login';
                break;
            case 'web':
                $route = 'client.login';
                break;
            default:
                // Sanctum or unknown guards — avoid hitting route('login')
                return redirect()->guest('/'); // or any safe page
        }

        return redirect()->guest(route($route));
    }
}

