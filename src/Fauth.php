<?php

declare(strict_types=1);

namespace Bright\Fauth;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Kreait\Firebase\Auth\UserQuery;
use Kreait\Firebase\Auth\UserRecord;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Kreait\Firebase\Exception\AuthException;
use Kreait\Firebase\Exception\FirebaseException;
use Stringable;
use Throwable;

class Fauth
{
    /**
     * Firebase Auth client (non-static, instantiated once per service)
     */

    /**
     * Create a new class instance.
     *
     * Ensures Firebase Auth is initialized once per service instance.
     */
    public function __construct(private readonly \Kreait\Firebase\Contract\Auth $auth) {}

    /**
     * Get a user from Firebase by UID.
     */
    public function find(string $uid): ?UserRecord
    {
        assert($uid !== '');

        return Futils::catchup(fn (): UserRecord => $this->auth->getUser($uid));
    }

    /**
     * Get the user by given email address
     */
    public function findByEmail(string $email): ?UserRecord
    {
        assert($email !== '');

        return Futils::catchup(fn (): UserRecord => $this->auth->getUserByEmail($email));
    }

    /**
     * Get the user by given phone number
     */
    public function findByPhone(string $phone): ?UserRecord
    {
        assert($phone !== '');

        return Futils::catchup(fn (): UserRecord => $this->auth->getUserByPhoneNumber($phone));
    }

    /**
     * Check whether the user is authenticated
     */
    public function check(string $email, string $password = ''): bool
    {
        try {
            assert($email !== '' && $password !== '');

            $auth = $this->auth->signInWithEmailAndPassword($email, $password);

            return gettype($auth->firebaseUserId()) === 'string';
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Attempt to log in to Firebase using email and password.
     *
     * @param  array<string, mixed>  $credentials  The login credentials
     */
    public function attempt(array $credentials): ?UserRecord
    {
        if (isset($credentials['email'], $credentials['password'])) {

            /** @phpstan-ignore-next-line */
            $this->auth->signInWithEmailAndPassword($credentials['email'], $credentials['password']);

            return $this->findByEmail((string) $credentials['email']);
        }

        return null;
    }

    /**
     * Disable a Firebase user
     */
    public function disabled(string $uid): ?UserRecord
    {
        assert($uid !== '');

        return Futils::catchup(fn (): UserRecord => $this->auth->disableUser($uid));
    }

    /**
     * Enable a Firebase user
     */
    public function enabled(string $uid): ?UserRecord
    {
        assert($uid !== '');

        return Futils::catchup(fn (): UserRecord => $this->auth->enableUser($uid));
    }

    /**
     * Create a new Firebase user.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input = []): UserRecord
    {
        /** @var array<non-empty-string, mixed> $input */
        $user = $this->auth->createUser($input);

        if (! empty($input['options']) && is_array($input['options'])) {
            /** @phpstan-ignore-next-line */
            $this->auth->setCustomUserClaims($user->uid, $input['options']);
        }

        return $user;
    }

    /**
     * Update the Firebase user by given UID.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(string $uid, array $input = []): ?UserRecord
    {
        assert($uid !== '');

        /** @var non-empty-array<non-empty-string, mixed> $input */
        $user = $this->auth->updateUser($uid, $input);

        if (! empty($input['options']) && is_array($input['options'])) {
            /** @phpstan-ignore-next-line */
            $this->auth->setCustomUserClaims($user->uid, $input['options']);
        }

        return $user;
    }

    /**
     * Update or create the Firebase user by given UID.
     *
     * @param  array<string, mixed>  $input
     */
    public function upsert(?string $uid, array $input = []): UserRecord
    {
        if ($uid && $user = $this->update($uid, $input)) {
            return $user;
        }

        return $this->create($input);
    }

    /**
     * Delete the firebase user(s).
     *
     * @param  string|array<int, string|Stringable>  $uids
     */
    public function delete(string|array $uids = ''): bool
    {
        if (is_string($uids) && $uids !== '') {
            return (bool) Futils::catchup(function () use ($uids): true {
                $this->auth->deleteUser($uids);

                return true;
            });
        }

        if (is_array($uids)) {
            /** @var array<int, non-empty-string|Stringable> $uids */
            return Futils::catchup(fn (): bool => (bool) $this->auth->deleteUsers($uids)->successCount(), false);
        }

        return false;
    }

    /**
     * Delete all Firebase users
     */
    public function deleteAllUsers(): int
    {
        $totalDeleted = 0;
        $uids = array_keys(iterator_to_array($this->auth->queryUsers([])));

        while (count($uids) > 0) {
            $this->auth->deleteUsers($uids, true);
            $totalDeleted += count($uids);
            $uids = array_keys(iterator_to_array($this->auth->queryUsers([])));
        }

        return $totalDeleted;
    }

