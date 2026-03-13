<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Tests\Fixtures;

final readonly class DummyMessage
{
    public function __construct(
        public string $id,
    ) {
    }
}
