# Laravel Mail 2FA

A simple Laravel package for two-factor authentication via email OTP codes with InertiaJS support.

## Requirements

- PHP 8.2+
- Laravel 11.0+
- InertiaJS with Vue 3

## Installation

Install the package via Composer:

```bash
composer require adesin-fr/laravel-mail-2fa
```

## Publishing Assets

### Publish all assets at once:

```bash
php artisan vendor:publish --tag=mail2fa
```

### Or publish individually:

**Configuration file:**
```bash
php artisan vendor:publish --tag=mail2fa-config
```

**Database migrations:**
```bash
php artisan vendor:publish --tag=mail2fa-migrations
```

**Email views (Blade templates):**
```bash
php artisan vendor:publish --tag=mail2fa-views
```

**Inertia Vue components:**
```bash
php artisan vendor:publish --tag=mail2fa-inertia
```

## Run Migrations

The package adds `mfa_code` and `mfa_expires_at` columns to your `users` table:

```bash
php artisan migrate
```

## Configuration

After publishing, edit `config/mail2fa.php`:

```php
return [
    // Enable/disable 2FA globally
    'enabled' => env('MAIL2FA_ENABLED', true),

    // Length of the verification code (default: 6)
    'code_length' => env('MAIL2FA_CODE_LENGTH', 6),

    // Code expiration in minutes (default: 10)
    'code_expiration' => env('MAIL2FA_CODE_EXPIRATION', 10),

    // Cooldown between resend requests in seconds (default: 60)
    'resend_cooldown' => env('MAIL2FA_RESEND_COOLDOWN', 60),

    // Verification session lifetime in minutes (null = entire session)
    'verification_lifetime' => env('MAIL2FA_VERIFICATION_LIFETIME', null),

    // Route configuration
    'routes' => [
        'prefix' => '2fa',
        'middleware' => ['web', 'auth'],
        'names' => [
            'verify' => 'mail2fa.verify',
            'verify.post' => 'mail2fa.verify.post',
            'resend' => 'mail2fa.resend',
        ],
    ],

    // Inertia component path
    'inertia' => [
        'verify_component' => 'Mail2FA/Verify',
    ],
];
```

## User Model Setup

Make sure your User model has the `mfa_code` and `mfa_expires_at` fields in the `$fillable` array:

```php
// app/Models/User.php

protected $fillable = [
    'name',
    'email',
    'password',
    'mfa_code',      // Add this
    'mfa_expires_at', // Add this
];

protected $casts = [
    // ... other casts
    'mfa_expires_at' => 'datetime',
];
```

## Usage

### Protecting Routes

Add the `mail2fa` middleware to any routes you want to protect:

```php
// Single route
Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'mail2fa']);

// Route group
Route::middleware(['auth', 'mail2fa'])->group(function () {
    Route::get('/dashboard', DashboardController::class);
    Route::get('/settings', SettingsController::class);
});
```

### How It Works

1. User attempts to access a protected route
2. Middleware checks if user has verified 2FA in current session
3. If not verified, user is redirected to `/2fa/verify`
4. A 6-digit code is sent to the user's email
5. User enters the code
6. If correct, user is redirected to their intended destination
7. Session is marked as verified for the configured lifetime

### Environment Variables

```env
MAIL2FA_ENABLED=true
MAIL2FA_CODE_LENGTH=6
MAIL2FA_CODE_EXPIRATION=10
MAIL2FA_RESEND_COOLDOWN=60
MAIL2FA_VERIFICATION_LIFETIME=
```

## Customization

### Custom Email Template

After publishing views, edit `resources/views/vendor/mail2fa/emails/verification-code.blade.php`.

### Custom Inertia Components

After publishing Inertia components, edit the Vue files in `resources/js/Pages/Mail2FA/`:

- `Verify.vue` - Main verification page

### Custom Inertia Component Path

If you want to use different component paths:

```php
// config/mail2fa.php
'inertia' => [
    'verify_component' => 'Auth/TwoFactorVerify',
],
```

## API

### Routes

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | /2fa/verify | mail2fa.verify | Show verification form |
| POST | /2fa/verify | mail2fa.verify.post | Submit verification code |
| POST | /2fa/resend | mail2fa.resend | Resend verification code |

### Clear Verification

To programmatically clear a user's verification status (e.g., on logout):

```php
use AdesinFr\Mail2FA\Traits\Mail2FATrait;

class AuthController
{
    use Mail2FATrait;

    public function logout(Request $request)
    {
        $this->clearVerification($request);
        // ... rest of logout logic
    }
}
```

## Testing

Run tests with Pest:

```bash
composer test
```

Or run Pest directly:

```bash
./vendor/bin/pest
```

## Security Considerations

- Codes are stored hashed (bcrypt) in the database
- Configurable code expiration
- Rate limiting on code resend via cooldown
- Codes are cleared after successful verification
- Session-based verification status

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
