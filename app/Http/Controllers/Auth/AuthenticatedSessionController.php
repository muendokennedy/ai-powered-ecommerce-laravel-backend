<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): Response
    {
        $request->authenticate();

        $request->session()->regenerate();

        $authUser = auth('web')->user();

        return response(['user' => $authUser]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): Response
    {        
        Auth::guard('web')->logout();

        info('The user has been logged out' . $request->user());

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
