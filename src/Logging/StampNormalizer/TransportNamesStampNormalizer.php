<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Logging\StampNormalizer;

use C10k\MessengerLoggingBundle\Logging\StampNormalizerInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

final class TransportNamesStampNormalizer implements StampNormalizerInterface
{
    public static function getSupportedStampClass(): string
    {
        return TransportNamesStamp::class;
    }

    public function normalize(StampInterface $stamp): array
    {
        if (!$stamp instanceof TransportNamesStamp) {
            throw new \InvalidArgumentException(sprintf('Expected "%s", got "%s".', TransportNamesStamp::class, $stamp::class));
        }

        return ['transport_names' => $stamp->getTransportNames()];
    }
}
