<?php

declare(strict_types=1);

use Bright\Fauth\AuthUserProvider;
use Bright\Fauth\FauthFake;
use Bright\Fauth\ServiceProvider;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    // Prevent Firebase::auth() call
    $this->app->singleton('fauth', fn () => new FauthFake);

    $provider = new ServiceProvider($this->app);
    $provider->boot();
});

it('boot() registers the fauth user provider driver', function () {
    // Ensure config defines 'fauth' before provider boot
    config([
        'auth.providers.fauth' => [
            'driver' => 'fauth',
            'model' => Illuminate\Foundation\Auth\User::class,
        ],
    ]);

    // Re-run provider boot to register the driver into Auth
    $provider = new ServiceProvider($this->app);
    $provider->boot();

    $driver = Auth::createUserProvider('fauth');

    expect($driver)->toBeInstanceOf(AuthUserProvider::class);
});

it('auth provider resolves with model from config', function () {
    config([
        'auth.providers.fauth' => [
            'driver' => 'fauth',
            'model' => Illuminate\Foundation\Auth\User::class,
        ],
    ]);

    $provider = new ServiceProvider($this->app);
    $provider->boot();

    $resolved = Auth::createUserProvider('fauth');

    expect($resolved)->toBeInstanceOf(AuthUserProvider::class);
});

it('fauth binding exists, is singleton, and uses the fake', function () {
    expect(app()->bound('fauth'))->toBeTrue();

    $a = app('fauth');
    $b = app('fauth');

    expect($a)->toBeInstanceOf(FauthFake::class)
        ->and($a)->toBe($b); // singleton
});

it('keeps the fauth fake binding available after boot', function () {
    expect(app('fauth'))->toBeInstanceOf(FauthFake::class);
});
