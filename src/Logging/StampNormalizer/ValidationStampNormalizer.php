<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Logging\StampNormalizer;

use C10k\MessengerLoggingBundle\Logging\StampNormalizerInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Stamp\ValidationStamp;

final class ValidationStampNormalizer implements StampNormalizerInterface
{
    public static function getSupportedStampClass(): string
    {
        return ValidationStamp::class;
    }

    public function normalize(StampInterface $stamp): array
    {
        if (!$stamp instanceof ValidationStamp) {
            throw new \InvalidArgumentException(sprintf('Expected "%s", got "%s".', ValidationStamp::class, $stamp::class));
        }

        return ['groups' => $stamp->getGroups()];
    }
}
