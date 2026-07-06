<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\EventSubscriber;

use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use C10k\MessengerLoggingBundle\Logging\MessengerLogEvent;
use C10k\MessengerLoggingBundle\Logging\ProcessingDurationTracker;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

final class WorkerMessageHandledEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessengerLogContextBuilder $contextBuilder,
        private readonly ProcessingDurationTracker $processingDurationTracker,
        private readonly LoggerInterface|null $logger = null,
        private readonly string $logLevel = LogLevel::INFO,
    ) {
    }

    public function onHandled(WorkerMessageHandledEvent $event): void
    {
        $this->contextBuilder->ensureUuidOnWorkerEvent($event);
        $uuid = $this->contextBuilder->uuid($event->getEnvelope());

        $this->logger?->log(
            $this->logLevel,
            'Messenger message handled.',
            $this->contextBuilder->build(
                $event->getEnvelope(),
                MessengerLogEvent::Handled,
                [
                    'receiver_name' => $event->getReceiverName(),
                    'handling_duration_ms' => $uuid !== null ? $this->processingDurationTracker->stopMs($uuid) : null,
                ],
            ),
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageHandledEvent::class => 'onHandled',
        ];
    }
}
