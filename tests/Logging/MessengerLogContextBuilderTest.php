<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Tests\Logging;

use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use C10k\MessengerLoggingBundle\Logging\StampNormalizer\BusNameStampNormalizer;
use C10k\MessengerLoggingBundle\Logging\StampNormalizer\HandledStampNormalizer;
use C10k\MessengerLoggingBundle\Logging\StampNormalizer\RedeliveryStampNormalizer;
use C10k\MessengerLoggingBundle\Logging\StampNormalizerInterface;
use C10k\MessengerLoggingBundle\Stamp\MessageUuidStamp;
use C10k\MessengerLoggingBundle\Tests\Fixtures\CustomStamp;
use C10k\MessengerLoggingBundle\Tests\Fixtures\CustomStampNormalizer;
use C10k\MessengerLoggingBundle\Tests\Fixtures\DummyMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
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
        $builder = $this->createBuilder(
            new BusNameStampNormalizer(),
            new HandledStampNormalizer(),
            new RedeliveryStampNormalizer(),
        );
        $redeliveredAt = new \DateTimeImmutable('2024-02-03T04:05:06+00:00');
        $context = $builder->build(
            new Envelope(
                new DummyMessage('message-1'),
                [
                    new MessageUuidStamp('018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc'),
                    new BusNameStamp('command.bus'),
                    new ReceivedStamp('failed'),
                    new SentToFailureTransportStamp('async'),
                    new RedeliveryStamp(2, $redeliveredAt),
                    new HandledStamp(
                        ['result' => 'this should never be logged by default'],
                        'App\\MessageHandler\\DummyHandler',
                    ),
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

        self::assertSame([], $this->stampContext($stamps, MessageUuidStamp::class));
        self::assertSame(
            ['bus_name' => 'command.bus'],
            $this->stampContext($stamps, BusNameStamp::class),
        );
        self::assertSame(
            [],
            $this->stampContext($stamps, ReceivedStamp::class),
        );
        self::assertSame(
            [],
            $this->stampContext($stamps, SentToFailureTransportStamp::class),
        );
        self::assertSame(
            ['redelivered_at' => $redeliveredAt->format(\DateTimeInterface::ATOM)],
            $this->stampContext($stamps, RedeliveryStamp::class),
        );
        self::assertSame(
            ['handler_name' => 'App\\MessageHandler\\DummyHandler'],
            $this->stampContext($stamps, HandledStamp::class),
        );
        self::assertSame([], $this->stampContext($stamps, TransportMessageIdStamp::class));
    }

    public function testItUsesRegisteredCustomStampNormalizer(): void
    {
        $builder = $this->createBuilder(new CustomStampNormalizer());

        $context = $builder->build(
            new Envelope(
                new DummyMessage('message-1'),
                [
                    new CustomStamp(
                        'safe',
                        ['token' => 'secret', 'large' => ['payload' => 'hidden']],
                    ),
                ],
            ),
        );

        /** @var list<array{class: string, context: array<string, mixed>}> $stamps */
        $stamps = $context['stamps'];

        self::assertSame(
            ['safe_value' => 'safe'],
            $this->stampContext($stamps, CustomStamp::class),
        );
    }

    public function testItLeavesUnknownStampsEmptyWhenNoNormalizerExists(): void
    {
        $builder = new MessengerLogContextBuilder();

        $context = $builder->build(
            new Envelope(
                new DummyMessage('message-1'),
                [
                    new CustomStamp(
                        'safe',
                        ['token' => 'secret', 'large' => ['payload' => 'visible']],
                    ),
                ],
            ),
        );

        /** @var list<array{class: string, context: array<string, mixed>}> $stamps */
        $stamps = $context['stamps'];

        self::assertSame(
            [],
            $this->stampContext($stamps, CustomStamp::class),
        );
    }

    private function createBuilder(StampNormalizerInterface ...$stampNormalizers): MessengerLogContextBuilder
    {
        /** @var array<string, callable(mixed): StampNormalizerInterface> $factories */
        $factories = [];

        foreach ($stampNormalizers as $stampNormalizer) {
            $factories[$stampNormalizer::getSupportedStampClass()] = static fn (mixed $locator): StampNormalizerInterface => $stampNormalizer;
        }

        /** @var ServiceLocator<StampNormalizerInterface> $stampNormalizerLocator */
        $stampNormalizerLocator = new ServiceLocator($factories);

        return new MessengerLogContextBuilder($stampNormalizerLocator);
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
