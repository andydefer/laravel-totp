<?php

declare(strict_types=1);

namespace AndyDefer\LaravelTotp\Tests\Integration\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelTotp\Exceptions\TotpException;
use AndyDefer\LaravelTotp\Models\TotpSecret;
use AndyDefer\LaravelTotp\Services\QrCodeGenerator;
use AndyDefer\LaravelTotp\Services\TotpGenerator;
use AndyDefer\LaravelTotp\Services\TotpService;
use AndyDefer\LaravelTotp\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelTotp\Tests\IntegrationTestCase;

final class TotpServiceIntegrationTest extends IntegrationTestCase
{
    private TotpService $totpService;

    private TestUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->totpService = new TotpService(
            new TotpGenerator,
            new QrCodeGenerator,
        );

        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    // ============================================================
    // TESTS EXISTANTS (gardés)
    // ============================================================

    public function test_setup_generates_secret_and_qr_code(): void
    {
        $setup = $this->totpService->setup($this->user);

        $this->assertArrayHasKey('secret', $setup);
        $this->assertArrayHasKey('qr_code', $setup);
        $this->assertArrayHasKey('qr_code_uri', $setup);
        $this->assertArrayHasKey('recovery_codes', $setup);

        $this->assertIsString($setup['secret']);
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $setup['secret']);
        $this->assertCount(10, $setup['recovery_codes']);

        $secret = TotpSecret::where([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->getKey(),
        ])->first();

