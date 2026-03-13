<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\EventSubscriber;

use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

final class WorkerMessageFailedEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessengerLogContextBuilder $contextBuilder,
        private readonly LoggerInterface|null $logger = null,
        private readonly string $logLevel = LogLevel::ERROR,
    ) {
    }

    public function onFailed(WorkerMessageFailedEvent $event): void
    {
        $this->contextBuilder->ensureUuidOnWorkerEvent($event);

        $throwable = $event->getThrowable();

        $this->logger?->log(
            $this->logLevel,
            'Messenger message failed.',
            $this->contextBuilder->build(
                $event->getEnvelope(),
                [
                    'receiver_name' => $event->getReceiverName(),
                    'will_retry' => $event->willRetry(),
                    'exception_class' => $throwable::class,
                    'exception_message' => $throwable->getMessage(),
                    'exception_code' => $throwable->getCode(),
                ],
            ),
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => ['onFailed', 0],
        ];
    }
}
