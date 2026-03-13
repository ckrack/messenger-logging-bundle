<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Tests\Fixtures;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class CustomStamp implements StampInterface
{
    /**
     * @param array<string, mixed> $secretPayload
     */
    public function __construct(
        private readonly string $safeValue,
        private readonly array $secretPayload,
    ) {
    }

    public function getSafeValue(): string
    {
        return $this->safeValue;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSecretPayload(): array
    {
        return $this->secretPayload;
    }
}
