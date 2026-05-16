<?php

namespace App\Providers;

use App\Services\Auth\SiweMessageBuilder;
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
