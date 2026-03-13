<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Tests\Logging;

use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use C10k\MessengerLoggingBundle\Stamp\MessageUuidStamp;
use C10k\MessengerLoggingBundle\Tests\Fixtures\DummyMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Uid\UuidV7;

#[CoversClass(MessengerLogContextBuilder::class)]
final class MessengerLogContextBuilderTest extends TestCase
{
    public function testItAddsUuidVersionSevenStampWhenMissing(): void
    {
        $builder = new MessengerLogContextBuilder();
        $envelope = $builder->withUuid(new Envelope(new DummyMessage('message-1')));
        $uuidStamp = $envelope->last(MessageUuidStamp::class);

        self::assertInstanceOf(MessageUuidStamp::class, $uuidStamp);
        self::assertSame(
            $uuidStamp->getUuid(),
            UuidV7::fromString($uuidStamp->getUuid())->toRfc4122(),
        );
    }

    public function testItBuildsStructuredEnvelopeContext(): void
    {
        $builder = new MessengerLogContextBuilder();
        $context = $builder->build(
            new Envelope(
                new DummyMessage('message-1'),
                [
                    new MessageUuidStamp('018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc'),
                    new BusNameStamp('command.bus'),
                    new ReceivedStamp('failed'),
                    new SentToFailureTransportStamp('async'),
                    new RedeliveryStamp(2),
                    new TransportMessageIdStamp('transport-id-1'),
                ],
            ),
        );

        self::assertSame('018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc', $context['uuid']);
        self::assertSame(DummyMessage::class, $context['message_class']);
        self::assertSame(2, $context['retry_count']);
        self::assertSame(['failed'], $context['received_transport_names']);
        self::assertTrue($context['from_failed_transport']);
        self::assertSame('async', $context['failed_transport_original_receiver_name']);
        self::assertSame('transport-id-1', $context['transport_message_id']);
        /** @var list<array{class: string, context: array<string, mixed>}> $stamps */
        $stamps = $context['stamps'];

        self::assertSame(['uuid' => '018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc'], $this->stampContext($stamps, MessageUuidStamp::class));
        self::assertSame(
            ['transport_name' => 'failed'],
            $this->stampContext($stamps, ReceivedStamp::class),
        );
        self::assertSame(
            ['original_receiver_name' => 'async'],
            $this->stampContext($stamps, SentToFailureTransportStamp::class),
        );
    }

    /**
     * @param list<array{class: string, context: array<string, mixed>}> $stamps
     *
     * @return array<string, mixed>
     */
    private function stampContext(array $stamps, string $stampClass): array
    {
        foreach ($stamps as $stamp) {
            if ($stamp['class'] === $stampClass) {
                return $stamp['context'];
            }
        }

        self::fail('Expected stamp not found: '.$stampClass);
    }
}