    /**
     * Update Firebase user password using email.
     */
    public function updatePassword(string $email, string $password): UserRecord
    {
        try {
            assert($email !== '' && $password !== '');

            $user = $this->auth->getUserByEmail($email);

            return $this->auth->changeUserPassword($user->uid, $password);
        } catch (UserNotFound) {
            throw ValidationException::withMessages(['email' => "User with email {$email} was not found."]);
        } catch (AuthException|FirebaseException $e) {
            throw new Exception("Failed to update password: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    /**
     * Get multiple users by UID list.
     *
     * @param  array<int, string|Stringable>  $uids
     * @return Collection<int, UserRecord|null>
     */
    public function findMany(array $uids = [], bool $cache = true): Collection
    {
        if ($uids === []) {
            /** @var Collection<int, UserRecord|null> */
            return collect([]);
        }

        $callback = function () use ($uids) {
            /** @var non-empty-list<Stringable|non-empty-string> $uids */
            return collect($this->auth->getUsers($uids))->values();
        };

        if (! $cache) {
            return $callback();
        }

        $cacheKey = Futils::cacheKey(__METHOD__, $uids);

        /** @var Collection<int, UserRecord|null> */
        return Cache::remember($cacheKey, Futils::cacheLife(), $callback);
    }

    /**
     * Get the firebase users
     *
     * @return Collection<int, UserRecord>
     */
    public function all(): Collection
    {
        /** @var Collection<int, UserRecord> */
        return collect($this->auth->queryUsers([]))->values();
    }

    /**
     * Query Firebase users with optional caching.
     *
     * @param  UserQuery|array<string, mixed>  $query
     * @return Collection<int, UserRecord>
     */
    public function query(UserQuery|array $query = [], bool $cache = true): Collection
    {
        $callback = function () use ($query): Collection {
            /** @var iterable<UserRecord> $result */
            $result = $this->auth->queryUsers(/** @var @phpstan-ignore-line */ $query);

            /** @var Collection<int, UserRecord> */
            return collect(iterator_to_array($result, false));
        };

        if (! $cache) {
            return $callback();
        }

        $cacheKey = Futils::cacheKey(__METHOD__, $query);

        /** @var Collection<int, UserRecord> */
        return Cache::remember($cacheKey, Futils::cacheLife(), $callback);
    }

    /**
     * Count total Firebase users
     */
    public function count(): int
    {
        return iterator_count($this->auth->queryUsers([]));
    }

    /**
     * Search Firebase users by keyword.
     *
     * @param  mixed  $search
     * @param  int  $offset
     * @param  int  $limit
     * @return Collection<int, UserRecord>
     */
    public function search($search = null, $offset = 0, $limit = 10, bool $cache = true): Collection
    {
        $callback = function () use ($search, $offset, $limit) {
            /** @var array{ offset: int<0, max>, limit: int<1, max> } $query */
            $query = ['offset' => max(0, $offset), 'limit' => max(1, $limit)];

            $users = $this->auth->queryUsers($query);

            $term = mb_strtolower((string) $search);

            return collect($users)->filter(fn (UserRecord $user): bool => match (true) {
                $term === '' => true,
                str_contains(mb_strtolower($user->displayName ?? ''), $term) => true,
                str_contains(mb_strtolower($user->email ?? ''), $term) => true,
                str_contains($user->phoneNumber ?? '', $term) => true,
                default => false,
            })->values();
        };

        if (! $cache) {
            return $callback();
        }

        $cacheKey = Futils::cacheKey(__METHOD__, [$search, $limit, $offset]);

        /** @var Collection<int, UserRecord> */
        return Cache::remember($cacheKey, Futils::cacheLife(), $callback);
    }

    /**
     * Get human reaable message from error code
     */
    public function message(string $code, ?string $default = null): string
    {
        return Futils::message($code, $default);
    }

    /**
     * Send a password reset link to a user.
     */
    public function sendResetLink(string $email): string
    {
        /** @phpstan-ignore-next-line */
        $this->auth->sendPasswordResetLink($email, ['url' => route('login')]);

        return Password::RESET_LINK_SENT;
    }

    /**
     * Send a verification email to a user.
     *
     * @param  array<string, mixed>  $action
     */
    public function sendVerificationEmail(Model $user, array $action = []): void
    {
        /** @phpstan-ignore-next-line */
        $url = URL::temporarySignedRoute('auth.firebase.verify', now()->addHour(), ['uid' => $user->uid]);

        $action = array_merge($action, ['url' => $url]);

        /** @phpstan-ignore-next-line */
        $this->auth->sendEmailVerificationLink((string) $user->email, $action);
    }
}
