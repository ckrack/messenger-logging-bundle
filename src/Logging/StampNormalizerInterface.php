<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Logging;

use Symfony\Component\Messenger\Stamp\StampInterface;

interface StampNormalizerInterface
{
    public const SERVICE_TAG = 'c10k_messenger_logging.stamp_normalizer';

    /**
     * @return class-string<StampInterface>
     */
    public static function getSupportedStampClass(): string;

    /**
     * @return array<string, mixed>
     */
    public function normalize(StampInterface $stamp): array;
}
