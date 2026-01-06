<?php

use AdesinFr\Mail2FA\Http\Controllers\Mail2FAController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('mail2fa.routes.middleware', ['web', 'auth']))
    ->prefix(config('mail2fa.routes.prefix', '2fa'))
    ->group(function () {
        Route::get('/verify', [Mail2FAController::class, 'show'])
            ->name(config('mail2fa.routes.names.verify', 'mail2fa.verify'));

        Route::post('/verify', [Mail2FAController::class, 'verify'])
            ->name(config('mail2fa.routes.names.verify.post', 'mail2fa.verify.post'));

        Route::post('/resend', [Mail2FAController::class, 'resend'])
            ->name(config('mail2fa.routes.names.resend', 'mail2fa.resend'));
    });
