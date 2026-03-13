<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Tests\EventSubscriber;

use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageHandledEventSubscriber;
use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use C10k\MessengerLoggingBundle\Stamp\MessageUuidStamp;
use C10k\MessengerLoggingBundle\Tests\Fixtures\DummyMessage;
use C10k\MessengerLoggingBundle\Tests\Fixtures\InMemoryLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

#[CoversClass(WorkerMessageHandledEventSubscriber::class)]
final class WorkerMessageHandledEventSubscriberTest extends TestCase
{
    public function testItLogsHandledMessages(): void
    {
        $logger = new InMemoryLogger();
        $subscriber = new WorkerMessageHandledEventSubscriber(new MessengerLogContextBuilder(), $logger);

        $subscriber->onHandled(
            new WorkerMessageHandledEvent(
                new Envelope(
                    new DummyMessage('message-4'),
                    [
                        new MessageUuidStamp('018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc'),
                        new ReceivedStamp('async'),
                    ],
                ),
                'async',
            ),
        );

        self::assertSame('Messenger message handled.', $logger->lastRecord()['message']);
        self::assertSame('018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc', $logger->lastRecord()['context']['uuid']);
        self::assertSame('async', $logger->lastRecord()['context']['receiver_name']);
    }
}
