<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Logging;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

use function array_key_first;
use function count;

final class ProcessingDurationTracker
{
    private const MAX_TRACKED = 1000;

    /** @var array<string, DateTimeImmutable> */
    private array $startedAt = [];

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    public function start(string $uuid): void
    {
        while (count($this->startedAt) >= self::MAX_TRACKED) {
            $oldestUuid = array_key_first($this->startedAt);
            unset($this->startedAt[$oldestUuid]);
        }

        $this->startedAt[$uuid] = $this->clock->now();
    }

    public function stopMs(string $uuid): int|null
    {
        $start = $this->startedAt[$uuid] ?? null;

        if ($start === null) {
            return null;
        }

        unset($this->startedAt[$uuid]);

        return $this->milliseconds($this->clock->now()) - $this->milliseconds($start);
    }

    private function milliseconds(DateTimeImmutable $dateTime): int
    {
        return (int) $dateTime->format('Uv');
    }
}
