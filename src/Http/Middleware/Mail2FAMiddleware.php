<?php

namespace AdesinFr\Mail2FA\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Mail2FAMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if 2FA is disabled
        if (! config('mail2fa.enabled', true)) {
            return $next($request);
        }

        // Skip if user is not authenticated
        if (! $request->user()) {
            return $next($request);
        }

        // Check if already verified in this session
        if ($this->isVerified($request)) {
            return $next($request);
        }

        // Store the intended URL
        $request->session()->put('mail2fa.intended_url', $request->url());

        // Redirect to verification page
        return redirect()->route(config('mail2fa.routes.names.verify', 'mail2fa.verify'));
    }

    /**
     * Check if the user has been verified in this session.
     */
    protected function isVerified(Request $request): bool
    {
        $sessionKey = config('mail2fa.session_key', 'mail2fa_verified');
        $verifiedAt = $request->session()->get($sessionKey);

        if (! $verifiedAt) {
            return false;
        }

        // Check if verification has expired
        $lifetime = config('mail2fa.verification_lifetime');
        if ($lifetime) {
            $verifiedAt = $verifiedAt instanceof Carbon ? $verifiedAt : Carbon::parse($verifiedAt);
            if (now()->diffInMinutes($verifiedAt) > $lifetime) {
                $request->session()->forget($sessionKey);

                return false;
            }
        }

        return true;
    }
}
