<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Logging\StampNormalizer;

use C10k\MessengerLoggingBundle\Logging\StampNormalizerInterface;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

final class SentStampNormalizer implements StampNormalizerInterface
{
    public static function getSupportedStampClass(): string
    {
        return SentStamp::class;
    }

    public function normalize(StampInterface $stamp): array
    {
        if (!$stamp instanceof SentStamp) {
            throw new \InvalidArgumentException(sprintf('Expected "%s", got "%s".', SentStamp::class, $stamp::class));
        }

        return [
            'sender_class' => $stamp->getSenderClass(),
            'sender_alias' => $stamp->getSenderAlias(),
        ];
    }
}
