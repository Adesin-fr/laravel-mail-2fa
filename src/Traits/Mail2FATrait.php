<?php

namespace AdesinFr\Mail2FA\Traits;

use AdesinFr\Mail2FA\Notifications\SendMail2FACode;
use Carbon\Carbon;
use Illuminate\Http\Request;

trait Mail2FATrait
{
    /**
     * Generate a random numeric code.
     */
    protected function generateCode(): string
    {
        $length = config('mail2fa.code_length', 6);
        $min = (int) str_pad('1', $length, '0');
        $max = (int) str_pad('', $length, '9');

        return (string) random_int($min, $max);
    }

    /**
     * Create and send a new MFA code to the user.
     */
    protected function createAndSendCode($user): string
    {
        $plainCode = $this->generateCode();
        $expiration = config('mail2fa.code_expiration', 10);

        $user->update([
            'mfa_code' => bcrypt($plainCode),
            'mfa_expires_at' => now()->addMinutes($expiration),
        ]);

        $user->notify(new SendMail2FACode($plainCode));

        return $plainCode;
    }

    /**
     * Check if the code has expired.
     */
    protected function isCodeExpired($user): bool
    {
        if (! $user->mfa_expires_at) {
            return true;
        }

        return Carbon::parse($user->mfa_expires_at)->isPast();
    }

    /**
     * Check if resend is allowed based on cooldown.
     */
    protected function canResend(Request $request): bool
    {
        $lastSent = $request->session()->get('mail2fa.last_sent_at');

        if (! $lastSent) {
            return true;
        }

        $cooldown = config('mail2fa.resend_cooldown', 60);

        return Carbon::parse($lastSent)->addSeconds($cooldown)->isPast();
    }

    /**
     * Get remaining seconds until resend is allowed.
     */
    protected function getResendCooldownRemaining(Request $request): int
    {
        $lastSent = $request->session()->get('mail2fa.last_sent_at');

        if (! $lastSent) {
            return 0;
        }

        $cooldown = config('mail2fa.resend_cooldown', 60);
        $canResendAt = Carbon::parse($lastSent)->addSeconds($cooldown);

        if ($canResendAt->isPast()) {
            return 0;
        }

        return (int) now()->diffInSeconds($canResendAt);
    }

    /**
     * Verify the code for a user.
     */
    protected function verifyCode($user, string $inputCode): array
    {
        if (! $user->mfa_code) {
            return [
                'success' => false,
                'message' => 'No verification code found. Please request a new one.',
                'should_resend' => true,
            ];
        }

        if ($this->isCodeExpired($user)) {
            return [
                'success' => false,
                'message' => 'The verification code has expired. Please request a new one.',
                'should_resend' => true,
            ];
        }

        if (password_verify($inputCode, $user->mfa_code)) {
            // Clear the code after successful verification
            $user->update([
                'mfa_code' => null,
                'mfa_expires_at' => null,
            ]);

            return [
                'success' => true,
                'message' => 'Verification successful.',
            ];
        }

        return [
            'success' => false,
            'message' => 'Invalid verification code.',
        ];
    }

    /**
     * Resend the verification code.
     */
    protected function resendCode($user, Request $request): array
    {
        if (! $this->canResend($request)) {
            return [
                'success' => false,
                'message' => 'Please wait before requesting a new code.',
                'cooldown_remaining' => $this->getResendCooldownRemaining($request),
            ];
        }

        $this->createAndSendCode($user);
        $request->session()->put('mail2fa.last_sent_at', now());

        return [
            'success' => true,
            'message' => 'A new verification code has been sent to your email.',
        ];
    }

    /**
     * Mark the session as verified.
     */
    protected function markAsVerified(Request $request): void
    {
        $sessionKey = config('mail2fa.session_key', 'mail2fa_verified');
        $request->session()->put($sessionKey, now());
        $request->session()->forget('mail2fa.last_sent_at');
        $request->session()->forget('mail2fa.intended_url');
    }

    /**
     * Clear the verification status.
     */
    protected function clearVerification(Request $request): void
    {
        $sessionKey = config('mail2fa.session_key', 'mail2fa_verified');
        $request->session()->forget($sessionKey);
        $request->session()->forget('mail2fa.intended_url');
        $request->session()->forget('mail2fa.last_sent_at');
    }

    /**
     * Get the intended URL after verification.
     */
    protected function getIntendedUrl(Request $request): string
    {
        return $request->session()->pull('mail2fa.intended_url', '/');
    }
}
