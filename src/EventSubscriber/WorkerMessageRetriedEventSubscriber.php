<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\EventSubscriber;

use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use C10k\MessengerLoggingBundle\Logging\MessengerLogEvent;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageRetriedEvent;

final class WorkerMessageRetriedEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessengerLogContextBuilder $contextBuilder,
        private readonly LoggerInterface|null $logger = null,
        private readonly string $logLevel = LogLevel::WARNING,
    ) {
    }

    public function onRetried(WorkerMessageRetriedEvent $event): void
    {
        $this->contextBuilder->ensureUuidOnWorkerEvent($event);

        $this->logger?->log(
            $this->logLevel,
            'Messenger message retry scheduled.',
            $this->contextBuilder->build(
                $event->getEnvelope(),
                MessengerLogEvent::Retried,
                [
                    'receiver_name' => $event->getReceiverName(),
                ],
            ),
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageRetriedEvent::class => 'onRetried',
        ];
    }
}
