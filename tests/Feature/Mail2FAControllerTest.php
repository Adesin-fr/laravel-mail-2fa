<?php

use AdesinFr\Mail2FA\Notifications\SendMail2FACode;
use AdesinFr\Mail2FA\Tests\Fixtures\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    Notification::fake();
});

test('verification page requires authentication', function () {
    $response = $this->get(route('mail2fa.verify'));

    $response->assertRedirect(route('login'));
});

test('verification page displays for authenticated user', function () {
    $response = $this->actingAs($this->user)
        ->get(route('mail2fa.verify'));

    $response->assertOk();
    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('Mail2FA/Verify')
            ->has('email')
            ->has('expiresAt')
            ->has('codeLength')
    );
});

test('verification page sends code email', function () {
    $this->actingAs($this->user)
        ->get(route('mail2fa.verify'));

    Notification::assertSentTo($this->user, SendMail2FACode::class);
});

test('verification success redirects to intended url', function () {
    $plainCode = '123456';

    $this->user->update([
        'mfa_code' => bcrypt($plainCode),
        'mfa_expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('mail2fa.verify.post'), [
            'code' => $plainCode,
        ]);

    $response->assertRedirect('/');
    expect(session()->has('mail2fa_verified'))->toBeTrue();
});

test('verification failure returns error', function () {
    $this->user->update([
        'mfa_code' => bcrypt('123456'),
        'mfa_expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('mail2fa.verify.post'), [
            'code' => '000000',
        ]);

    $response->assertSessionHasErrors('code');
});

test('verification with invalid length fails validation', function () {
    $response = $this->actingAs($this->user)
        ->post(route('mail2fa.verify.post'), [
            'code' => '123',
        ]);

    $response->assertSessionHasErrors('code');
});

test('resend sends new code', function () {
    $this->user->update([
        'mfa_code' => bcrypt('123456'),
        'mfa_expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->actingAs($this->user)
        ->withSession(['mail2fa.last_sent_at' => now()->subSeconds(61)])
        ->post(route('mail2fa.resend'));

    $response->assertRedirect();
    $response->assertSessionHas('success');
    Notification::assertSentTo($this->user, SendMail2FACode::class);
});

test('resend respects cooldown', function () {
    $this->user->update([
        'mfa_code' => bcrypt('123456'),
        'mfa_expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->actingAs($this->user)
        ->withSession(['mail2fa.last_sent_at' => now()])
        ->post(route('mail2fa.resend'));

    $response->assertSessionHasErrors('resend');
});

test('code is cleared after successful verification', function () {
    $plainCode = '123456';

    $this->user->update([
        'mfa_code' => bcrypt($plainCode),
        'mfa_expires_at' => now()->addMinutes(10),
    ]);

    $this->actingAs($this->user)
        ->post(route('mail2fa.verify.post'), [
            'code' => $plainCode,
        ]);

    $this->user->refresh();

    expect($this->user->mfa_code)->toBeNull();
    expect($this->user->mfa_expires_at)->toBeNull();
});
