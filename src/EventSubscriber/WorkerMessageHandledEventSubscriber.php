<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\EventSubscriber;

use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

final class WorkerMessageHandledEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessengerLogContextBuilder $contextBuilder,
        private readonly LoggerInterface|null $logger = null,
        private readonly string $logLevel = LogLevel::INFO,
    ) {
    }

    public function onHandled(WorkerMessageHandledEvent $event): void
    {
        $this->contextBuilder->ensureUuidOnWorkerEvent($event);

        $this->logger?->log(
            $this->logLevel,
            'Messenger message handled.',
            $this->contextBuilder->build(
                $event->getEnvelope(),
                [
                    'receiver_name' => $event->getReceiverName(),
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
