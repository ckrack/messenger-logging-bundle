<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Tests\Fixtures;

use C10k\MessengerLoggingBundle\Logging\StampNormalizerInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

final class ConfiguredBusNameStampNormalizer implements StampNormalizerInterface
{
    public static function getSupportedStampClass(): string
    {
        return BusNameStamp::class;
    }

    public function normalize(StampInterface $stamp): array
    {
        if (!$stamp instanceof BusNameStamp) {
            throw new \InvalidArgumentException(sprintf('Expected "%s", got "%s".', BusNameStamp::class, $stamp::class));
        }

        return ['configured_bus_name' => 'configured:'.$stamp->getBusName()];
    }
}
