<?php

namespace Overtrue\LaravelPassportCacheClient;

use Illuminate\Database\Eloquent\Collection;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use Illuminate\Support\Facades\Cache;

class CacheClientRepository extends ClientRepository
{
    /**
     * @var string
     */
    protected $cacheKeyPrefix;

    /**
     * @var int
     */
    protected $expiresInSeconds;

    /**
     * @var array
     */
    protected $cacheTags;

    /**
     * @var string
     */
    protected $cacheStore;

    /**
     * @var string
     */
    protected $cacheTag = 'laravel-passport-cache-client';

    /**
     * CacheClientRepository constructor.
     *
     * @param null        $personalAccessClientId
     * @param null        $personalAccessClientSecret
     * @param string|null $cacheKey
     * @param int|null    $expiresInSeconds
     * @param array       $tags
     * @param string|null $store
     */
    public function __construct(
        $personalAccessClientId = null,
        $personalAccessClientSecret = null,
        string $cacheKey = null,
        int $expiresInSeconds = null,
        array $tags = [],
        ?string $store = null
    ) {
        parent::__construct($personalAccessClientId, $personalAccessClientSecret);
        $this->cacheKeyPrefix = sprintf('%s_client_', $cacheKey ?? 'passport');
        $this->expiresInSeconds = $expiresInSeconds ?? 5 * 60;
        $this->cacheTags = \array_merge($tags, [$this->cacheTag]);
        $this->cacheStore = $store ?? \config('cache.default');
    }

    /**
     * Get a client by the given ID.
     *
     * @param int $id
     *
     * @return \Laravel\Passport\Client|null
     */
    public function find($id)
    {
        return Cache::store($this->cacheStore)->tags($this->cacheTags)->remember(
            $this->itemKey($id),
            \now()->addSeconds($this->expiresInSeconds),
            function () use ($id) {
                $client = Passport::client();

                return $client->where($client->getKeyName(), $id)->first();
            }
        );
    }

    /**
     * Get a client instance for the given ID and user ID.
     *
     * @param int   $clientId
     * @param mixed $userId
     *
     * @return \Laravel\Passport\Client|null
     */
    public function findForUser($clientId, $userId)
    {
        return Cache::store($this->cacheStore)->tags($this->cacheTags)->remember(
            $this->itemKey($clientId),
            \now()->addSeconds($this->expiresInSeconds),
            function () use ($clientId, $userId) {
                $client = Passport::client();

                return Passport::token()->where($client->getKeyName(), $clientId)->where('user_id', $userId)->first();
            }
        );
    }

    /**
     * Get the token instances for the given user ID.
     *
     * @param mixed $userId
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function forUser($userId): Collection
    {
        return Cache::store($this->cacheStore)->tags($this->cacheTags)->remember(
            $this->itemKey($userId),
            \now()->addSeconds($this->expiresInSeconds),
            function () use ($userId) {
                return Passport::client()
                    ->where('user_id', $userId)
                    ->orderBy('name', 'asc')->get();
            }
        );
    }

    /**
     * Get the personal access token client for the application.
     *
     * @return \Laravel\Passport\Client
     *
     * @throws \RuntimeException
     */
    public function personalAccessClient()
    {
        if ($this->personalAccessClientId) {
            return $this->find($this->personalAccessClientId);
        }

        $client = Passport::personalAccessClient();

        if (!$client->exists()) {
            throw new \RuntimeException('Personal access client not found. Please create one.');
        }

        return Cache::store($this->cacheStore)->tags($this->cacheTags)->remember(
            $this->itemKey($this->personalAccessClientId),
            \now()->addSeconds($this->expiresInSeconds),
            function () use ($client) {
                return $client->orderBy($client->getKeyName(), 'desc')->first()->client;
            }
        );
    }

    public function update(Client $client, $name, $redirect)
    {
        $client = parent::update($client, $name, $redirect);

        $this->removeClientCache($client);

        return $client;
    }

    public function delete(Client $client)
    {
        parent::delete($client);

        $this->removeClientCache($client);
    }

    protected function removeClientCache(Client $client)
    {
        $keys = [
            $this->itemKey($client->getKeyName()),
            $this->itemKey($client->user_id),
        ];

        if ($this->personalAccessClientId) {
            $keys[] = $this->itemKey($this->personalAccessClientId);
        }

        Cache::store($this->cacheStore)->tags($this->cacheTags)->deleteMultiple($keys);
    }

    public function itemKey(string $key)
    {
        return $this->cacheKeyPrefix . $key;
    }
}
