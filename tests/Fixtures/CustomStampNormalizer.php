<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Tests\Fixtures;

use C10k\MessengerLoggingBundle\Logging\StampNormalizerInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;

final class CustomStampNormalizer implements StampNormalizerInterface
{
    public static function getSupportedStampClass(): string
    {
        return CustomStamp::class;
    }

    public function normalize(StampInterface $stamp): array
    {
        if (!$stamp instanceof CustomStamp) {
            throw new \InvalidArgumentException(sprintf('Expected "%s", got "%s".', CustomStamp::class, $stamp::class));
        }

        return ['safe_value' => $stamp->getSafeValue()];
    }
}
