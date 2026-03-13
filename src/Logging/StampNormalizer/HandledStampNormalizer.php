<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Logging\StampNormalizer;

use C10k\MessengerLoggingBundle\Logging\StampNormalizerInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

final class HandledStampNormalizer implements StampNormalizerInterface
{
    public static function getSupportedStampClass(): string
    {
        return HandledStamp::class;
    }

    public function normalize(StampInterface $stamp): array
    {
        if (!$stamp instanceof HandledStamp) {
            throw new \InvalidArgumentException(sprintf('Expected "%s", got "%s".', HandledStamp::class, $stamp::class));
        }

        return ['handler_name' => $stamp->getHandlerName()];
    }
}
