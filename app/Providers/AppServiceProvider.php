<?php

namespace App\Providers;

use App\Services\Auth\SiweMessageBuilder;
use App\Services\Storage\StorageInterface;
use App\Services\Storage\StorageManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\GitLab\GitLabExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            SiweMessageBuilder::class,
            fn () => new SiweMessageBuilder(
                parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost',
                (string) config('app.url'),
            ),
        );

        // Storage abstraction: the StorageManager registry resolves the
        // active driver from `cloudmarktplaats.storage.driver`, and the
        // bare StorageInterface binding delegates to that registry so
        // typed constructor parameters auto-resolve the right driver.
        $this->app->singleton(StorageManager::class);
        $this->app->bind(
            StorageInterface::class,
            fn ($app) => $app->make(StorageManager::class)->driver(),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register the GitLab Socialite driver via SocialiteProviders manager.
        // GitHub is supported out of the box by laravel/socialite, so no
        // additional listener is required for it.
        Event::listen(
            SocialiteWasCalled::class,
            [GitLabExtendSocialite::class, 'handle'],
        );
    }
}
