<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\EventSubscriber;

use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use C10k\MessengerLoggingBundle\Logging\MessengerLogEvent;
use C10k\MessengerLoggingBundle\Logging\ProcessingDurationTracker;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

final class WorkerMessageReceivedEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessengerLogContextBuilder $contextBuilder,
        private readonly ProcessingDurationTracker $processingDurationTracker,
        private readonly LoggerInterface|null $logger = null,
        private readonly string $logLevel = LogLevel::INFO,
    ) {
    }

    public function onReceived(WorkerMessageReceivedEvent $event): void
    {
        $this->contextBuilder->ensureUuidOnWorkerEvent($event);
        $uuid = $this->contextBuilder->uuid($event->getEnvelope());

        if ($uuid !== null) {
            $this->processingDurationTracker->start($uuid);
        }

        $this->logger?->log(
            $this->logLevel,
            'Messenger message received.',
            $this->contextBuilder->build(
                $event->getEnvelope(),
                MessengerLogEvent::Received,
                [
                    'receiver_name' => $event->getReceiverName(),
                    'time_in_queue_ms' => $uuid !== null ? $this->contextBuilder->queueLatencyMs($uuid) : null,
                ],
            ),
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => 'onReceived',
        ];
    }
}
