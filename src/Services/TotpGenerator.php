<?php

declare(strict_types=1);

namespace AndyDefer\LaravelTotp\Services;

use Illuminate\Support\Str;

final class TotpGenerator
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $length = 32): string
    {
        $secret = '';
        $maxIndex = strlen(self::BASE32_ALPHABET) - 1;

        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_ALPHABET[random_int(0, $maxIndex)];
        }

        return $secret;
    }

    public function generateCode(
        string $secret,
        ?int $timestamp = null,
        int $digits = 6,
        int $period = 30,
    ): string {
        $timestamp = $timestamp ?? time();
        $decodedSecret = $this->base32Decode($secret);

        if ($decodedSecret === '') {
            return $this->generateFallbackCode($digits);
        }

        $counter = floor($timestamp / $period);
        $packed = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $packed, $decodedSecret, true);

        $offset = ord(substr($hash, -1)) & 0xF;
        $truncated = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($truncated % 10 ** $digits), $digits, '0', STR_PAD_LEFT);
    }

    public function generateCodesWithWindow(
        string $secret,
        int $digits = 6,
        int $period = 30,
        int $window = 1,
        ?int $timestamp = null,
    ): array {
        $timestamp = $timestamp ?? time();
        $codes = [];

        for ($i = -$window; $i <= $window; $i++) {
            $offsetTimestamp = $timestamp + ($i * $period);
            $codes[$i] = $this->generateCode($secret, $offsetTimestamp, $digits, $period);
        }

        return $codes;
    }

    public function generateRecoveryCodes(int $count = 10, int $length = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random($length));
        }

        return $codes;
    }

    public function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn (string $code) => hash('sha256', $code), $codes);
    }

    public function verifyRecoveryCode(string $code, array $hashedCodes): bool
    {
        $hashed = hash('sha256', $code);

        foreach ($hashedCodes as $storedHash) {
            if (hash_equals($storedHash, $hashed)) {
                return true;
            }
        }

        return false;
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper($secret);
        $buffer = '';

        foreach (str_split($secret) as $char) {
            $index = strpos(self::BASE32_ALPHABET, $char);

            if ($index === false) {
                continue;
            }

            $buffer .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        if ($buffer === '') {
            return '';
        }

        $result = '';
        foreach (str_split($buffer, 8) as $byte) {
            if (strlen($byte) === 8) {
                $result .= chr(bindec($byte));
            }
        }

        return $result;
    }

    private function generateFallbackCode(int $digits): string
    {
        return str_pad((string) random_int(0, 10 ** $digits - 1), $digits, '0', STR_PAD_LEFT);
    }
}