<?php

declare(strict_types=1);

namespace AndyDefer\LaravelTotp\Models;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelTotp\ValueObjects\TotpSecretVO;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class TotpSecret extends Model
{
    use SoftDeletes;

    protected $table = 'totp_secrets';

    protected $fillable = [
        'authenticatable_type',
        'authenticatable_id',
        'secret',
        'is_enabled',
        'recovery_codes',
        'verified_at',
        'metadata',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'recovery_codes' => 'array',
        'verified_at' => 'datetime',
        'metadata' => 'array',
    ];

    // === RELATIONS POLYMORPHIQUES UNIQUEMENT DANS LE MODELE TOTP ===

    public function authenticatable()
    {
        return $this->morphTo();
    }

    // === ACCESSORS ===

    public function getSecret(): TotpSecretVO
    {
        return new TotpSecretVO(
            secret: $this->secret,
            isEnabled: $this->is_enabled,
            recoveryCodes: $this->recovery_codes ?? [],
            verifiedAt: $this->verified_at ? new DateTimeVO($this->verified_at) : null,
        );
    }

    public function getVerifiedAt(): ?DateTimeVO
    {
        return $this->verified_at ? new DateTimeVO($this->verified_at) : null;
    }

    public function getCreatedAt(): ?DateTimeVO
    {
        return $this->created_at ? new DateTimeVO($this->created_at) : null;
    }

    public function getUpdatedAt(): ?DateTimeVO
    {
        return $this->updated_at ? new DateTimeVO($this->updated_at) : null;
    }

    public function getDeletedAt(): ?DateTimeVO
    {
        return $this->deleted_at ? new DateTimeVO($this->deleted_at) : null;
    }

    public function getMetadata(): ?StrictDataObject
    {
        $value = $this->metadata;

        if ($value === null) {
            return null;
        }

        $data = is_string($value) ? json_decode($value, true) : $value;

        return is_array($data) ? new StrictDataObject($data) : null;
    }

    // === METHODES UTILITAIRES ===

    public function isEnabled(): bool
    {
        return $this->is_enabled === true;
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
