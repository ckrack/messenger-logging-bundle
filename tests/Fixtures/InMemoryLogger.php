<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Tests\Fixtures;

use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use UnexpectedValueException;

use function is_string;

final class InMemoryLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    private array $records = [];

    /**
     * @param mixed $level
     * @param array<string, mixed> $context
     */
    public function log($level, Stringable|string $message, array $context = []): void
    {
        if (!is_string($level)) {
            throw new UnexpectedValueException('Logger level must be a string.');
        }

        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public function records(): array
    {
        return $this->records;
    }

    /**
     * @return array{level: string, message: string, context: array<string, mixed>}
     */
    public function lastRecord(): array
    {
        if ($this->records === []) {
            throw new RuntimeException('No log records captured.');
        }

        return $this->records[array_key_last($this->records)];
    }
}
