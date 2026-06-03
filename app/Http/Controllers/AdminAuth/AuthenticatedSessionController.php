<?php

namespace App\Http\Controllers\AdminAuth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\AdminActivity;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): Response
    {
        $request->authenticate();

        $request->session()->regenerate();

        $admin = auth('admin')->user();

        // update admin activity last_login to now(), create record if missing
        try {
            $updated = AdminActivity::where('admin_id', $admin->id)->update(['last_login' => now()]);
            if (!$updated) {
                AdminActivity::create([
                    'admin_id' => $admin->id,
                    'action' => json_encode(['activity' => 'Logged in', 'time' => now()->timestamp]),
                    'total_actions' => 1,
                    'last_login' => now(),
                ]);
            }
        } catch (\Exception $e) {
            // ignore activity update errors
        }

        return response(['admin' => $admin, 'session_id' => $request->session()->getId()]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): Response
    {

        Auth::guard('admin')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
