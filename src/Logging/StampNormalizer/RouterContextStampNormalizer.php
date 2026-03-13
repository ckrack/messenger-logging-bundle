<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Logging\StampNormalizer;

use C10k\MessengerLoggingBundle\Logging\StampNormalizerInterface;
use Symfony\Component\Messenger\Stamp\RouterContextStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

final class RouterContextStampNormalizer implements StampNormalizerInterface
{
    public static function getSupportedStampClass(): string
    {
        return RouterContextStamp::class;
    }

    public function normalize(StampInterface $stamp): array
    {
        if (!$stamp instanceof RouterContextStamp) {
            throw new \InvalidArgumentException(sprintf('Expected "%s", got "%s".', RouterContextStamp::class, $stamp::class));
        }

        return [
            'base_url' => $stamp->getBaseUrl(),
            'method' => $stamp->getMethod(),
            'host' => $stamp->getHost(),
            'scheme' => $stamp->getScheme(),
            'http_port' => $stamp->getHttpPort(),
            'https_port' => $stamp->getHttpsPort(),
            'path_info' => $stamp->getPathInfo(),
        ];
    }
}
