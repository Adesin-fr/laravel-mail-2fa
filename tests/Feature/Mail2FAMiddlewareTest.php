<?php

use AdesinFr\Mail2FA\Tests\Fixtures\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    // Register a test route with the middleware
    Route::middleware(['web', 'auth', 'mail2fa'])->get('/protected', function () {
        return 'Protected Content';
    })->name('protected');
});

test('middleware redirects unverified user', function () {
    $response = $this->actingAs($this->user)
        ->get('/protected');

    $response->assertRedirect(route('mail2fa.verify'));
});

test('middleware allows verified user', function () {
    $response = $this->actingAs($this->user)
        ->withSession(['mail2fa_verified' => now()])
        ->get('/protected');

    $response->assertOk();
    $response->assertSee('Protected Content');
});

test('middleware stores intended url', function () {
    $this->actingAs($this->user)
        ->get('/protected');

    expect(session('mail2fa.intended_url'))->toBe(url('/protected'));
});

test('middleware skips when disabled', function () {
    config(['mail2fa.enabled' => false]);

    $response = $this->actingAs($this->user)
        ->get('/protected');

    $response->assertOk();
});

test('middleware allows unauthenticated requests', function () {
    Route::middleware(['web', 'mail2fa'])->get('/public-with-2fa', function () {
        return 'Public Content';
    });

    $response = $this->get('/public-with-2fa');

    $response->assertOk();
});

test('verification does not expire when lifetime is null', function () {
    config(['mail2fa.verification_lifetime' => null]);

    $response = $this->actingAs($this->user)
        ->withSession(['mail2fa_verified' => now()->subDays(30)])
        ->get('/protected');

    $response->assertOk();
});

test('verification redirects to root by default', function () {
    $plainCode = '123456';

    $this->user->update([
        'mfa_code' => bcrypt($plainCode),
        'mfa_expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('mail2fa.verify.post'), [
            'code' => $plainCode,
        ]);

    // Without intended URL in session, redirects to /
    $response->assertRedirect('/');
});
