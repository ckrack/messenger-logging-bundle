<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Logging;

use BackedEnum;
use C10k\MessengerLoggingBundle\Stamp\MessageUuidStamp;
use DateTimeInterface;
use ReflectionClass;
use ReflectionMethod;
use Stringable;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\AbstractWorkerMessageEvent;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Uid\Uuid;
use Throwable;
use UnitEnum;

use function array_map;
use function array_merge;
use function array_values;
use function get_debug_type;
use function is_array;
use function is_object;
use function is_scalar;
use function ksort;
use function lcfirst;
use function preg_replace;
use function str_starts_with;
use function strtolower;

final class MessengerLogContextBuilder
{
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

        foreach ($envelope->all(ReceivedStamp::class) as $receivedStamp) {
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

        foreach ($envelope->all() as $stampClass => $stamps) {
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
        $context = [];
        $reflectionClass = new ReflectionClass($stamp);

        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->getNumberOfRequiredParameters() !== 0) {
                continue;
            }

            $key = $this->contextKey($method->getName());

            if ($key === null) {
                continue;
            }

            $context[$key] = $this->normalizeValue($method->invoke($stamp));
        }

        ksort($context);

        return $context;
    }

    private function contextKey(string $methodName): string|null
    {
        foreach (['get', 'is', 'has'] as $prefix) {
            if (!str_starts_with($methodName, $prefix)) {
                continue;
            }

            $rawName = lcfirst((string) preg_replace('/^'.$prefix.'/', '', $methodName));

            if ($rawName === '') {
                return null;
            }

            return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $rawName));
        }

        return null;
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
