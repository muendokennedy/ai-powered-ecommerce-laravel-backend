<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $userData = [
            'email' => $request->email,
            'client_id' => 'CUST-' . Str::upper(Str::random(8)),
            'status' => 0,
            'loyalty_points' => 0,
            'total_spent' => 0,
            'password' => Hash::make($request->password),
        ];

        if ($request->hasFile('profileImg')) {
            $path = $request->file('profileImg')->store('users', 'public');
            $userData['profileImg'] = Storage::url($path);
        }

        $user = User::create($userData);

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        $authUser = auth('web')->user();

        return response(['user' => $authUser]);
    }
}
