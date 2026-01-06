# Fauth - Firebase Authentication for Laravel

<p align="center">
    <a href="https://github.com/GoBrightApps/laravel-fauth/actions"><img alt="GitHub Workflow Status" src="https://img.shields.io/github/actions/workflow/status/GoBrightApps/laravel-fauth/tests.yml?branch=main&label=tests&style=round-square"></a>
    <a href="https://packagist.org/packages/bright/laravel-fauth"><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/bright/laravel-fauth"></a>
    <a href="https://packagist.org/packages/bright/laravel-fauth"><img alt="Latest Version" src="https://img.shields.io/packagist/v/bright/laravel-fauth"></a>
    <a href="https://packagist.org/packages/bright/laravel-fauth"><img alt="License" src="https://img.shields.io/github/license/GoBrightApps/laravel-fauth"></a>
</p>

## Overview

Fauth provides a seamless Firebase Authentication integration for Laravel applications. Built on top of [kreait/laravel-firebase](https://github.com/kreait/laravel-firebase), it allows you to use Firebase as a native Laravel authentication provider with full support for user management, password handling, and comprehensive testing utilities.

### Key Features

- **Native Laravel Integration** - Works seamlessly with Laravel's authentication system
- **Eloquent Model Sync** - Automatically synchronizes your User models with Firebase
- **Full User Management** - Create, read, update, and delete Firebase users
- **Password & Email Operations** - Handle password resets and email verifications
- **Testing Support** - Built-in fakes for testing without Firebase API calls
- **Zero Configuration** - Auto-discovery and sensible defaults

## Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x
- [kreait/laravel-firebase](https://github.com/kreait/laravel-firebase) installed and configured

## Installation

### Step 1: Install Dependencies

First, ensure you have `kreait/laravel-firebase` installed and configured. If not, install it first:

```bash
composer require kreait/laravel-firebase
```

Then install Fauth:

```bash
composer require bright/laravel-fauth
```

The package will be auto-discovered by Laravel.

### Step 2: Configure Your User Model

Add the required traits to your User model:

```php
<?php

namespace App\Models;

use Bright\Fauth\Traits\HasFauth;
use Bright\Fauth\Traits\HasFauthAttributes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFauth, HasFauthAttributes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uid',          // Required: Firebase UID (stored in database)
        'name',         // Fauth attribute (synced with Firebase)
        'email',        // Fauth attribute
        'phone',        // Fauth attribute
        'avatar',       // Fauth attribute
        'password',     // Fauth attribute
        'disabled',     // Fauth attribute
        'options',      // Fauth attribute (custom claims)
    ];

    /**
     * Map local attributes to Firebase fields.
     *
     * @var array<string, string>
     */
    protected array $fauth_mapping = [
        'name'          => 'displayName',
        'email'         => 'email',
        'phone'         => 'phoneNumber',
        'avatar'        => 'photoURL',
        'password'      => 'password',
        'disabled'      => 'disabled',
        'options'       => 'customClaims',
        'emailVerified' => 'emailVerified',
    ];

    /**
     * Get the Firebase UID column name.
     *
     * @return string
     */
    public function getFauthKeyName(): string
    {
        return 'uid';
    }
}
```

### Step 3: Configure Authentication Provider

Update your `config/auth.php` to use the Fauth driver:

```php
'providers' => [
    'users' => [
        'driver' => 'fauth',
        'model' => App\Models\User::class,
    ],
],
```

## Usage

### Finding Users

Retrieve Firebase users by UID, email, or phone number:

```php
use Bright\Fauth\Facades\Fauth;

// Find by UID
$user = Fauth::find('firebase_uid_123');

// Find by email
$user = Fauth::findByEmail('user@example.com');

// Find by phone
$user = Fauth::findByPhone('+15551234567');
```

All methods return a `Kreait\Firebase\Auth\UserRecord` instance or `null` if not found.

### Authentication

Verify user credentials or perform full authentication:

```php
// Check credentials (returns boolean)
$isValid = Fauth::check('user@example.com', 'password123');

// Authenticate and retrieve user
$user = Fauth::attempt([
    'email'    => 'user@example.com',
    'password' => 'password123',
]);

if ($user) {
    // Authentication successful
    // $user is a UserRecord instance
}
```

### Creating Users

Create new Firebase users with optional custom claims:

```php
$user = Fauth::create([
    'email'    => 'newuser@example.com',
    'password' => 'securePassword123',
    'name'     => 'John Doe',
    'options'  => ['role' => 'admin', 'department' => 'IT'],
]);
```

### Updating Users

Update existing user information:

```php
$updatedUser = Fauth::update('firebase_uid_123', [
    'name'   => 'Jane Smith',
    'email'  => 'jane.smith@example.com',
    'avatar' => 'https://example.com/avatar.jpg',
]);
```

### Upsert Operations

Create a user if they don't exist, or update if they do:

```php
$user = Fauth::upsert('firebase_uid_123', [
    'email' => 'user@example.com',
    'name'  => 'Updated Name',
]);
```

### Enabling and Disabling Users

Control user account status:

```php
// Disable user account
Fauth::disabled('firebase_uid_123');

// Enable user account
Fauth::enabled('firebase_uid_123');
```

### Deleting Users

Remove users from Firebase:

```php
// Delete single user
Fauth::delete('firebase_uid_123');

// Delete multiple users
Fauth::delete(['uid_1', 'uid_2', 'uid_3']);

// Delete all users (use with caution!)
$totalDeleted = Fauth::deleteAllUsers();
```

### Querying Users

Retrieve and search through Firebase users:

```php
// Get all users
$users = Fauth::all();

// Count total users
$count = Fauth::count();

// Custom query with options
$users = Fauth::query(['limit' => 50]);

// Search by name or email
$results = Fauth::search('john');
```

All methods return Laravel `Collection` instances containing `UserRecord` objects.

### Password Management

Handle password operations securely:

```php
// Update user password
Fauth::updatePassword('user@example.com', 'newSecurePassword123');

// Send password reset email
Fauth::sendResetLink('user@example.com');

// Send email verification
Fauth::sendVerificationEmail($user);
```

### Using Fauth Without Facade

If you prefer dependency injection or need more control:

```php
use Bright\Fauth\Fauth;
use Kreait\Laravel\Firebase\Facades\Firebase;

$fauth = new Fauth(Firebase::auth());

// Use all available methods
$user = $fauth->find('uid_123');
$isValid = $fauth->check('user@example.com', 'password');
```

## Error Handling

Fauth provides helpful error messages for Firebase operations:

```php
use Bright\Fauth\Futils;

try {
    $user = Fauth::create(['email' => 'invalid-email']);
} catch (\Exception $e) {
    // Get human-readable error message
    $message = Futils::message($e->getMessage());
    
    // Example output: "The user account has been disabled."
    $disabledMessage = Futils::message('USER_DISABLED');
}
```

Common error codes include:
- `USER_DISABLED` - The user account has been disabled
- `EMAIL_EXISTS` - The email address is already in use
- `INVALID_PASSWORD` - The password is invalid
- `USER_NOT_FOUND` - No user found with the provided identifier

## Testing

Fauth includes comprehensive testing utilities that allow you to test authentication flows without making actual Firebase API calls.

### Using FauthFake

The simplest way to test is using the built-in fake:

```php
use Bright\Fauth\Facades\Fauth;

test('user creation works', function () {
    Fauth::fake();

    $user = Fauth::create([
        'uid'   => 'test_uid_1',
        'email' => 'test@example.com',
    ]);

    expect($user)->not->toBeNull();
    
    Fauth::assertCalled('create');
});
```

### Overriding the Binding

For more control, override the service container binding:

```php
use Bright\Fauth\FauthFake;

beforeEach(function () {
    $this->app->singleton('fauth', fn () => new FauthFake());
});

test('finds created user', function () {
    $fauth = app('fauth');

    $fauth->create(['uid' => 'u1', 'email' => 'user@example.com']);
    $found = $fauth->find('u1');

    expect($found->email)->toBe('user@example.com');
});
```

### Testing Multiple Operations

Test complex authentication workflows:

```php
use Bright\Fauth\FauthFake;
use Kreait\Firebase\Auth\UserRecord;

test('full user lifecycle', function () {
    $email = 'user@example.com';
    $uid = 'user_123';
    $userData = ['email' => $email];
    
    $user = FauthFake::userRecord($uid, $userData);

    Fauth::shouldReceive('create')
        ->once()
        ->with($userData)
        ->andReturn($user);

    Fauth::shouldReceive('update')
        ->once()
        ->with($uid, $userData)
        ->andReturn($user);

    Fauth::shouldReceive('delete')
        ->once()
        ->with([$uid])
        ->andReturnTrue();

    // Test creation
    $created = Fauth::create($userData);
    expect($created)->toBeInstanceOf(UserRecord::class);

    // Test update
    $updated = Fauth::update($uid, $userData);
    expect($updated)->toBeInstanceOf(UserRecord::class);

    // Test deletion
    $deleted = Fauth::delete([$uid]);
    expect($deleted)->toBeTrue();
});
```

### Asserting Method Calls

Verify that specific methods were called during tests:

```php
test('sends verification email', function () {
    Fauth::fake();
    
    $user = Fauth::create(['email' => 'test@example.com']);
    Fauth::sendVerificationEmail($user);

    Fauth::assertCalled('sendVerificationEmail');
});
```

## Security Considerations

### Credential Management

- Store your Firebase Admin SDK credentials securely using the `FIREBASE_CREDENTIALS` environment variable
- Never commit credentials to version control
- Use Laravel's encrypted environment files for sensitive data in production

### Production Best Practices

- Implement rate limiting on authentication endpoints
- Enable Firebase security rules for additional protection
- Cache user queries where appropriate to reduce API calls
- Monitor Firebase usage to stay within quota limits
- Regularly audit user permissions and custom claims

### Validation

Always validate user input before passing to Firebase:

```php
$validated = $request->validate([
    'email'    => 'required|email',
    'password' => 'required|min:8',
    'name'     => 'required|string|max:255',
]);

$user = Fauth::create($validated);
```

## Advanced Configuration

### Custom Attribute Mapping

You can customize how local attributes map to Firebase fields:

```php
protected array $fauth_mapping = [
    'full_name'     => 'displayName',
    'email_address' => 'email',
    'mobile'        => 'phoneNumber',
    'profile_pic'   => 'photoURL',
    'user_options'  => 'customClaims',
];
```

### Custom UID Column

If you use a different column name for storing Firebase UIDs:

```php
public function getFauthKeyName(): string
{
    return 'firebase_id'; // Instead of 'uid'
}
```

## Troubleshooting

### Common Issues

**Issue**: `Class 'Fauth' not found`
- Ensure the package is installed: `composer require bright/laravel-fauth`
- Clear Laravel's config cache: `php artisan config:clear`

**Issue**: Authentication always fails
- Verify your Firebase Admin SDK credentials are correct
- Check that the `kreait/laravel-firebase` package is properly configured
- Ensure your Firebase project has Email/Password authentication enabled

**Issue**: Users not syncing with Firebase
- Verify the `HasFauth` and `HasFauthAttributes` traits are added to your model
- Check that the `uid` column exists in your database
- Ensure the `fauth_mapping` array includes all required fields

## Contributing

We welcome contributions! To contribute:

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Make your changes with clear, descriptive commits
4. Write or update tests as needed
5. Ensure all tests pass: `composer test`
6. Submit a pull request with a detailed description

### Development Setup

```bash
# Clone the repository
git clone https://github.com/GoBrightApps/laravel-fauth.git

# Install dependencies
composer install

# Run tests
composer test
composer lint # linting
composer test:types # strict types (PHPStan)
```

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).

## Credits

Developed and maintained by [Bright](https://bright.it)

Built on top of the excellent [kreait/laravel-firebase](https://github.com/kreait/laravel-firebase) package.

## Support

- **Documentation**: [GitHub Wiki](https://github.com/GoBrightApps/laravel-fauth/wiki)
- **Issues**: [GitHub Issues](https://github.com/GoBrightApps/laravel-fauth/issues)
- **Discussions**: [GitHub Discussions](https://github.com/GoBrightApps/laravel-fauth/discussions)

---

<p align="center">Made with ❤️ by <a href="https://bright.it">Bright</a></p>