<?php

namespace App\Http\Middleware;

use App\Models\UserTwoFactor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequireVerifiedTwoFactor
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('web')->user();
        $verified = $request->hasSession()
            && $request->session()->get('2fa_verified') === true
            && $request->session()->get('2fa_verified_user_id') === $user?->id;

        if (!$user || !$verified || !UserTwoFactor::where('user_id', $user->id)
            ->where('two_factor_enabled', true)->whereNotNull('two_factor_confirmed_at')->exists()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Two-factor authentication is required.'], 401);
            }
            return redirect('/login');
        }

        return $next($request);
    }
}
