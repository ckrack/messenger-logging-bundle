<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Tests\EventSubscriber;

use C10k\MessengerLoggingBundle\EventSubscriber\SendMessageToTransportsEventSubscriber;
use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use C10k\MessengerLoggingBundle\Stamp\MessageUuidStamp;
use C10k\MessengerLoggingBundle\Tests\Fixtures\DummyMessage;
use C10k\MessengerLoggingBundle\Tests\Fixtures\InMemoryLogger;
use Psr\Log\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Uid\UuidV7;

#[CoversClass(SendMessageToTransportsEventSubscriber::class)]
final class SendMessageToTransportsEventSubscriberTest extends TestCase
{
    public function testItAssignsAUuidStampAndLogsQueueing(): void
    {
        $logger = new InMemoryLogger();
        $subscriber = new SendMessageToTransportsEventSubscriber(new MessengerLogContextBuilder(), $logger);
        $event = new SendMessageToTransportsEvent(
            new Envelope(new DummyMessage('message-1')),
            ['async' => new class () implements SenderInterface {
                public function send(Envelope $envelope): Envelope
                {
                    return $envelope;
                }
            }],
        );

        $subscriber->onQueued($event);

        $uuidStamp = $event->getEnvelope()->last(MessageUuidStamp::class);

        self::assertInstanceOf(MessageUuidStamp::class, $uuidStamp);
        self::assertSame(
            $uuidStamp->getUuid(),
            UuidV7::fromString($uuidStamp->getUuid())->toRfc4122(),
        );
        self::assertSame('Messenger message queued.', $logger->lastRecord()['message']);
        self::assertSame($uuidStamp->getUuid(), $logger->lastRecord()['context']['uuid']);
        self::assertSame(['async'], $logger->lastRecord()['context']['sender_names']);
    }

    public function testItUsesConfiguredLogLevelForQueueing(): void
    {
        $logger = new InMemoryLogger();
        $subscriber = new SendMessageToTransportsEventSubscriber(
            new MessengerLogContextBuilder(),
            $logger,
            LogLevel::DEBUG,
        );
        $event = new SendMessageToTransportsEvent(
            new Envelope(new DummyMessage('message-1')),
            ['async' => new class () implements SenderInterface {
                public function send(Envelope $envelope): Envelope
                {
                    return $envelope;
                }
            }],
        );

        $subscriber->onQueued($event);

        self::assertSame(LogLevel::DEBUG, $logger->lastRecord()['level']);
    }
}
