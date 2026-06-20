<?php

declare(strict_types=1);

namespace AndyDefer\LaravelTotp\Exceptions;

use RuntimeException;

final class TotpException extends RuntimeException
{
    public static function totpNotEnabled(): self
    {
        return new self('TOTP is not enabled for this user.');
    }

    public static function secretNotFound(): self
    {
        return new self('TOTP secret not found.');
    }

    public static function invalidCode(): self
    {
        return new self('Invalid TOTP code.');
    }

    public static function invalidRecoveryCode(): self
    {
        return new self('Invalid recovery code.');
    }

    public static function alreadyEnabled(): self
    {
        return new self('TOTP is already enabled for this user.');
    }

    public static function alreadyDisabled(): self
    {
        return new self('TOTP is already disabled for this user.');
    }

    public static function maxAttemptsExceeded(): self
    {
        return new self('Maximum TOTP attempts exceeded.');
    }

    public static function setupFailed(): self
    {
        return new self('TOTP setup failed.');
    }
}