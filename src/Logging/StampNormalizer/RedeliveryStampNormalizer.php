<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Logging\StampNormalizer;

use C10k\MessengerLoggingBundle\Logging\StampNormalizerInterface;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

final class RedeliveryStampNormalizer implements StampNormalizerInterface
{
    public static function getSupportedStampClass(): string
    {
        return RedeliveryStamp::class;
    }

    public function normalize(StampInterface $stamp): array
    {
        if (!$stamp instanceof RedeliveryStamp) {
            throw new \InvalidArgumentException(sprintf('Expected "%s", got "%s".', RedeliveryStamp::class, $stamp::class));
        }

        return ['redelivered_at' => $stamp->getRedeliveredAt()];
    }
}
