<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\EventSubscriber;

use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

final class WorkerMessageReceivedEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessengerLogContextBuilder $contextBuilder,
        private readonly LoggerInterface|null $logger = null,
        private readonly string $logLevel = LogLevel::INFO,
    ) {
    }

    public function onReceived(WorkerMessageReceivedEvent $event): void
    {
        $this->contextBuilder->ensureUuidOnWorkerEvent($event);

        $this->logger?->log(
            $this->logLevel,
            'Messenger message received.',
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
            WorkerMessageReceivedEvent::class => 'onReceived',
        ];
    }
}
