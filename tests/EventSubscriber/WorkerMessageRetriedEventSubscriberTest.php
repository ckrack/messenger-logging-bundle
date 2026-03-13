<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Tests\EventSubscriber;

use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageRetriedEventSubscriber;
use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use C10k\MessengerLoggingBundle\Stamp\MessageUuidStamp;
use C10k\MessengerLoggingBundle\Tests\Fixtures\DummyMessage;
use C10k\MessengerLoggingBundle\Tests\Fixtures\InMemoryLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageRetriedEvent;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

#[CoversClass(WorkerMessageRetriedEventSubscriber::class)]
final class WorkerMessageRetriedEventSubscriberTest extends TestCase
{
    public function testItLogsRetriedMessages(): void
    {
        $logger = new InMemoryLogger();
        $subscriber = new WorkerMessageRetriedEventSubscriber(new MessengerLogContextBuilder(), $logger);

        $subscriber->onRetried(
            new WorkerMessageRetriedEvent(
                new Envelope(
                    new DummyMessage('message-5'),
                    [
                        new MessageUuidStamp('018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc'),
                        new RedeliveryStamp(2),
                        new ReceivedStamp('async'),
                    ],
                ),
                'async',
            ),
        );

        self::assertSame('Messenger message scheduled for retry.', $logger->lastRecord()['message']);
        self::assertSame('018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc', $logger->lastRecord()['context']['uuid']);
        self::assertSame(2, $logger->lastRecord()['context']['retry_count']);
        self::assertSame('async', $logger->lastRecord()['context']['receiver_name']);
    }
}
