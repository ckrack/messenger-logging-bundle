<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Tests\EventSubscriber;

use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageFailedEventSubscriber;
use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use C10k\MessengerLoggingBundle\Stamp\MessageUuidStamp;
use C10k\MessengerLoggingBundle\Tests\Fixtures\DummyMessage;
use C10k\MessengerLoggingBundle\Tests\Fixtures\MonologTestLoggerTrait;
use Monolog\Level;
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
    use MonologTestLoggerTrait;

    public function testItLogsFailuresIncludingRetryInformation(): void
    {
        [$logger, $handler] = $this->createTestLogger();
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

        $record = $this->lastRecord($handler);

        self::assertSame('Messenger message failed.', $record->message);
        self::assertSame('018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc', $record->context['uuid']);
        self::assertSame('async', $record->context['receiver_name']);
        self::assertSame(1, $record->context['retry_count']);
        self::assertTrue($record->context['will_retry']);
        self::assertSame(\RuntimeException::class, $record->context['exception_class']);
        self::assertSame('boom', $record->context['exception_message']);
    }

    public function testItUsesConfiguredLogLevelForFailures(): void
    {
        [$logger, $handler] = $this->createTestLogger();
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

        self::assertSame(Level::Info, $this->lastRecord($handler)->level);
    }
}
