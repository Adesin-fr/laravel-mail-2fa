<?php

namespace AdesinFr\Mail2FA;

use AdesinFr\Mail2FA\Http\Middleware\Mail2FAMiddleware;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class Mail2FAServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerRoutes();
        $this->registerMiddleware();
    }

    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/mail2fa.php',
            'mail2fa'
        );
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__ . '/../config/mail2fa.php' => config_path('mail2fa.php'),
            ], 'mail2fa-config');

            // Publish migrations
            $this->publishesMigrations([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'mail2fa-migrations');

            // Publish email views
            $this->publishes([
                __DIR__ . '/../resources/views/emails' => resource_path('views/vendor/mail2fa/emails'),
            ], 'mail2fa-views');

            // Publish Inertia components
            $this->publishes([
                __DIR__ . '/../resources/js/Pages/Mail2FA' => resource_path('js/Pages/Mail2FA'),
            ], 'mail2fa-inertia');

            // Publish all
            $this->publishes([
                __DIR__ . '/../config/mail2fa.php' => config_path('mail2fa.php'),
                __DIR__ . '/../resources/views/emails' => resource_path('views/vendor/mail2fa/emails'),
                __DIR__ . '/../resources/js/Pages/Mail2FA' => resource_path('js/Pages/Mail2FA'),
            ], 'mail2fa');
        }

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'mail2fa');
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        if (config('mail2fa.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }
    }

    /**
     * Register the middleware.
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('mail2fa', Mail2FAMiddleware::class);
    }
}
