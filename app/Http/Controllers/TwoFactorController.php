<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserTwoFactor;
use App\Services\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class TwoFactorController extends Controller
{
    public function login(Request $request)
    {
        if (!$request->hasSession()) return response()->json(['message' => 'Session authentication is required.'], 419);
        $input = $request->validate(['username' => ['required', 'string'], 'password' => ['required', 'string']]);
        $username = strtolower(trim($input['username']));
        $key = 'password:'.sha1($username.'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) return $this->limited($key);

        $user = User::where('username', $username)->first();
        if (!$user || !Hash::check($input['password'], $user->password)) {
            RateLimiter::hit($key, 300);
            return response()->json(['message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'], 422);
        }

        RateLimiter::clear($key);
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $twoFactor = UserTwoFactor::where('user_id', $user->id)->first();
        $configured = $twoFactor?->two_factor_enabled && $twoFactor->two_factor_confirmed_at && $twoFactor->two_factor_secret;
        $request->session()->put('two_factor_pending', ['user_id' => $user->id, 'until' => time() + 600]);
        return response()->json([
            'two_factor_required' => true,
            'next' => $configured ? '/2fa/verify' : '/2fa/setup',
        ]);
    }

    public function challengeStatus(Request $request)
    {
        $user = $this->pendingUser($request);
        $configured = $user && UserTwoFactor::where('user_id', $user->id)->where('two_factor_enabled', true)
            ->whereNotNull('two_factor_confirmed_at')->whereNotNull('two_factor_secret')->exists();
        return response()->json(['two_factor_required' => (bool) $user,
            'next' => $user ? ($configured ? '/2fa/verify' : '/2fa/setup') : null]);
    }

    public function challenge(Request $request, Totp $totp)
    {
        $data = $request->validate(['code' => ['required', 'string']]);
        $user = $this->pendingUser($request);
        if (!$user) return response()->json(['message' => 'Verification expired. Please sign in again.'], 419);
        if (!UserTwoFactor::where('user_id', $user->id)->where('two_factor_enabled', true)->whereNotNull('two_factor_confirmed_at')->exists()) {
            return response()->json(['message' => 'Authenticator setup is required.'], 409);
        }
        $key = 'two-factor:'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) return $this->limited($key);

        $valid = DB::transaction(function () use ($user, $data, $totp) {
            $factor = UserTwoFactor::where('user_id', $user->id)->lockForUpdate()->first();
            if (!$factor?->two_factor_enabled) return false;
            $code = strtoupper(preg_replace('/[\s-]/', '', $data['code']));
            if (preg_match('/^\d{6}$/D', $code)) {
                $counter = $totp->matchedCounter($factor->two_factor_secret, $code, $factor->last_used_counter);
                if ($counter === null) return false;
                $factor->last_used_counter = $counter;
                $factor->save();
                return true;
            }
            $hash = hash_hmac('sha256', $code, config('app.key'));
            $recovery = DB::table('two_factor_recovery_codes')
                ->where('user_id', $user->id)->where('code_hash', $hash)->whereNull('used_at')->lockForUpdate()->first();
            if (!$recovery) return false;
            DB::table('two_factor_recovery_codes')->where('id', $recovery->id)->whereNull('used_at')->update(['used_at' => now(), 'updated_at' => now()]);
            return true;
        });

        if (!$valid) {
            RateLimiter::hit($key, 300);
            return response()->json(['message' => 'Invalid or expired verification code.'], 422);
        }
        RateLimiter::clear($key);
        $this->completeLogin($request, $user);
        return response()->json(['message' => 'Login success', 'user' => $user]);
    }

    public function pendingSetup(Request $request, Totp $totp)
    {
        $user = $this->pendingUser($request);
        if (!$user) return response()->json(['message' => 'Setup expired. Please sign in again.'], 419);
        $factor = UserTwoFactor::firstOrCreate(['user_id' => $user->id]);
        if ($factor->two_factor_enabled) return response()->json(['message' => 'Authenticator setup is already complete.'], 409);
        if (!$factor->pending_secret || !$factor->pending_created_at || $factor->pending_created_at->lt(now()->subMinutes(10))) {
            $factor->update(['pending_secret' => $totp->secret(), 'pending_created_at' => now()]);
        }
        return response()->json(['secret' => $factor->pending_secret,
            'otpauth_uri' => $totp->uri($factor->pending_secret, $user->email)]);
    }

    public function pendingConfirm(Request $request, Totp $totp)
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $user = $this->pendingUser($request);
        if (!$user) return response()->json(['message' => 'Setup expired. Please sign in again.'], 419);
        $key = 'two-factor-setup:'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) return $this->limited($key);
        $result = DB::transaction(function () use ($user, $totp, $data) {
            $factor = UserTwoFactor::where('user_id', $user->id)->lockForUpdate()->first();
            if (!$factor?->pending_secret || $factor->two_factor_enabled || !$factor->pending_created_at
                || $factor->pending_created_at->lt(now()->subMinutes(10))) return null;
            $counter = $totp->matchedCounter($factor->pending_secret, $data['code']);
            if ($counter === null) return null;
            $factor->update(['two_factor_secret' => $factor->pending_secret, 'pending_secret' => null,
                'pending_created_at' => null, 'two_factor_enabled' => true,
                'two_factor_confirmed_at' => now(), 'last_used_counter' => $counter]);
            return $this->replaceRecoveryCodes($user->id);
        });
        if (!$result) {
            RateLimiter::hit($key, 300);
            return response()->json(['message' => 'Invalid or expired verification code.'], 422);
        }
        RateLimiter::clear($key);
        $this->completeLogin($request, $user);
        return response()->json(['user' => $user, 'recovery_codes' => $result]);
    }

    public function status(Request $request)
    {
        $factor = UserTwoFactor::where('user_id', $request->user()->id)->first();
        return response()->json([
            'enabled' => (bool) $factor?->two_factor_enabled,
            'confirmed_at' => $factor?->two_factor_confirmed_at,
            'recovery_codes_remaining' => DB::table('two_factor_recovery_codes')->where('user_id', $request->user()->id)->whereNull('used_at')->count(),
        ]);
    }

    public function setup(Request $request, Totp $totp)
    {
        $this->confirmPassword($request);
        $factor = UserTwoFactor::firstOrCreate(['user_id' => $request->user()->id]);
        if ($factor->two_factor_enabled) return response()->json(['message' => 'Disable or reconfigure 2FA first.'], 409);
        $secret = $totp->secret();
        $factor->update(['pending_secret' => $secret, 'pending_created_at' => now()]);
        return response()->json(['secret' => $secret, 'otpauth_uri' => $totp->uri($secret, $request->user()->email)]);
    }

    public function confirm(Request $request, Totp $totp)
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $key = 'two-factor-setup:'.$request->user()->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) return $this->limited($key);
        $result = DB::transaction(function () use ($request, $totp, $data) {
            $factor = UserTwoFactor::where('user_id', $request->user()->id)->lockForUpdate()->first();
            if (!$factor?->pending_secret || $factor->two_factor_enabled || !$factor->pending_created_at || $factor->pending_created_at->lt(now()->subMinutes(10))) return null;
            $counter = $totp->matchedCounter($factor->pending_secret, $data['code']);
            if ($counter === null) return null;
            $factor->update([
                'two_factor_secret' => $factor->pending_secret,
                'pending_secret' => null,
                'pending_created_at' => null,
                'two_factor_enabled' => true,
                'two_factor_confirmed_at' => now(),
                'last_used_counter' => $counter,
            ]);
            return $this->replaceRecoveryCodes($request->user()->id);
        });
        if (!$result) {
            RateLimiter::hit($key, 300);
            return response()->json(['message' => 'Invalid or expired verification code.'], 422);
        }
        RateLimiter::clear($key);
        return response()->json(['recovery_codes' => $result]);
    }

    public function disable(Request $request)
    {
        $this->confirmPassword($request);
        DB::transaction(function () use ($request) {
            UserTwoFactor::where('user_id', $request->user()->id)->delete();
            DB::table('two_factor_recovery_codes')->where('user_id', $request->user()->id)->delete();
        });
        $userId = $request->user()->id;
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('two_factor_pending', ['user_id' => $userId, 'until' => time() + 600]);
        return response()->noContent();
    }

    public function regenerate(Request $request)
    {
        $this->confirmPassword($request);
        abort_unless(UserTwoFactor::where('user_id', $request->user()->id)->where('two_factor_enabled', true)->exists(), 409);
        return response()->json(['recovery_codes' => DB::transaction(fn () => $this->replaceRecoveryCodes($request->user()->id))]);
    }

    public function reconfigure(Request $request, Totp $totp)
    {
        $this->confirmPassword($request);
        $factor = UserTwoFactor::where('user_id', $request->user()->id)->where('two_factor_enabled', true)->firstOrFail();
        $secret = $totp->secret();
        $factor->update(['pending_secret' => $secret, 'pending_created_at' => now()]);
        return response()->json(['secret' => $secret, 'otpauth_uri' => $totp->uri($secret, $request->user()->email)]);
    }

    public function confirmReconfigure(Request $request, Totp $totp)
    {
        $this->confirmPassword($request);
        $data = $request->validate(['code' => ['required', 'digits:6'], 'password' => ['required', 'string']]);
        $key = 'two-factor-setup:'.$request->user()->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) return $this->limited($key);
        $result = DB::transaction(function () use ($request, $totp, $data) {
            $factor = UserTwoFactor::where('user_id', $request->user()->id)->lockForUpdate()->first();
            if (!$factor?->two_factor_enabled || !$factor->pending_secret || !$factor->pending_created_at || $factor->pending_created_at->lt(now()->subMinutes(10))) return null;
            $counter = $totp->matchedCounter($factor->pending_secret, $data['code']);
            if ($counter === null) return null;
            $factor->update(['two_factor_secret' => $factor->pending_secret, 'pending_secret' => null, 'pending_created_at' => null, 'two_factor_confirmed_at' => now(), 'last_used_counter' => $counter]);
            return $this->replaceRecoveryCodes($request->user()->id);
        });
        if (!$result) {
            RateLimiter::hit($key, 300);
            return response()->json(['message' => 'Invalid or expired verification code.'], 422);
        }
        RateLimiter::clear($key);
        return response()->json(['recovery_codes' => $result]);
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get('two_factor_pending');
        if (!$pending || ($pending['until'] ?? 0) < time()) {
            $request->session()->forget('two_factor_pending');
            return null;
        }
        return User::find($pending['user_id']);
    }

    private function completeLogin(Request $request, User $user): void
    {
        $request->session()->forget('two_factor_pending');
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('2fa_verified', true);
        $request->session()->put('2fa_verified_user_id', $user->id);
    }

    private function confirmPassword(Request $request): void
    {
        $data = $request->validate(['password' => ['required', 'string']]);
        $key = 'two-factor-password:'.$request->user()->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) abort(429, 'Too many attempts. Please try again later.');
        if (!Hash::check($data['password'], $request->user()->password)) {
            RateLimiter::hit($key, 300);
            abort(422, 'Incorrect password.');
        }
        RateLimiter::clear($key);
    }

    private function replaceRecoveryCodes(int $userId): array
    {
        DB::table('two_factor_recovery_codes')->where('user_id', $userId)->delete();
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $code = strtoupper(Str::random(5).'-'.Str::random(5));
            $codes[] = $code;
            DB::table('two_factor_recovery_codes')->insert([
                'user_id' => $userId,
                'code_hash' => hash_hmac('sha256', str_replace('-', '', $code), config('app.key')),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return $codes;
    }

    private function limited(string $key)
    {
        return response()->json(['message' => 'Too many attempts. Please try again later.', 'retry_after' => RateLimiter::availableIn($key)], 429);
    }
}
