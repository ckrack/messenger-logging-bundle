<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Logging;

use BackedEnum;
use C10k\MessengerLoggingBundle\Stamp\MessageUuidStamp;
use DateTimeInterface;
use Stringable;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\AbstractWorkerMessageEvent;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Service\ServiceProviderInterface;
use Throwable;
use UnitEnum;

use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function class_implements;
use function class_parents;
use function get_debug_type;
use function is_array;
use function is_object;
use function is_scalar;
use function ksort;

final class MessengerLogContextBuilder
{
    /** @var ServiceProviderInterface<StampNormalizerInterface> */
    private readonly ServiceProviderInterface $stampNormalizers;

    /**
     * @param ServiceProviderInterface<StampNormalizerInterface>|null $stampNormalizers
     */
    public function __construct(
        ServiceProviderInterface|null $stampNormalizers = null,
    ) {
        /** @var ServiceProviderInterface<StampNormalizerInterface> $resolvedStampNormalizers */
        $resolvedStampNormalizers = $stampNormalizers ?? new ServiceLocator([]);

        $this->stampNormalizers = $resolvedStampNormalizers;
    }

    public function withUuid(Envelope $envelope): Envelope
    {
        if ($this->uuid($envelope) !== null) {
            return $envelope;
        }

        return $envelope->with(new MessageUuidStamp(Uuid::v7()->toRfc4122()));
    }

    public function ensureUuidOnWorkerEvent(AbstractWorkerMessageEvent $event): void
    {
        if ($this->uuid($event->getEnvelope()) !== null) {
            return;
        }

        $event->addStamps(new MessageUuidStamp(Uuid::v7()->toRfc4122()));
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function build(Envelope $envelope, array $context = []): array
    {
        $sentToFailureTransportStamp = $envelope->last(SentToFailureTransportStamp::class);
        $transportMessageIdStamp = $envelope->last(TransportMessageIdStamp::class);

        return array_merge(
            [
                'uuid' => $this->uuid($envelope),
                'message_class' => $envelope->getMessage()::class,
                'retry_count' => RedeliveryStamp::getRetryCountFromEnvelope($envelope),
                'received_transport_names' => $this->receivedTransportNames($envelope),
                'from_failed_transport' => $sentToFailureTransportStamp !== null,
                'failed_transport_original_receiver_name' => $sentToFailureTransportStamp?->getOriginalReceiverName(),
                'transport_message_id' => $transportMessageIdStamp?->getId(),
                'stamps' => $this->normalizeStamps($envelope),
            ],
            $context,
        );
    }

    private function uuid(Envelope $envelope): string|null
    {
        return $envelope->last(MessageUuidStamp::class)?->getUuid();
    }

    /**
     * @return list<string>
     */
    private function receivedTransportNames(Envelope $envelope): array
    {
        $names = [];

        /** @var list<ReceivedStamp> $receivedStamps */
        $receivedStamps = $envelope->all(ReceivedStamp::class);

        foreach ($receivedStamps as $receivedStamp) {
            $transportName = $receivedStamp->getTransportName();

            if (isset($names[$transportName])) {
                continue;
            }

            $names[$transportName] = $transportName;
        }

        return array_values($names);
    }

    /**
     * @return list<array{class: class-string<StampInterface>, context: array<string, mixed>}>
     */
    private function normalizeStamps(Envelope $envelope): array
    {
        $normalizedStamps = [];

        /** @var array<class-string<StampInterface>, list<StampInterface>> $allStamps */
        $allStamps = $envelope->all();

        foreach ($allStamps as $stampClass => $stamps) {
            foreach ($stamps as $stamp) {
                $normalizedStamps[] = [
                    'class' => $stampClass,
                    'context' => $this->normalizeStamp($stamp),
                ];
            }
        }

        return $normalizedStamps;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeStamp(StampInterface $stamp): array
    {
        $stampNormalizer = $this->stampNormalizer($stamp);

        if ($stampNormalizer === null) {
            return [];
        }

        return $this->normalizeContext($stampNormalizer->normalize($stamp));
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function normalizeContext(array $context): array
    {
        $normalizedContext = [];

        foreach ($context as $key => $value) {
            $normalizedContext[$key] = $this->normalizeValue($value);
        }

        ksort($normalizedContext);

        return $normalizedContext;
    }

    private function stampNormalizer(StampInterface $stamp): StampNormalizerInterface|null
    {
        foreach ($this->stampNormalizerCandidates($stamp) as $stampClass) {
            if (!$this->stampNormalizers->has($stampClass)) {
                continue;
            }

            return $this->stampNormalizers->get($stampClass);
        }

        return null;
    }

    /**
     * @return list<class-string>
     */
    private function stampNormalizerCandidates(StampInterface $stamp): array
    {
        return array_values(
            array_unique(
                array_merge(
                    [$stamp::class],
                    array_values(class_parents($stamp) ?: []),
                    array_values(class_implements($stamp) ?: []),
                ),
            ),
        );
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof Throwable) {
            return [
                'class' => $value::class,
                'message' => $value->getMessage(),
                'code' => $value->getCode(),
            ];
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_array($value)) {
            return array_map($this->normalizeValue(...), $value);
        }

        if (!is_object($value)) {
            return get_debug_type($value);
        }

        return [
            'class' => $value::class,
            'type' => get_debug_type($value),
        ];
    }
}
