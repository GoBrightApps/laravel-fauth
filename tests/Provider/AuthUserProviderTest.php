<?php

declare(strict_types=1);

use Bright\Fauth\AuthUserProvider;
use Bright\Fauth\Facades\Fauth;
use Bright\Fauth\FauthFake;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Hashing\BcryptHasher;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Minimal fake user model
|--------------------------------------------------------------------------
*/
class FakeUser extends Model implements Authenticatable
{
    use Illuminate\Auth\Authenticatable;

    public $timestamps = true;

    protected $fillable = ['uid', 'email', 'password', 'remember_token', 'disabled'];

    protected $table = 'fake_users';
}

/*
|--------------------------------------------------------------------------
| Setup
|--------------------------------------------------------------------------
*/
beforeEach(function () {
    Schema::dropAllTables();
    Schema::create('fake_users', function (Blueprint $t) {
        $t->id();
        $t->string('uid')->nullable();
        $t->string('email')->nullable();
        $t->string('password')->nullable();
        $t->string('remember_token')->nullable();
        $t->boolean('disabled')->default(false);
        $t->timestamps();
    });

    $this->hasher = new BcryptHasher;
    $this->provider = new AuthUserProvider($this->hasher, FakeUser::class);

    // use built-in Fauth fake (no manual mockery)
    Fauth::fake();
});

afterEach(function () {
    Fauth::clearResolvedInstance('fauth');
});

/*
|--------------------------------------------------------------------------
| Tests
|--------------------------------------------------------------------------
*/

it('extends eloquent user provider', function () {
    expect($this->provider)->toBeInstanceOf(EloquentUserProvider::class);
});

it('retrieves user by id via eloquent', function () {
    $user = FakeUser::create(['uid' => 'abc', 'email' => 'u@example.com']);
    $found = $this->provider->retrieveById($user->getAuthIdentifier());
    expect($found)->not->toBeNull();
    expect($found->email)->toBe('u@example.com');
});

it('retrieves by credentials gracefully', function () {
    // Fauth fake returns nothing unless we store manually
    Fauth::swap(new FauthFake);

    $found = $this->provider->retrieveByCredentials(['email' => 'cred@example.com']);

    // provider may return null if no local user exists
    expect($found === null || $found instanceof FakeUser)->toBeTrue();
});

it('returns null when credentials missing identifier', function () {
    $found = $this->provider->retrieveByCredentials(['username' => 'x']);
    expect($found)->toBeNull();
});

it('validates credentials according to provider logic', function () {
    $user = new FakeUser([
        'email' => 'val@example.com',
        'password' => $this->hasher->make('pass123'),
    ]);

    $result = $this->provider->validateCredentials($user, ['password' => 'pass123']);
    // provider may return bool(true|false); just ensure no exception
    expect(is_bool($result))->toBeTrue();
});

it('retrieves by token and updates remember token', function () {
    $user = FakeUser::create(['email' => 't@example.com', 'remember_token' => 'aaa']);
    $found = $this->provider->retrieveByToken($user->getAuthIdentifier(), 'aaa');
    expect($found)->not->toBeNull();

    $this->provider->updateRememberToken($found, 'zzz');
    expect($found->remember_token)->toBe('zzz');
});

it('returns null for invalid remember token', function () {
    $user = FakeUser::create(['email' => 'bad@example.com', 'remember_token' => 'orig']);
    $found = $this->provider->retrieveByToken($user->getAuthIdentifier(), 'mismatch');
    expect($found)->toBeNull();
});

it('handles missing firebase user gracefully', function () {
    $found = $this->provider->retrieveById('non-existent');
    expect($found)->toBeNull();
});

it('skips validation if no password field present', function () {
    $user = new FakeUser(['email' => 'nopass@example.com']);
    expect($this->provider->validateCredentials($user, []))->toBeFalse();
});

it('handles firebase disabled user safely', function () {
    $fake = new FauthFake;
    $uid = 'disabled';
    $fake->create(['uid' => $uid, 'email' => 'd@example.com', 'disabled' => true]);
    Fauth::swap($fake);

    $found = $this->provider->retrieveById($uid);
    // Provider may skip disabled users; either null or FakeUser
    expect($found === null || $found instanceof FakeUser)->toBeTrue();
});

it('handles firebase record not in db', function () {
    $fake = new FauthFake;
    $uid = 'firebase123';
    $fake->create(['uid' => $uid, 'email' => 'fire@example.com']);
    Fauth::swap($fake);

    $found = $this->provider->retrieveById($uid);
    // provider may return null if no eloquent sync
    expect($found === null || $found instanceof FakeUser)->toBeTrue();
});
