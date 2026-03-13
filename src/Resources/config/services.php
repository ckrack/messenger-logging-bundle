<?php

declare(strict_types=1);

use C10k\MessengerLoggingBundle\EventSubscriber\SendMessageToTransportsEventSubscriber;
use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageFailedEventSubscriber;
use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageHandledEventSubscriber;
use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageReceivedEventSubscriber;
use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageRetriedEventSubscriber;
use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageSkipEventSubscriber;
use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Messenger\Event\WorkerMessageSkipEvent;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(MessengerLogContextBuilder::class);
    $services->set(SendMessageToTransportsEventSubscriber::class)
        ->arg('$logger', service('logger')->nullOnInvalid())
        ->arg('$logLevel', param('c10k_messenger_logging.log_levels.queued'));
    $services->set(WorkerMessageReceivedEventSubscriber::class)
        ->arg('$logger', service('logger')->nullOnInvalid())
        ->arg('$logLevel', param('c10k_messenger_logging.log_levels.received'));
    $services->set(WorkerMessageHandledEventSubscriber::class)
        ->arg('$logger', service('logger')->nullOnInvalid())
        ->arg('$logLevel', param('c10k_messenger_logging.log_levels.handled'));
    $services->set(WorkerMessageFailedEventSubscriber::class)
        ->arg('$logger', service('logger')->nullOnInvalid())
        ->arg('$logLevel', param('c10k_messenger_logging.log_levels.failed'));
    $services->set(WorkerMessageRetriedEventSubscriber::class)
        ->arg('$logger', service('logger')->nullOnInvalid())
        ->arg('$logLevel', param('c10k_messenger_logging.log_levels.retried'));

    if (class_exists(WorkerMessageSkipEvent::class)) {
        $services->set(WorkerMessageSkipEventSubscriber::class)
            ->arg('$logger', service('logger')->nullOnInvalid())
            ->arg('$logLevel', param('c10k_messenger_logging.log_levels.skipped'));
    }
};
