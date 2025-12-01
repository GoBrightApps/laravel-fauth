<?php

declare(strict_types=1);

namespace Bright\Fauth\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasAttributeAccessor
{
    /**
     * Get the user's full name.
     *
     * @return Attribute<string|null, never>
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->castToStringNullable($this->getFauthValue('name'))
        );
    }

    /**
     * Get the user's email.
     *
     * @return Attribute<string|null, never>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->castToStringNullable($this->getFauthValue('email'))
        );
    }

    /**
     * Get the user's avatar.
     *
     * @return Attribute<string|null, never>
     */
    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->castToStringNullable($this->getFauthValue('avatar'))
        );
    }

    /**
     * Get whether the user is disabled.
     *
     * @return Attribute<bool, never>
     */
    protected function disabled(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => (bool) $this->getFauthValue('disabled')
        );
    }

    /**
     * Get the user's options.
     *
     * @return Attribute<array, never>
     */
    protected function options(): Attribute
    {
        return Attribute::make(
            get: fn (): array => (array) $this->getFauthValue('options', [])
        );
    }

    /**
     * Get whether the user's email is verified.
     *
     * @return Attribute<bool|null, never>
     */
    protected function emailVerified(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => (bool) $this->getFauthValue('emailVerified', false)
        );
    }

    /**
     * Get the fauth value from fauth_data or fallback model attributes.
     *
     * @param  mixed|null  $default
     * @return mixed
     */
    private function getFauthValue(string $key, $default = null)
    {
        $flipKey = $this->fauth_mapping[$key] ?? '';

        return $this->fauth_data->{$flipKey} ?? data_get($this->attributes, $key, $default);
    }

    /**
     * Safely cast a value to string|null for static analysis.
     */
    private function castToStringNullable(mixed $value): ?string
    {
        return $value !== null ? (string) $value : null;
    }
}
