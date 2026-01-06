<?php

use AdesinFr\Mail2FA\Notifications\SendMail2FACode;
use AdesinFr\Mail2FA\Tests\Fixtures\User;
use AdesinFr\Mail2FA\Traits\Mail2FATrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    Notification::fake();
});

// Helper class to use the trait in tests
class TraitTestHelper
{
    use Mail2FATrait;

    public function testGenerateCode(): string
    {
        return $this->generateCode();
    }

    public function testCreateAndSendCode($user): string
    {
        return $this->createAndSendCode($user);
    }

    public function testIsCodeExpired($user): bool
    {
        return $this->isCodeExpired($user);
    }

    public function testVerifyCode($user, string $code): array
    {
        return $this->verifyCode($user, $code);
    }

    public function testCanResend(Request $request): bool
    {
        return $this->canResend($request);
    }

    public function testResendCode($user, Request $request): array
    {
        return $this->resendCode($user, $request);
    }
}

test('generated code has correct length', function () {
    $helper = new TraitTestHelper;

    config(['mail2fa.code_length' => 6]);
    expect(strlen($helper->testGenerateCode()))->toBe(6);

    config(['mail2fa.code_length' => 4]);
    expect(strlen($helper->testGenerateCode()))->toBe(4);

    config(['mail2fa.code_length' => 8]);
    expect(strlen($helper->testGenerateCode()))->toBe(8);
});

test('generated code is numeric', function () {
    $helper = new TraitTestHelper;
    $code = $helper->testGenerateCode();

    expect(ctype_digit($code))->toBeTrue();
});

test('code expiration check works correctly', function () {
    $helper = new TraitTestHelper;

    // No expiration set
    expect($helper->testIsCodeExpired($this->user))->toBeTrue();

    // Expired code
    $this->user->mfa_expires_at = now()->subMinutes(1);
    expect($helper->testIsCodeExpired($this->user))->toBeTrue();

    // Valid code
    $this->user->mfa_expires_at = now()->addMinutes(10);
    expect($helper->testIsCodeExpired($this->user))->toBeFalse();
});

test('create and send code updates user and sends notification', function () {
    $helper = new TraitTestHelper;
    $plainCode = $helper->testCreateAndSendCode($this->user);

    expect($plainCode)->toHaveLength(6);
    expect($this->user->fresh()->mfa_code)->not->toBeNull();
    expect($this->user->fresh()->mfa_expires_at)->not->toBeNull();

    Notification::assertSentTo($this->user, SendMail2FACode::class);
});

test('verify code succeeds with correct code', function () {
    $helper = new TraitTestHelper;
    $plainCode = '123456';

    $this->user->update([
        'mfa_code' => bcrypt($plainCode),
        'mfa_expires_at' => now()->addMinutes(10),
    ]);

    $result = $helper->testVerifyCode($this->user, $plainCode);

    expect($result['success'])->toBeTrue();
    expect($this->user->fresh()->mfa_code)->toBeNull();
});

test('verify code fails with wrong code', function () {
    $helper = new TraitTestHelper;

    $this->user->update([
        'mfa_code' => bcrypt('123456'),
        'mfa_expires_at' => now()->addMinutes(10),
    ]);

    $result = $helper->testVerifyCode($this->user, '000000');

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('Invalid verification code.');
});

test('verify code fails when expired', function () {
    $helper = new TraitTestHelper;

    $this->user->update([
        'mfa_code' => bcrypt('123456'),
        'mfa_expires_at' => now()->subMinutes(1),
    ]);

    $result = $helper->testVerifyCode($this->user, '123456');

    expect($result['success'])->toBeFalse();
    expect($result['should_resend'])->toBeTrue();
});

test('verify code fails when no code exists', function () {
    $helper = new TraitTestHelper;

    $result = $helper->testVerifyCode($this->user, '123456');

    expect($result['success'])->toBeFalse();
    expect($result['should_resend'])->toBeTrue();
});

test('can resend returns true when no previous send', function () {
    $helper = new TraitTestHelper;
    $request = Request::create('/');
    $request->setLaravelSession(app('session.store'));

    expect($helper->testCanResend($request))->toBeTrue();
});

test('can resend returns false during cooldown', function () {
    $helper = new TraitTestHelper;
    $request = Request::create('/');
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('mail2fa.last_sent_at', now());

    expect($helper->testCanResend($request))->toBeFalse();
});

test('can resend returns true after cooldown', function () {
    $helper = new TraitTestHelper;
    $request = Request::create('/');
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('mail2fa.last_sent_at', now()->subSeconds(61));

    expect($helper->testCanResend($request))->toBeTrue();
});

test('resend code respects cooldown', function () {
    $helper = new TraitTestHelper;
    $request = Request::create('/');
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('mail2fa.last_sent_at', now());

    $result = $helper->testResendCode($this->user, $request);

    expect($result['success'])->toBeFalse();
    expect($result)->toHaveKey('cooldown_remaining');
});

test('resend code succeeds after cooldown', function () {
    $helper = new TraitTestHelper;
    $request = Request::create('/');
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('mail2fa.last_sent_at', now()->subSeconds(61));

    $result = $helper->testResendCode($this->user, $request);

    expect($result['success'])->toBeTrue();
    Notification::assertSentTo($this->user, SendMail2FACode::class);
});
