<?php

namespace App\Providers;

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
        //
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
