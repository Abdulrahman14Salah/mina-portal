<?php

namespace App\Http\Middleware;

use App\Services\Auth\AuditLogService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if (Auth::check() && ! Auth::user()->is_active) {
            app(AuditLogService::class)->log('forced_logout', Auth::user(), ['reason' => 'account_deactivated']);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => __('auth.account_deactivated')]);
        }

        return $next($request);
    }
}
