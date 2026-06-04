<?php

namespace App\Http\Controllers\AdminAuth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\User;
use App\Models\AdminActivity;
use Illuminate\Support\Facades\Storage;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): Response
    {

        
        $request->validate([
            'fullName' => ['required', 'string'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.Admin::class],
            'phone' => ['required', 'string'],
            'department' => ['required', 'string'],
            'location' => ['required', 'string'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'profileImg' => ['sometimes','file','image','max:5120'],
            'permissions' => ['sometimes'],
        ]);

        // Determine role: first admin => primary admin
        $isFirst = Admin::count() === 0;
        $role = $isFirst ? 'primary admin' : 'secondary admin';

        $permissions = [];
        if ($isFirst) {
            // enable common permissions for primary as 'enabled'
            $permissions = [
                'operations' => 'enabled',
                'customer_management' => 'enabled',
                'stock_management' => 'enabled',
                'reports' => 'enabled',
            ];
        } elseif ($request->filled('permissions')) {
            // accept provided permissions (assume JSON string or array)
            $permissions = is_string($request->permissions) ? json_decode($request->permissions, true) : $request->permissions;
        } elseif (strtolower($request->input('department', '')) === 'operations' || $request->input('permission_area') === 'operations') {
            // department-level default for operations team
            $permissions = [
                'operations' => 'enabled',
                'stock_management' => 'enabled',
                'customer_management' => 'enabled',
                'reports' => 'enabled',
            ];
        }

        $preferences = $request->input('preferences') ? (is_string($request->preferences) ? json_decode($request->preferences, true) : $request->preferences) : ['theme' => 'light', 'language' => 'english'];

        $notifications = $request->input('notifications') ? (is_string($request->notifications) ? json_decode($request->notifications, true) : $request->notifications) : ['email' => 'enabled', 'sms' => 'disabled'];

        $adminData = [
            'fullName' => $request->fullName,
            'email' => $request->email,
            'phone' => $request->phone,
            'department' => $request->department,
            'location' => $request->location,
            'password' => Hash::make($request->password),
            'profileImg' => null,
            'permissions' => $permissions,
            'role' => $role,
            'status' => 1,
            'preferences' => $preferences,
            'notifications' => $notifications,
            'active_since' => now(),
        ];

        if ($request->hasFile('profileImg')) {
            $path = $request->file('profileImg')->store('admins', 'public');
            $adminData['profileImg'] = Storage::url($path);
        }

        $admin = Admin::create($adminData);

        // create initial admin activity; set last_login to now() as account is active
        AdminActivity::create([
            'admin_id' => $admin->id,
            'action' => json_encode(['activity' => 'Created an account', 'time' => now()->timestamp]),
            'total_actions' => 1,
            'last_login' => now(),
        ]);

        event(new Registered($admin));

        Auth::guard('admin')->login($admin);

        $request->session()->regenerate();

        return response(['admin' => $admin]);
    }

    /**
     * Edit currently authenticated admin's profile.
     */
    public function editProfile(Request $request)
    {
        $admin = $request->user('admin') ?? auth('admin')->user();
        if (!$admin) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated (admin)'], 401);
        }

        $request->validate([
            'fullName' => ['sometimes','string'],
            'name' => ['sometimes','string'],
            'email' => ['sometimes','email','max:255','unique:admins,email,'.$admin->id],
            'phone' => ['sometimes','string'],
            'department' => ['sometimes','string'],
            'location' => ['sometimes','string'],
            'profileImg' => ['sometimes','file','image','max:5120'],
        ]);

        if ($request->filled('fullName')) $admin->fullName = $request->input('fullName');
        if ($request->filled('name')) $admin->fullName = $request->input('name');
        if ($request->filled('email')) $admin->email = $request->input('email');
        if ($request->filled('phone')) $admin->phone = $request->input('phone');
        if ($request->filled('department')) $admin->department = $request->input('department');
        if ($request->filled('location')) $admin->location = $request->input('location');

        if ($request->hasFile('profileImg')) {
            $path = $request->file('profileImg')->store('admins', 'public');
            $admin->profileImg = Storage::url($path);
        }

        $admin->save();

        return response()->json(['success' => true, 'admin' => $admin]);
    }
}
