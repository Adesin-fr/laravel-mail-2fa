@component('mail::message')
# Your Verification Code

Hello {{ $user->name ?? 'there' }},

You are receiving this email because a verification code was requested for your account.

@component('mail::panel')
<div style="text-align: center;">
    <span style="font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #3b82f6;">{{ $code }}</span>
</div>
@endcomponent

This code will expire in **{{ $expiration }} minutes**.

If you did not request this verification code, no further action is required. However, if you suspect unauthorized access to your account, please contact support immediately.

**Security Tips:**
- Never share this code with anyone
- Our team will never ask you for this code
- This code is only valid for one use

Thanks,<br>
{{ config('app.name') }}

@component('mail::subcopy')
This is an automated message. Please do not reply to this email.
@endcomponent
@endcomponent
