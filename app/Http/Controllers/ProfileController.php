<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($request->user()->only('id', 'username', 'name', 'email'));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $username = strtolower(trim((string) $request->input('username', '')));
        $data = Validator::make([
            'username' => $username,
            'name' => trim((string) $request->input('name', '')),
            'email' => trim((string) $request->input('email', '')),
            'current_password' => $request->input('current_password'),
        ], [
            'username' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[a-z0-9][a-z0-9._-]*$/'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['nullable', 'string'],
        ])->validate();

        if (User::whereRaw('LOWER(username) = ?', [$username])->whereKeyNot($user->id)->exists()) {
            return response()->json(['message' => 'ชื่อผู้ใช้นี้ถูกใช้แล้ว', 'errors' => ['username' => ['ชื่อผู้ใช้นี้ถูกใช้แล้ว']]], 422);
        }
        if ($username !== $user->username) {
            $key = 'profile-username:'.$user->id.'|'.$request->ip();
            if (RateLimiter::tooManyAttempts($key, 5)) {
                return response()->json(['message' => 'ลองใหม่อีกครั้งในภายหลัง'], 429);
            }
            if (!is_string($data['current_password'] ?? null)
                || !Hash::check($data['current_password'], $user->password)) {
                RateLimiter::hit($key, 300);
                return response()->json(['message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง'], 422);
            }
            RateLimiter::clear($key);
        }
        $user->fill(['username' => $username, 'name' => $data['name'], 'email' => $data['email']]);
        $user->save();
        return response()->json($user->only('id', 'username', 'name', 'email'));
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        $user = $request->user();
        $key = 'profile-password:'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['message' => 'ลองใหม่อีกครั้งในภายหลัง'], 429);
        }
        if (!Hash::check($data['current_password'], $user->password)) {
            RateLimiter::hit($key, 300);
            return response()->json(['message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง'], 422);
        }
        RateLimiter::clear($key);
        $user->password = Hash::make($data['password']);
        $user->save();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['message' => 'เปลี่ยนรหัสผ่านแล้ว กรุณาเข้าสู่ระบบอีกครั้ง']);
    }
}