        $this->assertNotNull($secret);
        $this->assertSame($setup['secret'], $secret->secret);
        $this->assertFalse($secret->is_enabled);
    }

    public function test_verify_and_enable_totp(): void
    {
        $setup = $this->totpService->setup($this->user);

        $secret = TotpSecret::where([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->getKey(),
        ])->first();

        $code = (new TotpGenerator)->generateCode($secret->secret);

        $verified = $this->totpService->verifyAndEnable($this->user, $code);

        $this->assertTrue($verified);

        $secret->refresh();
        $this->assertTrue($secret->is_enabled);
        $this->assertNotNull($secret->verified_at);
    }

    public function test_verify_valid_code(): void
    {
        $setup = $this->totpService->setup($this->user);

        $secret = TotpSecret::where([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->getKey(),
        ])->first();

        $secret->is_enabled = true;
        $secret->save();

        $code = (new TotpGenerator)->generateCode($secret->secret);

        $valid = $this->totpService->verify($this->user, $code);

        $this->assertTrue($valid);
    }

    public function test_verify_invalid_code(): void
    {
        $setup = $this->totpService->setup($this->user);

        $secret = TotpSecret::where([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->getKey(),
        ])->first();

        $secret->is_enabled = true;
        $secret->save();

        $valid = $this->totpService->verify($this->user, '000000');

        $this->assertFalse($valid);
    }

    public function test_verify_recovery_code(): void
    {
        $setup = $this->totpService->setup($this->user);

        $secret = TotpSecret::where([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->getKey(),
        ])->first();

        $secret->is_enabled = true;
        $secret->recovery_codes = (new TotpGenerator)->hashRecoveryCodes($setup['recovery_codes']);
        $secret->save();

        $recoveryCode = $setup['recovery_codes'][0];

        $valid = $this->totpService->verifyRecoveryCode($this->user, $recoveryCode);

        $this->assertTrue($valid);

        $secret->refresh();
        $this->assertCount(9, $secret->recovery_codes);
    }

    public function test_disable_totp(): void
    {
        $setup = $this->totpService->setup($this->user);

        $secret = TotpSecret::where([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->getKey(),
        ])->first();

        $secret->is_enabled = true;
        $secret->save();

        $disabled = $this->totpService->disable($this->user);

        $this->assertTrue($disabled);

        $secret->refresh();
        $this->assertFalse($secret->is_enabled);
    }

    public function test_is_enabled(): void
    {
        $setup = $this->totpService->setup($this->user);

        $this->assertFalse($this->totpService->isEnabled($this->user));

        $secret = TotpSecret::where([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->getKey(),
        ])->first();

        $secret->is_enabled = true;
        $secret->save();

        $this->assertTrue($this->totpService->isEnabled($this->user));
    }

    public function test_regenerate_recovery_codes(): void
    {
        $setup = $this->totpService->setup($this->user);

        $secret = TotpSecret::where([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->getKey(),
        ])->first();

        $oldCodes = $secret->recovery_codes;

        $newCodes = $this->totpService->regenerateRecoveryCodes($this->user);

        $this->assertInstanceOf(StringTypedCollection::class, $newCodes);
        $this->assertCount(10, $newCodes);

        $secret->refresh();
        $this->assertNotEquals($oldCodes, $secret->recovery_codes);
    }

    public function test_get_remaining_recovery_codes(): void
    {
        $setup = $this->totpService->setup($this->user);

        $secret = TotpSecret::where([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->getKey(),
        ])->first();

        $secret->recovery_codes = (new TotpGenerator)->hashRecoveryCodes($setup['recovery_codes']);
        $secret->save();

        $remaining = $this->totpService->getRemainingRecoveryCodes($this->user);

        $this->assertInstanceOf(StringTypedCollection::class, $remaining);
        $this->assertCount(10, $remaining);
    }

    public function test_get_secret(): void
    {
        $setup = $this->totpService->setup($this->user);

        $secret = $this->totpService->getSecret($this->user);

        $this->assertSame($setup['secret'], $secret);
    }

    public function test_verify_throws_exception_when_not_enabled(): void
    {
        $this->expectException(TotpException::class);
        $this->expectExceptionMessage('TOTP is not enabled for this user.');

        $setup = $this->totpService->setup($this->user);

        $this->totpService->verify($this->user, '123456');
    }

    public function test_verify_throws_exception_when_secret_not_found(): void
    {
        $this->expectException(TotpException::class);
        $this->expectExceptionMessage('TOTP secret not found.');

        $newUser = TestUser::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $this->totpService->verify($newUser, '123456');
    }

    public function test_setup_does_not_override_existing_secret(): void
    {
        $setup1 = $this->totpService->setup($this->user);

        $setup2 = $this->totpService->setup($this->user);

        $this->assertSame($setup1['secret'], $setup2['secret']);
    }

    public function test_two_different_users_have_different_secrets(): void
    {
        $user2 = TestUser::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $setup1 = $this->totpService->setup($this->user);
        $setup2 = $this->totpService->setup($user2);

        $this->assertNotSame($setup1['secret'], $setup2['secret']);
    }

    // ============================================================
    // NOUVEAUX TESTS À AJOUTER
    // ============================================================

    public function test_enable_with_existing_secret(): void
    {
        $setup = $this->totpService->setup($this->user);

        $secret = TotpSecret::where([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->getKey(),
        ])->first();

        $this->assertFalse($secret->is_enabled);

        $recoveryCodes = ['CODE1', 'CODE2', 'CODE3'];

        $enabled = $this->totpService->enable(
            $this->user,
            $setup['secret'],
            $recoveryCodes
        );

        $this->assertInstanceOf(TotpSecret::class, $enabled);
        $this->assertTrue($enabled->is_enabled);

        $secret->refresh();
        $this->assertTrue($secret->is_enabled);
        $this->assertCount(3, $secret->recovery_codes);
    }

    public function test_mark_as_verified(): void
    {
        $setup = $this->totpService->setup($this->user);

        $secret = TotpSecret::where([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->getKey(),
        ])->first();

        $this->assertNull($secret->verified_at);

        $this->totpService->markAsVerified($this->user);

        $secret->refresh();
        $this->assertNotNull($secret->verified_at);
    }

    public function test_is_verified(): void
    {
        $setup = $this->totpService->setup($this->user);

        $this->assertFalse($this->totpService->isVerified($this->user));

        $secret = TotpSecret::where([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->getKey(),
        ])->first();

        $secret->verified_at = now();
        $secret->save();

        $this->assertTrue($this->totpService->isVerified($this->user));
    }

    public function test_verify_recovery_code_invalid(): void
    {
        $setup = $this->totpService->setup($this->user);

        $secret = TotpSecret::where([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->getKey(),
        ])->first();

        $secret->is_enabled = true;
        $secret->recovery_codes = (new TotpGenerator)->hashRecoveryCodes($setup['recovery_codes']);
        $secret->save();

        $valid = $this->totpService->verifyRecoveryCode($this->user, 'INVALIDCODE');

        $this->assertFalse($valid);

        $secret->refresh();
        $this->assertCount(10, $secret->recovery_codes);
    }

    public function test_verify_recovery_code_when_not_enabled(): void
    {
        $this->expectException(TotpException::class);
        $this->expectExceptionMessage('TOTP is not enabled for this user.');

        $setup = $this->totpService->setup($this->user);

        $this->totpService->verifyRecoveryCode($this->user, 'ANYCODE');
    }

    public function test_verify_with_window_tolerance(): void
    {
        $setup = $this->totpService->setup($this->user);

        $secret = TotpSecret::where([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->getKey(),
        ])->first();

        $secret->is_enabled = true;
        $secret->save();

        // Générer un code avec un timestamp décalé de 30s (période précédente)
        $generator = new TotpGenerator;
        $pastTimestamp = time() - 30;
        $code = $generator->generateCode($secret->secret, $pastTimestamp);

        // Vérifier avec window=1 (tolère +/- 30s)
        $valid = $this->totpService->verify($this->user, $code, window: 1);

        $this->assertTrue($valid);
    }

    public function test_verify_recovery_code_removes_used_code(): void
    {
        $setup = $this->totpService->setup($this->user);

        $secret = TotpSecret::where([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->getKey(),
        ])->first();

        $secret->is_enabled = true;
        $secret->recovery_codes = (new TotpGenerator)->hashRecoveryCodes($setup['recovery_codes']);
        $secret->save();

        $recoveryCode = $setup['recovery_codes'][0];
        $hashed = hash('sha256', $recoveryCode);

        // Vérifier que le code existe
        $this->assertContains($hashed, $secret->recovery_codes);

        $valid = $this->totpService->verifyRecoveryCode($this->user, $recoveryCode);

        $this->assertTrue($valid);

        $secret->refresh();
        $this->assertNotContains($hashed, $secret->recovery_codes);
        $this->assertCount(9, $secret->recovery_codes);
    }

    public function test_regenerate_recovery_codes_updates_database(): void
    {
        $setup = $this->totpService->setup($this->user);

        $secret = TotpSecret::where([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->getKey(),
        ])->first();

        $oldCodes = $secret->recovery_codes;

        $newCodes = $this->totpService->regenerateRecoveryCodes($this->user);

        $secret->refresh();

        $this->assertNotEquals($oldCodes, $secret->recovery_codes);
        $this->assertCount(10, $secret->recovery_codes);

        // Vérifier que les nouveaux codes sont hashés en SHA256
        foreach ($secret->recovery_codes as $hash) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
        }
    }

    public function test_get_remaining_recovery_codes_empty(): void
    {
        $setup = $this->totpService->setup($this->user);

        $secret = TotpSecret::where([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->getKey(),
        ])->first();

        $secret->recovery_codes = [];
        $secret->save();

        $remaining = $this->totpService->getRemainingRecoveryCodes($this->user);

        $this->assertInstanceOf(StringTypedCollection::class, $remaining);
        $this->assertCount(0, $remaining);
    }

    public function test_get_secret_null(): void
    {
        $newUser = TestUser::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $secret = $this->totpService->getSecret($newUser);

        $this->assertNull($secret);
    }

    public function test_disable_throws_exception_when_no_secret(): void
    {
        $this->expectException(TotpException::class);
        $this->expectExceptionMessage('TOTP secret not found.');

        $newUser = TestUser::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $this->totpService->disable($newUser);
    }

    public function test_verify_and_enable_with_invalid_code(): void
    {
        $setup = $this->totpService->setup($this->user);

        $verified = $this->totpService->verifyAndEnable($this->user, '000000');

        $this->assertFalse($verified);

        $secret = TotpSecret::where([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->getKey(),
        ])->first();

        $this->assertFalse($secret->is_enabled);
        $this->assertNull($secret->verified_at);
    }

    public function test_verify_and_enable_with_window(): void
    {
        $setup = $this->totpService->setup($this->user);

        $secret = TotpSecret::where([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->getKey(),
        ])->first();

        $generator = new TotpGenerator;
        $pastTimestamp = time() - 30;
        $code = $generator->generateCode($secret->secret, $pastTimestamp);

        $verified = $this->totpService->verifyAndEnable($this->user, $code, window: 1);

        $this->assertTrue($verified);

        $secret->refresh();
        $this->assertTrue($secret->is_enabled);
        $this->assertNotNull($secret->verified_at);
    }

    public function test_verify_recovery_code_removes_used_code_and_keeps_others(): void
    {
        $setup = $this->totpService->setup($this->user);

        $secret = TotpSecret::where([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->getKey(),
        ])->first();

        $secret->is_enabled = true;
        $secret->recovery_codes = (new TotpGenerator)->hashRecoveryCodes($setup['recovery_codes']);
        $secret->save();

        $allCodes = $secret->recovery_codes;
        $recoveryCode = $setup['recovery_codes'][0];
        $hashed = hash('sha256', $recoveryCode);

        // Vérifier que le code est présent
        $this->assertContains($hashed, $allCodes);

        $valid = $this->totpService->verifyRecoveryCode($this->user, $recoveryCode);

        $this->assertTrue($valid);

        $secret->refresh();
        $remainingCodes = $secret->recovery_codes;

        // Le code utilisé a été supprimé
        $this->assertNotContains($hashed, $remainingCodes);
        $this->assertCount(9, $remainingCodes);

        // Les autres codes sont toujours présents
        $otherCodes = array_filter($allCodes, fn ($h) => $h !== $hashed);
        foreach ($otherCodes as $code) {
            $this->assertContains($code, $remainingCodes);
        }
    }
}
