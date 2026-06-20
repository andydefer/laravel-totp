<?php

declare(strict_types=1);

namespace AndyDefer\LaravelTotp\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

final class TotpSecretVO extends AbstractValueObject
{
    public function __construct(
        private readonly string $secret,
        private readonly bool $isEnabled = false,
        private readonly array $recoveryCodes = [],
        private readonly ?DateTimeVO $verifiedAt = null,
    ) {}

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function getRecoveryCodes(): array
    {
        return $this->recoveryCodes;
    }

    public function getVerifiedAt(): ?DateTimeVO
    {
        return $this->verifiedAt;
    }

    public function hasRecoveryCodes(): bool
    {
        return ! empty($this->recoveryCodes);
    }

    public function toArray(): array
    {
        return [
            'secret' => $this->secret,
            'is_enabled' => $this->isEnabled,
            'recovery_codes' => $this->recoveryCodes,
            'verified_at' => $this->verifiedAt?->toDateTimeString(),
        ];
    }

    protected function getDefaultValue(string $propertyName): mixed
    {
        return match ($propertyName) {
            'secret' => '',
            'isEnabled' => false,
            'recoveryCodes' => [],
            'verifiedAt' => null,
            default => null,
        };
    }
}