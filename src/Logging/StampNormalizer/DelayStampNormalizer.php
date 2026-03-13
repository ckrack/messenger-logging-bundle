<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Logging\StampNormalizer;

use C10k\MessengerLoggingBundle\Logging\StampNormalizerInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

final class DelayStampNormalizer implements StampNormalizerInterface
{
    public static function getSupportedStampClass(): string
    {
        return DelayStamp::class;
    }

    public function normalize(StampInterface $stamp): array
    {
        if (!$stamp instanceof DelayStamp) {
            throw new \InvalidArgumentException(sprintf('Expected "%s", got "%s".', DelayStamp::class, $stamp::class));
        }

        return ['delay' => $stamp->getDelay()];
    }
}
