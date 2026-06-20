<?php

declare(strict_types=1);

namespace AndyDefer\LaravelTotp\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

final class QrCodeGenerator
{
    public function generate(
        string $account,
        string $secret,
        string $issuer,
        int $digits = 6,
        int $period = 30,
        string $algorithm = 'SHA1',
    ): string {
        $uri = $this->buildUri($account, $secret, $issuer, $digits, $period, $algorithm);

        return $this->generateFromUri($uri);
    }

    public function generateFromUri(string $uri): string
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->writerOptions([])
            ->data($uri)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size(300)
            ->margin(10)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->build();

        return $result->getString();
    }

    public function generateDataUri(string $uri): string
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->writerOptions([])
            ->data($uri)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size(300)
            ->margin(10)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->build();

        return $result->getDataUri();
    }

    public function buildUri(
        string $account,
        string $secret,
        string $issuer,
        int $digits = 6,
        int $period = 30,
        string $algorithm = 'SHA1',
    ): string {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=%s&digits=%d&period=%d',
            urlencode($issuer),
            urlencode($account),
            $secret,
            urlencode($issuer),
            $algorithm,
            $digits,
            $period,
        );
    }
}