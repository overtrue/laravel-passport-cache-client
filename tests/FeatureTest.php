<?php

namespace Tests;

use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Overtrue\LaravelPassportCacheClient\CacheClientRepository;

class FeatureTest extends TestCase
{
    public function test_user_can_access_with_clients()
    {
        $this->withoutExceptionHandling();

        $password = 'Pa55w0rd!';
        $user = new User();
        $user->email = 'foo@gmail.com';
        $user->password = $this->app->make(Hasher::class)->make($password);
        $user->save();

        /** @var Client $client */
        $client = Client::create([
            'user_id' => null,
            'name' => 'Foo',
            'secret' => Str::random(40),
            'redirect' => 'http://localhost/',
            'personal_access_client' => false,
            'password_client' => true,
            'revoked' => false,
        ]);

        $response = $this->post(
            '/oauth/token',
            [
                'grant_type' => 'client_credentials',
                'client_id' => $client->id,
                'client_secret' => $client->secret,
            ]
        );

        $this->assertInstanceOf(CacheClientRepository::class, app(ClientRepository::class));
        $response->assertOk();
    }

    public function test_it_can_cache_client()
    {
        $password = 'foobar123';
        $user = new User();
        $user->email = 'foo@gmail.com';
        $user->password = $this->app->make(Hasher::class)->make($password);
        $user->save();

        /** @var Client $client */
        $client = app(ClientRepository::class)->createPersonalAccessClient($user->id, 'Personal Token Client', 'http://localhost');

        $query = $this->getQueryLog(function () use ($client) {
            app(ClientRepository::class)->find($client->id);
            app(ClientRepository::class)->find($client->id);
            app(ClientRepository::class)->find($client->id);
            app(ClientRepository::class)->find($client->id);
            app(ClientRepository::class)->find($client->id);
        });

        $this->assertSame('select * from "oauth_clients" where "id" = ? limit 1', $query[0]['sql']);
        $this->assertSame($client->getKey(), $query[0]['bindings'][0]);
        $this->assertCount(1, $query);
    }

    protected function getQueryLog(\Closure $callback): \Illuminate\Support\Collection
    {
        $sqls = \collect([]);
        \DB::listen(function ($query) use ($sqls) {
            $sqls->push(['sql' => $query->sql, 'bindings' => $query->bindings]);
        });

        $callback();

        return $sqls;
    }
}
