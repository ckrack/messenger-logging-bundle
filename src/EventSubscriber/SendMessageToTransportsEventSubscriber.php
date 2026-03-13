<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\EventSubscriber;

use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;

use function array_keys;

final class SendMessageToTransportsEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessengerLogContextBuilder $contextBuilder,
        private readonly LoggerInterface|null $logger = null,
        private readonly string $logLevel = LogLevel::INFO,
    ) {
    }

    public function onQueued(SendMessageToTransportsEvent $event): void
    {
        $envelope = $this->contextBuilder->withUuid($event->getEnvelope());
        $event->setEnvelope($envelope);

        $this->logger?->log(
            $this->logLevel,
            'Messenger message queued.',
            $this->contextBuilder->build(
                $envelope,
                [
                    'sender_names' => array_keys($event->getSenders()),
                ],
            ),
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SendMessageToTransportsEvent::class => 'onQueued',
        ];
    }
}
