<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Tests\EventSubscriber;

use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageRetriedEventSubscriber;
use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use C10k\MessengerLoggingBundle\Stamp\MessageUuidStamp;
use C10k\MessengerLoggingBundle\Tests\Fixtures\DummyMessage;
use C10k\MessengerLoggingBundle\Tests\Fixtures\MonologTestLoggerTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageRetriedEvent;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

#[CoversClass(WorkerMessageRetriedEventSubscriber::class)]
final class WorkerMessageRetriedEventSubscriberTest extends TestCase
{
    use MonologTestLoggerTrait;

    public function testItLogsRetriedMessages(): void
    {
        [$logger, $handler] = $this->createTestLogger();
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

        $record = $this->lastRecord($handler);

        self::assertSame('Messenger message scheduled for retry.', $record->message);
        self::assertSame('018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc', $record->context['uuid']);
        self::assertSame(2, $record->context['retry_count']);
        self::assertSame('async', $record->context['receiver_name']);
    }
}
