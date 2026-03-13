<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Tests\EventSubscriber;

use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageFailedEventSubscriber;
use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use C10k\MessengerLoggingBundle\Stamp\MessageUuidStamp;
use C10k\MessengerLoggingBundle\Tests\Fixtures\DummyMessage;
use C10k\MessengerLoggingBundle\Tests\Fixtures\InMemoryLogger;
use Psr\Log\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

#[CoversClass(WorkerMessageFailedEventSubscriber::class)]
final class WorkerMessageFailedEventSubscriberTest extends TestCase
{
    public function testItLogsFailuresIncludingRetryInformation(): void
    {
        $logger = new InMemoryLogger();
        $subscriber = new WorkerMessageFailedEventSubscriber(new MessengerLogContextBuilder(), $logger);
        $event = new WorkerMessageFailedEvent(
            new Envelope(
                new DummyMessage('message-3'),
                [
                    new MessageUuidStamp('018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc'),
                    new RedeliveryStamp(1),
                    new ReceivedStamp('async'),
                ],
            ),
            'async',
            new \RuntimeException('boom'),
        );
        $event->setForRetry();

        $subscriber->onFailed($event);

        self::assertSame('Messenger message failed.', $logger->lastRecord()['message']);
        self::assertSame('018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc', $logger->lastRecord()['context']['uuid']);
        self::assertSame('async', $logger->lastRecord()['context']['receiver_name']);
        self::assertSame(1, $logger->lastRecord()['context']['retry_count']);
        self::assertTrue($logger->lastRecord()['context']['will_retry']);
        self::assertSame(\RuntimeException::class, $logger->lastRecord()['context']['exception_class']);
        self::assertSame('boom', $logger->lastRecord()['context']['exception_message']);
    }

    public function testItUsesConfiguredLogLevelForFailures(): void
    {
        $logger = new InMemoryLogger();
        $subscriber = new WorkerMessageFailedEventSubscriber(
            new MessengerLogContextBuilder(),
            $logger,
            LogLevel::INFO,
        );
        $event = new WorkerMessageFailedEvent(
            new Envelope(new DummyMessage('message-3')),
            'async',
            new \RuntimeException('boom'),
        );

        $subscriber->onFailed($event);

        self::assertSame(LogLevel::INFO, $logger->lastRecord()['level']);
    }
}
