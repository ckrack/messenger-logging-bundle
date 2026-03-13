<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Tests\EventSubscriber;

use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageReceivedEventSubscriber;
use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use C10k\MessengerLoggingBundle\Stamp\MessageUuidStamp;
use C10k\MessengerLoggingBundle\Tests\Fixtures\DummyMessage;
use C10k\MessengerLoggingBundle\Tests\Fixtures\MonologTestLoggerTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;

#[CoversClass(WorkerMessageReceivedEventSubscriber::class)]
final class WorkerMessageReceivedEventSubscriberTest extends TestCase
{
    use MonologTestLoggerTrait;

    public function testItLogsReceiveFromFailedQueueWithOriginInformation(): void
    {
        [$logger, $handler] = $this->createTestLogger();
        $subscriber = new WorkerMessageReceivedEventSubscriber(new MessengerLogContextBuilder(), $logger);
        $event = new WorkerMessageReceivedEvent(
            new Envelope(
                new DummyMessage('message-2'),
                [
                    new MessageUuidStamp('018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc'),
                    new SentToFailureTransportStamp('async'),
                    new ReceivedStamp('failed'),
                ],
            ),
            'failed',
        );

        $subscriber->onReceived($event);

        $record = $this->lastRecord($handler);

        self::assertSame('Messenger message received.', $record->message);
        self::assertSame('018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc', $record->context['uuid']);
        self::assertSame('failed', $record->context['receiver_name']);
        self::assertTrue($record->context['from_failed_transport']);
        self::assertSame(
            'async',
            $record->context['failed_transport_original_receiver_name'],
        );
    }
}
