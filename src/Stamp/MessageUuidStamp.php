<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final readonly class MessageUuidStamp implements StampInterface
{
    public function __construct(
        private string $uuid,
    ) {
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }
}
