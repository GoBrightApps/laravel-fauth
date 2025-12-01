<?php

declare(strict_types=1);

namespace Bright\Fauth\Traits;

use Bright\Fauth\Facades\Fauth;
use Bright\Fauth\Futils;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Kreait\Firebase\Auth\UserRecord;

trait HasFauth
{
    use InteractsWithFauth;

    /**
     * The Fauth attribute key mappings between the local model and Firebase.
     *
     * @var array<string, string>
     */
    protected array $fauth_mapping = [
        'name' => 'displayName',
        'email' => 'email',
        'phone' => 'phoneNumber',
        'avatar' => 'photoURL',
        'options' => 'customClaims',
        'disabled' => 'disabled',
        'password' => 'password',
        'emailVerified' => 'emailVerified',
    ];

    /**
     * The cached Firebase user record.
     */
    protected ?UserRecord $fauth_data = null;

    /**
     * Indicates whether the Firebase user data has been loaded.
     */
    protected bool $fauth_loaded = false;

    /**
     * Get the fauth key name.
     */
    public function getFauthKeyName(): string
    {
        return 'uid';
    }

    /**
     * Map the local attributes into their Firebase equivalents.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function fauthAttributes(array $attributes = []): array
    {
        /** @var array<string, mixed> */
        return Futils::mapKeys(Arr::only($attributes, array_keys($this->fauth_mapping)), $this->fauth_mapping);
    }

    /**
     * Retrieve and cache the Firebase user data if not already loaded.
     *
     * @return $this
     */
    public function loadFauthData(): self
    {
        $uid = $this->getAttribute($this->getFauthKeyName());

        if (! is_string($uid) || $this->fauth_data !== null) {
            return $this;
        }

        $cacheKey = Futils::userCacheKey($uid);

        /** @phpstan-ignore-next-line */
        $this->fauth_data = Cache::rememberForever($cacheKey, fn () => Fauth::find($uid));

        return $this;
    }

    /**
     * Load Firebase data and merge it into model attributes.
     */
    public function loadFauth(): void
    {
        $this->loadFauthData();

        // Optionally sync Firebase data into Eloquent attributes:
        // foreach ($this->fauth_mapping as $local => $remote) {
        //     $this->setAttribute($local, data_get($this->fauth_data, $remote));
        // }
    }

    /**
     * Bootstrap the HasFauth trait.
     *
     * Automatically syncs Firebase user data on retrieval, update, and delete.
     */
    protected static function bootHasFauth(): void
    {
        static::retrieved(static function (self $model): void {
            $model->loadFauth();
        });

        static::saving(static function (self $model): void {

            /** @var array<string, mixed> $attributes */
            $attributes = $model->getDirty();

            $fauthKey = $model->getAttribute($model->getFauthKeyName());
            $fauthKey = is_string($fauthKey) ? $fauthKey : null;

            $fauth = Fauth::upsert($fauthKey, $model->fauthAttributes($attributes));
            $model->fauth_data = $fauth;

            if ($fauthKey !== $fauth->uid) {
                $attributes[$model->getFauthKeyName()] = $fauth->uid;
            }

            // Exclude Firebase-managed attributes from local persistence.
            $model->setRawAttributes(Arr::except($attributes, array_keys($model->fauth_mapping)));

            // Clear the Firebase cache entry.
            Cache::forget(Futils::userCacheKey($fauthKey));
        });

        static::deleting(static function (self $model): void {
            if ($key = $model->getAttribute($model->getFauthKeyName())) {
                Fauth::delete((string) $key);
                Cache::forget(Futils::userCacheKey((string) $key));
            }
        });
    }
}
