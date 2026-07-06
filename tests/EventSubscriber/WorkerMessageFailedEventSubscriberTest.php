<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Tests\EventSubscriber;

use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageFailedEventSubscriber;
use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use C10k\MessengerLoggingBundle\Logging\ProcessingDurationTracker;
use C10k\MessengerLoggingBundle\Stamp\MessageUuidStamp;
use C10k\MessengerLoggingBundle\Tests\Fixtures\DummyMessage;
use C10k\MessengerLoggingBundle\Tests\Fixtures\MonologTestLoggerTrait;
use Monolog\Level;
use Psr\Log\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

#[CoversClass(WorkerMessageFailedEventSubscriber::class)]
final class WorkerMessageFailedEventSubscriberTest extends TestCase
{
    use MonologTestLoggerTrait;

    public function testItLogsFailuresIncludingRetryInformation(): void
    {
        $clock = new MockClock('2024-04-23 17:41:32 UTC');
        $tracker = new ProcessingDurationTracker($clock);
        $tracker->start('018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc');
        $clock->sleep(0.875);

        [$logger, $handler] = $this->createTestLogger();
        $subscriber = new WorkerMessageFailedEventSubscriber(
            new MessengerLogContextBuilder(clock: $clock),
            $tracker,
            $logger,
        );
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
            new \RuntimeException('boom', 123),
        );
        $event->setForRetry();

        $subscriber->onFailed($event);

        $record = $this->lastRecord($handler);

        self::assertSame('Messenger message failed.', $record->message);
        self::assertSame('failed', $record->context['event']);
        self::assertSame('018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc', $record->context['uuid']);
        self::assertSame('async', $record->context['receiver_name']);
        self::assertSame(1, $record->context['retry_count']);
        self::assertTrue($record->context['will_retry']);
        self::assertSame(875, $record->context['handling_duration_ms']);
        self::assertNull($tracker->stopMs('018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc'));
        self::assertSame(\RuntimeException::class, $record->context['exception_class']);
        self::assertSame('boom', $record->context['exception_message']);
        self::assertSame('123', $record->context['exception_code']);
    }

    public function testItUsesConfiguredLogLevelForFailures(): void
    {
        [$logger, $handler] = $this->createTestLogger();
        $subscriber = new WorkerMessageFailedEventSubscriber(
            new MessengerLogContextBuilder(new MockClock('2024-04-23 17:41:32 UTC')),
            new ProcessingDurationTracker(new MockClock('2024-04-23 17:41:32 UTC')),
            $logger,
            LogLevel::INFO,
        );
        $event = new WorkerMessageFailedEvent(
            new Envelope(new DummyMessage('message-3')),
            'async',
            new \RuntimeException('boom'),
        );

        $subscriber->onFailed($event);

        self::assertSame(Level::Info, $this->lastRecord($handler)->level);
    }
}
