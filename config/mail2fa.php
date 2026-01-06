<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable 2FA
    |--------------------------------------------------------------------------
    |
    | This option controls whether 2FA verification is enabled globally.
    |
    */
    'enabled' => env('MAIL2FA_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Code Length
    |--------------------------------------------------------------------------
    |
    | The length of the verification code sent to the user.
    | Default is 6 digits.
    |
    */
    'code_length' => env('MAIL2FA_CODE_LENGTH', 6),

    /*
    |--------------------------------------------------------------------------
    | Code Expiration
    |--------------------------------------------------------------------------
    |
    | The number of minutes before a verification code expires.
    | Default is 10 minutes.
    |
    */
    'code_expiration' => env('MAIL2FA_CODE_EXPIRATION', 10),

    /*
    |--------------------------------------------------------------------------
    | Resend Cooldown
    |--------------------------------------------------------------------------
    |
    | The number of seconds before a user can request a new code.
    |
    */
    'resend_cooldown' => env('MAIL2FA_RESEND_COOLDOWN', 60),

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Configuration for the package routes.
    |
    */
    'routes' => [
        'prefix' => '2fa',
        'middleware' => ['web', 'auth'],
        'names' => [
            'verify' => 'mail2fa.verify',
            'verify.post' => 'mail2fa.verify.post',
            'resend' => 'mail2fa.resend',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the verification email.
    |
    */
    'email' => [
        'subject' => 'Your Verification Code',
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Key
    |--------------------------------------------------------------------------
    |
    | The session key used to store verification status.
    |
    */
    'session_key' => 'mail2fa_verified',

    /*
    |--------------------------------------------------------------------------
    | Verification Lifetime
    |--------------------------------------------------------------------------
    |
    | The number of minutes the verification session remains valid.
    | Set to null to keep it valid for the entire session.
    |
    */
    'verification_lifetime' => env('MAIL2FA_VERIFICATION_LIFETIME', null),

    /*
    |--------------------------------------------------------------------------
    | Inertia Component Path
    |--------------------------------------------------------------------------
    |
    | The path to the Inertia components for the 2FA pages.
    |
    */
    'inertia' => [
        'verify_component' => 'Mail2FA/Verify',
    ],
];
