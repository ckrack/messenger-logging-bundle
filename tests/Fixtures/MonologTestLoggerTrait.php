<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Tests\Fixtures;

use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Monolog\Logger;
use PHPUnit\Framework\Assert;

trait MonologTestLoggerTrait
{
    /**
     * @return array{Logger, TestHandler}
     */
    private function createTestLogger(): array
    {
        $handler = new TestHandler();

        return [new Logger('test', [$handler]), $handler];
    }

    private function lastRecord(TestHandler $handler): LogRecord
    {
        $records = $handler->getRecords();
        Assert::assertNotSame([], $records, 'No log records captured.');

        $lastKey = array_key_last($records);
        Assert::assertNotNull($lastKey);

        return $records[$lastKey];
    }
}
