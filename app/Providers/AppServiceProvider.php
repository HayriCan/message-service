<?php

namespace App\Providers;

use App\Gateways\Contracts\WebhookGatewayInterface;
use App\Gateways\WebhookGateway;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Repositories\MessageRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            MessageRepositoryInterface::class,
            MessageRepository::class
        );

        $this->app->bind(WebhookGatewayInterface::class, function ($app) {
            return new WebhookGateway(
                url: config('services.webhook.url'),
                authKey: config('services.webhook.auth_key'),
                timeout: config('services.webhook.timeout', 30)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
