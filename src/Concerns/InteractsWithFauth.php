<?php

declare(strict_types=1);

namespace Bright\Fauth\Concerns;

use Bright\Fauth\Facades\Fauth;
use Bright\Fauth\Futils;
use Illuminate\Support\Facades\Cache;

trait InteractsWithFauth
{
    /**
     * Get the user by given uid.
     */
    public static function findByUid(?string $uid): ?self
    {
        return static::query()
            ->where((new self)->getFauthKeyName(), $uid)
            ->first();
    }

    /**
     * Get the user by given firebase uid.
     */
    public static function findByFid(?string $uid): ?self
    {
        return static::query()
            ->where((new self)->getFauthKeyName(), $uid)
            ->first();
    }

    /**
     * Get the user from firebase and database by given email.
     */
    public static function findByEmail(string $email, bool $cache = true): ?self
    {
        $callback = static function () use ($email): ?self {
            $record = Fauth::findByEmail($email);
            $uid    = $record ? $record->uid : null;

            return $uid ? static::findByUid($uid) : null;
        };

        if (! $cache) {
            /** @var static|null $user */
            $user = $callback();

            return $user;
        }

        /** @var static|null $cached */
        $cached = Cache::remember(
            Futils::userCacheKey($email),
            Futils::cacheLife(),
            $callback
        );

        return $cached;
    }

    /**
     * Get the fauth key name.
     */
    public function getFauthKeyName(): string
    {
        return 'uid';
    }
}
