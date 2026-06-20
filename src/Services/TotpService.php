<?php

declare(strict_types=1);

namespace AndyDefer\LaravelTotp\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelTotp\Exceptions\TotpException;
use AndyDefer\LaravelTotp\Models\TotpSecret;
use Illuminate\Database\Eloquent\Model;

final class TotpService
{
    public function __construct(
        private readonly TotpGenerator $generator,
        private readonly QrCodeGenerator $qrCodeGenerator,
    ) {}

    /**
     * Configurer TOTP pour un modèle
     * Le modèle n'a besoin d'aucune relation !
     */
    public function setup(Model $authenticatable): array
    {
        $secret = $this->findOrCreateSecret($authenticatable);

        if ($secret->secret === null) {
            $secret->secret = $this->generator->generateSecret();
            $secret->save();
        }

        $recoveryCodes = $this->generator->generateRecoveryCodes();

        $account = $authenticatable->email ?? $authenticatable->getKey();

        return [
            'secret' => $secret->secret,
            'qr_code' => $this->qrCodeGenerator->generate(
                account: $account,
                secret: $secret->secret,
                issuer: config('app.name', 'Laravel'),
            ),
            'qr_code_uri' => $this->qrCodeGenerator->buildUri(
                account: $account,
                secret: $secret->secret,
                issuer: config('app.name', 'Laravel'),
            ),
            'recovery_codes' => $recoveryCodes,
        ];
    }

    /**
     * Activer TOTP après vérification du code
     */
    public function verifyAndEnable(
        Model $authenticatable,
        string $code,
        int $window = 1,
    ): bool {
        $secret = $this->findOrCreateSecret($authenticatable);

        if ($secret->secret === null) {
            throw TotpException::secretNotFound();
        }

        $codes = $this->generator->generateCodesWithWindow(
            secret: $secret->secret,
            window: $window,
        );

        foreach ($codes as $generatedCode) {
            if (hash_equals($generatedCode, $code)) {
                $secret->is_enabled = true;
                $secret->verified_at = now();
                $secret->save();

                return true;
            }
        }

        return false;
    }

    /**
     * Activer TOTP avec un secret existant
     */
    public function enable(
        Model $authenticatable,
        string $secret,
        array $recoveryCodes,
    ): TotpSecret {
        $record = $this->findOrCreateSecret($authenticatable);

        $record->secret = $secret;
        $record->is_enabled = true;
        $record->recovery_codes = $this->generator->hashRecoveryCodes($recoveryCodes);
        $record->save();

        return $record;
    }

    /**
     * Désactiver TOTP
     */
    public function disable(Model $authenticatable): bool
    {
        $secret = $this->findSecret($authenticatable);

        if ($secret === null) {
            throw TotpException::secretNotFound();
        }

        $secret->is_enabled = false;
        $secret->save();

        return true;
    }

    /**
     * Vérifier un code TOTP
     */
    public function verify(
        Model $authenticatable,
        string $code,
        int $window = 1,
    ): bool {
        $secret = $this->findSecret($authenticatable);

        if ($secret === null) {
            throw TotpException::secretNotFound();
        }

        if (! $secret->is_enabled) {
            throw TotpException::totpNotEnabled();
        }

        $codes = $this->generator->generateCodesWithWindow(
            secret: $secret->secret,
            window: $window,
        );

        foreach ($codes as $generatedCode) {
            if (hash_equals($generatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifier un code de récupération
     */
    public function verifyRecoveryCode(
        Model $authenticatable,
        string $code,
    ): bool {
        $secret = $this->findSecret($authenticatable);

        if ($secret === null) {
            throw TotpException::secretNotFound();
        }

        if (! $secret->is_enabled) {
            throw TotpException::totpNotEnabled();
        }

        $hashedCodes = $secret->recovery_codes ?? [];

        if ($this->generator->verifyRecoveryCode($code, $hashedCodes)) {
            $hashed = hash('sha256', $code);
            $remainingCodes = array_filter(
                $hashedCodes,
                fn (string $stored) => ! hash_equals($stored, $hashed)
            );

            $secret->recovery_codes = array_values($remainingCodes);
            $secret->save();

            return true;
        }

        return false;
    }

    /**
     * Marquer comme vérifié
     */
    public function markAsVerified(Model $authenticatable): void
    {
        $secret = $this->findOrCreateSecret($authenticatable);
        $secret->verified_at = now();
        $secret->save();
    }

    /**
     * Vérifier si TOTP est activé
     */
    public function isEnabled(Model $authenticatable): bool
    {
        $secret = $this->findSecret($authenticatable);

        return $secret !== null && $secret->is_enabled;
    }

    /**
     * Vérifier si TOTP est vérifié
     */
    public function isVerified(Model $authenticatable): bool
    {
        $secret = $this->findSecret($authenticatable);

        return $secret !== null && $secret->verified_at !== null;
    }

    /**
     * Récupérer les codes de récupération restants
     */
    public function getRemainingRecoveryCodes(Model $authenticatable): StringTypedCollection
    {
        $secret = $this->findSecret($authenticatable);

        if ($secret === null || $secret->recovery_codes === null) {
            return new StringTypedCollection;
        }

        return StringTypedCollection::from($secret->recovery_codes);
    }

    /**
     * Régénérer les codes de récupération
     */
    public function regenerateRecoveryCodes(Model $authenticatable): StringTypedCollection
    {
        $secret = $this->findOrCreateSecret($authenticatable);

        $recoveryCodes = $this->generator->generateRecoveryCodes();
        $secret->recovery_codes = $this->generator->hashRecoveryCodes($recoveryCodes);
        $secret->save();

        return StringTypedCollection::from($recoveryCodes);
    }

    /**
     * Récupérer le secret TOTP
     */
    public function getSecret(Model $authenticatable): ?string
    {
        $secret = $this->findSecret($authenticatable);

        return $secret ? $secret->secret : null;
    }

    /**
     * Trouver ou créer un secret
     */
    private function findOrCreateSecret(Model $authenticatable): TotpSecret
    {
        $secret = $this->findSecret($authenticatable);

        if ($secret === null) {
            $secret = $this->createSecret($authenticatable);
        }

        return $secret;
    }

    /**
     * Trouver un secret
     */
    private function findSecret(Model $authenticatable): ?TotpSecret
    {
        return TotpSecret::where([
            'authenticatable_type' => $authenticatable->getMorphClass(),
            'authenticatable_id' => $authenticatable->getKey(),
        ])->first();
    }

    /**
     * Créer un secret
     */
    private function createSecret(Model $authenticatable): TotpSecret
    {
        return TotpSecret::create([
            'authenticatable_type' => $authenticatable->getMorphClass(),
            'authenticatable_id' => $authenticatable->getKey(),
            'secret' => $this->generator->generateSecret(),
            'is_enabled' => false,
        ]);
    }
}
