<p align="center">
    <a href="https://github.com/GoBrightApps/firebase-laravel-auth/actions"><img alt="GitHub Workflow Status (master)" src="https://img.shields.io/github/actions/workflow/status/GoBrightApps/firebase-laravel-auth/tests.yml?branch=main&label=tests&style=round-square"></a>
    <a href="https://packagist.org/packages/bright/firebase-laravel-auth"><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/bright/firebase-laravel-auth"></a>
    <a href="https://packagist.org/packages/bright/firebase-laravel-auth"><img alt="Latest Version" src="https://img.shields.io/packagist/v/bright/firebase-laravel-auth"></a>
    <a href="https://packagist.org/packages/bright/firebase-laravel-auth"><img alt="License" src="https://img.shields.io/github/license/GoBrightApps/firebase-laravel-auth"></a>
</p>

# Firebase Laravel auth provider

The Firebase Laravel auth package provides a clean authentication layer for Firebase in Laravel.

It integrates kreait/laravel-firebase with Laravel’s authentication system — allowing you to use Firebase as a native auth provider, complete with user management, password handling, and test fakes.

With the package you can create, authenticate, update, or delete Firebase users directly through Laravel’s model (e.g User model) — without worrying about SDK boilerplate or manual API handling.

> **Note** This package built **on top of** the [kreait/laravel-firebase](https://github.com/kreait/laravel-firebase). Make sure you have it installed and configured.

## Installation

Before installation this package install and setup the `kreait/laravel-firebase` If you don’t have it yet.

```bash
composer require bright/firebase-laravel-auth
```

## Configuration (Auth Provider)

Use the `HasFauth` trait in your User model to automatically sync user data with Firebase.

```php
class User extends Model {

    use HasFauth;
}
```

This keeps your Laravel users in sync with Firebase — handling create, update, and delete actions seamlessly.

Next: Tell Laravel to use the **fauth** driver for user auth provider.

```php
// config/auth.php

'providers' => [
    'users' => [
        'driver' => 'fauth',                   // 👈 use the fauth driver
        'model'  => App\Models\User::class,    // your Eloquent User model
    ],
],
```

#### User model configuration

```php
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFauth;

    /**
     * The attributes are fillable in database + firebase.
     */
    protected $fillable = [
        'uid', // required in database | Get the key by getFauthKeyName()

        // fauth attributes (The fields doesn't touch database) mapped by: $fauth_mapping
        'name',
        'email',
        'phone',
        'avatar',
        'options',
        'password',
        'disabled',
    ];

    /**
     * The Fauth attribute key mappings between the local model and Firebase.
     *
     * Firebase fillable + mapping keys
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
     * (Optional) Get the fauth key name.
     */
    public function getFauthKeyName(): string
    {
        return 'uid';
    }
}
```

Nothing else to publish. The package’s service provider is auto-discovered
If not then use `Bright\Fauth\ServiceProvider`. in laravel providers

The ServiceProvider register `Fauth` facade and `fauth` auth driver

## Quick Usage

The `Fauth` facade provides a clean, zero-config API to interact with Firebase Auth.

### Finding a user

```php
use Bright\Fauth\Facades\Fauth;

$user = Fauth::find('uid_123');                // Find by UID
$byEmail = Fauth::findByEmail('dev@demo.com'); // Find by email
$byPhone = Fauth::findByPhone('+15551234567'); // Find by phone
```

Each of these returns a Kreait\Firebase\Auth\UserRecord instance or null if the user doesn’t exist.

### Authenticating Users

```php
$valid = Fauth::check('dev@site.com', 'password123');  // returns bool

// Full sign-in and fetch user
$user = Fauth::attempt([
    'email'    => 'dev@site.com',
    'password' => 'password123',
]);

```

If authentication succeeds, attempt() returns a UserRecord; otherwise null.

### Managing Accounts

```php
// Create a user
$user = Fauth::create([
    'email'    => 'new@site.com',
    'password' => 'secret',
    'options'  => ['role' => 'admin'],
]);

// Update existing
$updated = Fauth::update($user->uid, [
    'displayName' => 'New Display Name',
]);

// Enable/Disable user
Fauth::disabled($user->uid);
Fauth::enabled($user->uid);

// Update or create automatically
$record = Fauth::upsert($user->uid, ['email' => 'changed@site.com']);

// Delete single or multiple
Fauth::delete($user->uid);
Fauth::delete(['uid_1', 'uid_2']);

// Delete all Firebase users
$total = Fauth::deleteAllUsers();
```

#### Searching & Querying

```php
$users = Fauth::all(); // Collection of all users
$count = Fauth::count();

$query = Fauth::query(['limit' => 50]); // Custom query
$found = Fauth::search('example');      // Search by name/email
```

The methods return Laravel Collection instances of UserRecord objects.

### Passwords and Emails

```php
// Change user password
Fauth::updatePassword('dev@site.com', 'newPassword123');

// Send password reset link
Fauth::sendResetLink('dev@site.com');

// Send verification email
Fauth::sendVerificationEmail($user);
```

### Fauth without Facade

```php
use Bright\Fauth\Fauth;

/** @var Fauth $fauth */
$fauth = new Bright\Fauth\Fauth(\Kreait\Laravel\Firebase\Facades\Firebase::auth());

// Find by UID / email / phone
$u  = $fauth->find('uid_123');
$ue = $fauth->findByEmail('dev@example.com');
$up = $fauth->findByPhone('+1555123456');

// Sign-in style helpers
$ok = $fauth->check('dev@example.com', 'secret');                 // bool
$me = $fauth->attempt(['email' => 'dev@example.com', 'password' => 'secret']); // ?UserRecord

// Create / update
$new = $fauth->create(['email' => 'new@example.com', 'options' => ['role' => 'admin']]);
$upd = $fauth->update($new->uid, ['displayName' => 'New Name']);

// Upsert / delete
$u2  = $fauth->upsert($new->uid, ['email' => 'new2@example.com']);
$bye = $fauth->delete($new->uid);      // bool
```

## Error handling

-   Many operations return `null` on failure or throw a Laravel `ValidationException`.
-   Use `message($code, $default)` to map Firebase error codes to a human-readable string.

```php
\Bright\Fauth\Futils::message('error code');

\Bright\Fauth\Futils::message('USER_DISABLED'); // results: The user account has been disabled.

// Example
\Bright\Fauth\Futils::message($e->getMessage());
```

## Testing

Fake Mode for Testing

You can replace the real Firebase client with a fake version instantly:

```php
Fauth::fake(); // swaps FauthFake to avoid hitting Firebase in tests

Fauth::create(['uid' => 't1', 'email' => 'fake@local']);

Fauth::assertCalled('create');

//... the test assert here
```

This swaps in a Bright\Fauth\FauthFake instance — all calls are recorded but no real API requests are made.

### Easiest: override the binding

Use the built-in **`FauthFake`** to avoid hitting Firebase in tests.

```php
use Bright\Fauth\FauthFake;

beforeEach(function () {
    $this->app->singleton('fauth', fn () => new FauthFake());
});

it('creates and finds a user', function () {
    $fauth = app('fauth'); // FauthFake

    $created = $fauth->create(['uid' => 'u1', 'email' => 'a@ex.com']);
    $found   = $fauth->find('u1');

    expect($found?->email)->toBe('a@ex.com');
});
```

### Testing multiple operations

You can easily test multiple authentication-related features without touching Firebase:

```php
it('can create, update, and delete a user', function () {
    $input = ['email' => 'user@example.com'];
    $uid = 'user_123';
    $user = FauthFake::userRecord($uid, $input);

    Fauth::shouldReceive('create')->once()->with($input)->andReturn($user);
    Fauth::shouldReceive('update')->once()->with($uid, $input)->andReturn($user);
    Fauth::shouldReceive('delete')->once()->with([$uid])->andReturnTrue();

    expect(Fauth::create($input))->toBeInstanceOf(UserRecord::class);
    expect(Fauth::update($uid, $input))->toBeInstanceOf(UserRecord::class);
    expect(Fauth::delete([$uid]))->toBeTrue();
});

```

### Test assert recorded calls

`FauthFake` records calls so you can assert behavior:

```php
$fauth->sendVerificationEmail($user);
$fauth->assertCalled('sendVerificationEmail');
```

## Security & Production Notes

-   Keep your Firebase Admin credentials safe (`FIREBASE_CREDENTIALS`).
-   This package assumes you’ve **correctly configured** [kreait/laravel-firebase](https://github.com/kreait/laravel-firebase).
-   In production, prefer caching where available (e.g. `findMany()`, `query()`).

## Contributing

PRs and issues are welcome! Keep it Laravel-friendly:

-   Clear, small changes
-   Tests with **Pest**
-   Prefer `FauthFake` in tests (no network calls)

## License

MIT License © [Bright](https://bright.it)
