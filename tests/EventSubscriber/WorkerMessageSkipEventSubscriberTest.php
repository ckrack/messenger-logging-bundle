<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Tests\EventSubscriber;

use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageSkipEventSubscriber;
use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use C10k\MessengerLoggingBundle\Stamp\MessageUuidStamp;
use C10k\MessengerLoggingBundle\Tests\Fixtures\DummyMessage;
use C10k\MessengerLoggingBundle\Tests\Fixtures\InMemoryLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageSkipEvent;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

#[CoversClass(WorkerMessageSkipEventSubscriber::class)]
final class WorkerMessageSkipEventSubscriberTest extends TestCase
{
    public function testItLogsSkippedMessages(): void
    {
        if (!class_exists(WorkerMessageSkipEvent::class)) {
            self::markTestSkipped('WorkerMessageSkipEvent is not available in this Messenger version.');
        }

        $logger = new InMemoryLogger();
        $subscriber = new WorkerMessageSkipEventSubscriber(new MessengerLogContextBuilder(), $logger);

        $subscriber->onSkipped(
            new WorkerMessageSkipEvent(
                new Envelope(
                    new DummyMessage('message-6'),
                    [
                        new MessageUuidStamp('018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc'),
                        new ReceivedStamp('async'),
                    ],
                ),
                'async',
            ),
        );

        self::assertSame('Messenger message skipped.', $logger->lastRecord()['message']);
        self::assertSame('018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc', $logger->lastRecord()['context']['uuid']);
        self::assertSame('async', $logger->lastRecord()['context']['receiver_name']);
    }
}
