<?php

namespace AdesinFr\Mail2FA\Http\Controllers;

use AdesinFr\Mail2FA\Traits\Mail2FATrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class Mail2FAController extends Controller
{
    use Mail2FATrait;

    /**
     * Show the verification page.
     */
    public function show(Request $request): InertiaResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // If no active code or expired, create and send new one
        if (! $user->mfa_code || $this->isCodeExpired($user)) {
            $this->createAndSendCode($user);
            $request->session()->put('mail2fa.last_sent_at', now());
        }

        return Inertia::render(config('mail2fa.inertia.verify_component', 'Mail2FA/Verify'), [
            'email' => $this->maskEmail($user->email),
            'expiresAt' => $user->mfa_expires_at?->toIso8601String(),
            'canResend' => $this->canResend($request),
            'resendCooldown' => $this->getResendCooldownRemaining($request),
            'codeLength' => config('mail2fa.code_length', 6),
        ]);
    }

    /**
     * Verify the submitted code.
     */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:' . config('mail2fa.code_length', 6)],
        ]);

        $user = $request->user();
        $result = $this->verifyCode($user, $request->input('code'));

        if ($result['success']) {
            $this->markAsVerified($request);
            $intendedUrl = $this->getIntendedUrl($request);

            return redirect($intendedUrl)->with('success', $result['message']);
        }

        return back()->withErrors(['code' => $result['message']]);
    }

    /**
     * Resend the verification code.
     */
    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();
        $result = $this->resendCode($user, $request);

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->withErrors(['resend' => $result['message']]);
    }

    /**
     * Mask the email for display.
     */
    protected function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        $name = $parts[0];
        $domain = $parts[1];

        if (strlen($name) <= 4) {
            $maskedName = substr($name, 0, 1) . str_repeat('*', strlen($name) - 1);
        } else {
            $maskedName = substr($name, 0, 2) . str_repeat('*', strlen($name) - 4) . substr($name, -2);
        }

        return $maskedName . '@' . $domain;
    }
}
