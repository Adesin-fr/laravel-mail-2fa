<?php

namespace AdesinFr\Mail2FA\Tests;

use AdesinFr\Mail2FA\Mail2FAServiceProvider;
use AdesinFr\Mail2FA\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Define a login route for tests
        $this->app['router']->get('login', fn () => 'Login Page')->name('login');

        // Create a minimal app view for Inertia
        View::addLocation(__DIR__ . '/views');
    }

    protected function getPackageProviders($app): array
    {
        return [
            InertiaServiceProvider::class,
            Mail2FAServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Database
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // App config
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));

        // Session
        $app['config']->set('session.driver', 'array');

        // Auth - use our test User model
        $app['config']->set('auth.providers.users.model', User::class);

        // Mail2FA config
        $app['config']->set('mail2fa.enabled', true);
        $app['config']->set('mail2fa.code_length', 6);
        $app['config']->set('mail2fa.code_expiration', 10);
        $app['config']->set('mail2fa.resend_cooldown', 60);

        // Inertia config
        $app['config']->set('inertia.testing.ensure_pages_exist', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Create the users table with MFA columns directly
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('mfa_code')->nullable();
            $table->timestamp('mfa_expires_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }
}
