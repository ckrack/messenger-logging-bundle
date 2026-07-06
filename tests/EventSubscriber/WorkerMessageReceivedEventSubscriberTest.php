<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Tests\EventSubscriber;

use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageReceivedEventSubscriber;
use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use C10k\MessengerLoggingBundle\Logging\ProcessingDurationTracker;
use C10k\MessengerLoggingBundle\Stamp\MessageUuidStamp;
use C10k\MessengerLoggingBundle\Tests\Fixtures\DummyMessage;
use C10k\MessengerLoggingBundle\Tests\Fixtures\MonologTestLoggerTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
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
        $clock = new MockClock('2024-04-23 17:41:33.936 UTC');
        [$logger, $handler] = $this->createTestLogger();
        $subscriber = new WorkerMessageReceivedEventSubscriber(
            new MessengerLogContextBuilder(clock: $clock),
            new ProcessingDurationTracker($clock),
            $logger,
        );
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
        self::assertSame('received', $record->context['event']);
        self::assertSame('018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc', $record->context['uuid']);
        self::assertSame('failed', $record->context['receiver_name']);
        self::assertSame(1234, $record->context['time_in_queue_ms']);
        self::assertTrue($record->context['from_failed_transport']);
        self::assertSame(
            'async',
            $record->context['failed_transport_original_receiver_name'],
        );
    }

    public function testItBackfillsUuidAndStartsDurationTracking(): void
    {
        $clock = new MockClock('2024-04-23 17:41:32 UTC');
        $tracker = new ProcessingDurationTracker($clock);
        [$logger, $handler] = $this->createTestLogger();
        $subscriber = new WorkerMessageReceivedEventSubscriber(
            new MessengerLogContextBuilder(clock: $clock),
            $tracker,
            $logger,
        );

        $subscriber->onReceived(
            new WorkerMessageReceivedEvent(
                new Envelope(new DummyMessage('message-2')),
                'async',
            ),
        );

        $record = $this->lastRecord($handler);

        self::assertSame(0, $record->context['time_in_queue_ms']);
        self::assertIsString($record->context['uuid']);

        $clock->sleep(0.5);

        self::assertSame(500, $tracker->stopMs($record->context['uuid']));
    }

    public function testItLogsNullQueueLatencyForNonVersionSevenUuid(): void
    {
        $clock = new MockClock('2024-04-23 17:41:32 UTC');
        [$logger, $handler] = $this->createTestLogger();
        $subscriber = new WorkerMessageReceivedEventSubscriber(
            new MessengerLogContextBuilder(clock: $clock),
            new ProcessingDurationTracker($clock),
            $logger,
        );

        $subscriber->onReceived(
            new WorkerMessageReceivedEvent(
                new Envelope(
                    new DummyMessage('message-2'),
                    [new MessageUuidStamp('018f0c0c-6f9e-4eec-bfc3-6f8d3426f5dc')],
                ),
                'async',
            ),
        );

        self::assertNull($this->lastRecord($handler)->context['time_in_queue_ms']);
    }
}
