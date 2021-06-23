<?php

namespace Overtrue\LaravelPassportCacheClient;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\ClientRepository;

class CacheClientServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(
            ClientRepository::class,
            function ($container) {
                $config = $container->make('config')->get('passport.personal_access_client');

                return new CacheClientRepository(
                    $config['id'] ?? null,
                    $config['secret'] ?? null,
                    \config('passport.cache.prefix'),
                    \config('passport.cache.expires_in'),
                    \config('passport.cache.tags', []),
                    \config('passport.cache.store', \config('cache.default'))
                );
            }
        );
    }
}
