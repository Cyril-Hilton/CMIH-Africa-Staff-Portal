<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        \App\Support\PortalLinks::configureConsoleRoot();

        // Organisation policy: portal notifications stay inside the application.
        config(['performance.email_enabled'=>false,'creative.email_enabled'=>false,'mail.default'=>'array']);
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSending::class, fn () => false);
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Notifications\Events\NotificationSending::class, fn ($event) => $event->channel === 'mail' ? false : null);


        \Illuminate\Validation\Rules\Password::defaults(function () {
            return \Illuminate\Validation\Rules\Password::min(8)
                ->letters()
                ->numbers();
        });

        \Illuminate\Support\Facades\View::composer(['layouts.app', 'layouts.guest', 'components.application-logo'], function ($view) {
            try {
                $theme = \App\Models\SiteContent::getValue('site_theme', 'BOLDER and BETTER');
            } catch (\Throwable $e) {
                $theme = 'BOLDER and BETTER';
            }
            $view->with('site_theme', (string) $theme);
        });
    }
}
