<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Tests\EventSubscriber;

use C10k\MessengerLoggingBundle\EventSubscriber\SendMessageToTransportsEventSubscriber;
use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use C10k\MessengerLoggingBundle\Stamp\MessageUuidStamp;
use C10k\MessengerLoggingBundle\Tests\Fixtures\DummyMessage;
use C10k\MessengerLoggingBundle\Tests\Fixtures\MonologTestLoggerTrait;
use Monolog\Level;
use Psr\Log\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\MessageSentToTransportsEvent;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Uid\UuidV7;

#[CoversClass(SendMessageToTransportsEventSubscriber::class)]
final class SendMessageToTransportsEventSubscriberTest extends TestCase
{
    use MonologTestLoggerTrait;

    public function testItAssignsAUuidStampBeforeSending(): void
    {
        [$logger, $handler] = $this->createTestLogger();
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

        $subscriber->onSend($event);

        $uuidStamp = $event->getEnvelope()->last(MessageUuidStamp::class);

        self::assertInstanceOf(MessageUuidStamp::class, $uuidStamp);
        self::assertSame(
            $uuidStamp->getUuid(),
            UuidV7::fromString($uuidStamp->getUuid())->toRfc4122(),
        );
        self::assertFalse($handler->hasRecords(Level::Info));
    }

    public function testItLogsQueueingAfterSending(): void
    {
        [$logger, $handler] = $this->createTestLogger();
        $subscriber = new SendMessageToTransportsEventSubscriber(new MessengerLogContextBuilder(), $logger);
        $event = new MessageSentToTransportsEvent(
            new Envelope(
                new DummyMessage('message-1'),
                [new MessageUuidStamp('018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc')],
            ),
            ['async' => new class () implements SenderInterface {
                public function send(Envelope $envelope): Envelope
                {
                    return $envelope;
                }
            }],
        );

        $subscriber->onSent($event);

        $record = $this->lastRecord($handler);

        self::assertSame('Messenger message queued.', $record->message);
        self::assertSame('queued', $record->context['event']);
        self::assertSame('018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc', $record->context['uuid']);
        self::assertSame(['async'], $record->context['sender_names']);
    }

    public function testItUsesConfiguredLogLevelForQueueing(): void
    {
        [$logger, $handler] = $this->createTestLogger();
        $subscriber = new SendMessageToTransportsEventSubscriber(
            new MessengerLogContextBuilder(),
            $logger,
            LogLevel::DEBUG,
        );
        $event = new MessageSentToTransportsEvent(
            new Envelope(
                new DummyMessage('message-1'),
                [new MessageUuidStamp('018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc')],
            ),
            ['async' => new class () implements SenderInterface {
                public function send(Envelope $envelope): Envelope
                {
                    return $envelope;
                }
            }],
        );

        $subscriber->onSent($event);

        self::assertSame(Level::Debug, $this->lastRecord($handler)->level);
    }
}
