<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    /**
     * Return devices (other active sessions) for the currently authenticated admin
     * and a list of admins with online status.
     */
    public function index(Request $request)
    {
        $adminUser = $request->user('admin') ?? auth()->guard('admin')->user();
        if (!$adminUser) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated (admin)'], 401);
        }

        $currentAuthenticatedAdmin = Admin::with('actions')->find($adminUser->id);
        $currentSessionId = session()->getId();

        $allSessions = DB::table('sessions')->get();

        $lifetimeMinutes = config('session.lifetime', 120);
        $activeThreshold = Carbon::now()->subMinutes($lifetimeMinutes)->timestamp;

        $sessionBelongsToAdmin = function ($s) use ($adminUser) {
            if (isset($s->user_id) && $s->user_id == $adminUser->id) {
                return true;
            }

            $payload = $s->payload ?? '';
            try {
                $un = @unserialize($payload);
                if ($un !== false && (is_array($un) || is_object($un))) {
                    $arr = (array) $un;
                    foreach ($arr as $k => $v) {
                        if (is_string($k) && str_starts_with($k, 'login_') && ($v == $adminUser->id)) {
                            return true;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }

            // fallback: raw payload contains id or email
            if (strpos((string)$payload, (string)$adminUser->id) !== false || strpos((string)$payload, $adminUser->email) !== false) {
                return true;
            }

            return false;
        };

        // Devices: other authenticated sessions (exclude current session)
        $devices = $allSessions->filter(function ($s) use ($sessionBelongsToAdmin, $adminUser, $currentSessionId, $activeThreshold) {
            if (!$sessionBelongsToAdmin($s)) return false;
            if (($s->id ?? null) === $currentSessionId) return false;

            // Must be recent (within session lifetime)
            if (!isset($s->last_activity) || $s->last_activity < $activeThreshold) return false;

            $payload = $s->payload ?? '';
            $isAuth = false;
            try {
                $un = @unserialize($payload);
                if ($un !== false && is_array($un)) {
                    foreach ((array)$un as $k => $v) {
                        if (is_string($k) && str_starts_with($k, 'login_') && ($v == $adminUser->id)) {
                            $isAuth = true;
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }

            if (!$isAuth && (isset($s->user_id) && $s->user_id == $adminUser->id)) $isAuth = true;
            if (!$isAuth && (strpos((string)$payload, (string)$adminUser->id) !== false || strpos((string)$payload, $adminUser->email) !== false)) $isAuth = true;

            return $isAuth;
        })->map(function ($s) use ($currentSessionId) {
            $ua = $s->user_agent ?? '';
            $parsed = $this->parseUserAgent($ua);
            return [
                'session_id' => $s->id,
                'ip_address' => $s->ip_address,
                'user_agent' => $ua,
                'browser' => $parsed['browser'] ?? null,
                'os' => $parsed['os'] ?? null,
                'device_type' => $parsed['device'] ?? null,
                'last_activity' => $s->last_activity ? Carbon::createFromTimestamp($s->last_activity)->toIso8601String() : null,
                'is_current' => $s->id === $currentSessionId,
            ];
        })->values();

        // Attach devices to currentAuthenticatedAdmin for frontend convenience
        $currentAuthenticatedAdminArray = $currentAuthenticatedAdmin->toArray();
        $currentAuthenticatedAdminArray['devices'] = $devices->toArray();

        // Build admin list (primary admin sees full details)
        $allAdmins = (strtolower((string)$currentAuthenticatedAdmin->role) === 'primary admin')
            ? Admin::with('actions')->get()
            : Admin::all();

        $admins = $allAdmins->map(function ($a) use ($allSessions) {
            $matches = $allSessions->filter(function ($s) use ($a) {
                if (isset($s->user_id) && $s->user_id == $a->id) return true;
                $payload = $s->payload ?? '';
                if (strpos((string)$payload, (string)$a->id) !== false) return true;
                if (strpos((string)$payload, $a->email) !== false) return true;
                return false;
            });

            $recent = $matches->contains(function ($s) {
                return isset($s->last_activity) && $s->last_activity >= Carbon::now()->subMinutes(5)->timestamp;
            });

            return [
                'id' => $a->id,
                'fullName' => $a->fullName,
                'email' => $a->email,
                'role' => $a->role,
                'actions' => $a->actions->toArray(),
                'online' => (bool)$recent,
            ];
        })->values();

        return response()->json([
            'currentAuthenticatedAdmin' => $currentAuthenticatedAdminArray,
            'currentSessionId' => $currentSessionId,
            // 'devices' => $devices,
            'admins' => $admins,
        ]);
    }

    /** Invalidate a single session (by id) if it belongs to current admin */
    public function invalidateSession(Request $request, $sessionId)
    {
        $adminUser = $request->user('admin') ?? auth()->guard('admin')->user();
        if (!$adminUser) return response()->json(['success' => false, 'message' => 'Unauthenticated (admin)'], 401);

        $session = DB::table('sessions')->where('id', $sessionId)->first();
        if (!$session) return response()->json(['success' => false, 'message' => 'Session not found'], 404);

        // ensure belongs
        $payload = $session->payload ?? '';
        $belongs = (isset($session->user_id) && $session->user_id == $adminUser->id)
            || strpos((string)$payload, (string)$adminUser->id) !== false
            || strpos((string)$payload, $adminUser->email) !== false;

        if (!$belongs) return response()->json(['success' => false, 'message' => 'Session does not belong to current admin'], 403);

        DB::table('sessions')->where('id', $sessionId)->delete();
        return response()->json(['success' => true, 'message' => 'Session invalidated']);
    }

    /** Invalidate all other sessions for current admin */
    public function invalidateOtherSessions(Request $request)
    {
        $adminUser = $request->user('admin') ?? auth()->guard('admin')->user();
        if (!$adminUser) return response()->json(['success' => false, 'message' => 'Unauthenticated (admin)'], 401);

        $currentSessionId = session()->getId();
        $allSessions = DB::table('sessions')->get();

        $toDelete = $allSessions->filter(function ($s) use ($adminUser, $currentSessionId) {
            if (($s->id ?? null) === $currentSessionId) return false;
            if (isset($s->user_id) && $s->user_id == $adminUser->id) return true;
            $payload = $s->payload ?? '';
            if (strpos((string)$payload, (string)$adminUser->id) !== false) return true;
            if (strpos((string)$payload, $adminUser->email) !== false) return true;
            return false;
        })->pluck('id')->toArray();

        if (!empty($toDelete)) {
            DB::table('sessions')->whereIn('id', $toDelete)->delete();
        }

        return response()->json(['success' => true, 'message' => 'Other sessions invalidated', 'invalidated' => $toDelete]);
    }

    /** Very small UA parser for browser/os/device */
    private function parseUserAgent(?string $ua): array
    {
        $ua = $ua ?? '';
        $browser = null; $os = null; $device = 'desktop';

        if (stripos($ua, 'mobile') !== false || stripos($ua, 'android') !== false || stripos($ua, 'iphone') !== false) $device = 'mobile';
        if (stripos($ua, 'windows') !== false) $os = 'Windows';
        if (stripos($ua, 'mac os x') !== false || stripos($ua, 'macintosh') !== false) $os = 'macOS';
        if (stripos($ua, 'android') !== false) $os = 'Android';
        if (stripos($ua, 'iphone') !== false || stripos($ua, 'ipad') !== false) $os = 'iOS';

        if (stripos($ua, 'edge') !== false) $browser = 'Edge';
        elseif (stripos($ua, 'chrome') !== false && stripos($ua, 'chromium') === false) $browser = 'Chrome';
        elseif (stripos($ua, 'firefox') !== false) $browser = 'Firefox';
        elseif (stripos($ua, 'safari') !== false && stripos($ua, 'chrome') === false) $browser = 'Safari';
        elseif (stripos($ua, 'opera') !== false || stripos($ua, 'opr/') !== false) $browser = 'Opera';

        return ['browser' => $browser, 'os' => $os, 'device' => $device];
    }
}
