<?php

declare(strict_types=1);

use C10k\MessengerLoggingBundle\EventSubscriber\SendMessageToTransportsEventSubscriber;
use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageFailedEventSubscriber;
use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageHandledEventSubscriber;
use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageReceivedEventSubscriber;
use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageRetriedEventSubscriber;
use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use C10k\MessengerLoggingBundle\Logging\ProcessingDurationTracker;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service_locator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(Clock::class)
        ->arg('$clock', null);
    $services->alias(ClockInterface::class, Clock::class);
    $services->set(MessengerLogContextBuilder::class)
        ->arg('$stampNormalizers', service_locator([]));
    $services->set(ProcessingDurationTracker::class)
        ->arg('$clock', service(ClockInterface::class));
    $services->load(
        'C10k\\MessengerLoggingBundle\\Logging\\StampNormalizer\\',
        __DIR__.'/../../Logging/StampNormalizer/*Normalizer.php',
    );
    $services->set(SendMessageToTransportsEventSubscriber::class)
        ->arg('$logger', service('logger')->nullOnInvalid())
        ->arg('$logLevel', param('ckrack_messenger_logging.log_levels.queued'));
    $services->set(WorkerMessageReceivedEventSubscriber::class)
        ->arg('$logger', service('logger')->nullOnInvalid())
        ->arg('$logLevel', param('ckrack_messenger_logging.log_levels.received'))
        ->arg('$processingDurationTracker', service(ProcessingDurationTracker::class));
    $services->set(WorkerMessageHandledEventSubscriber::class)
        ->arg('$logger', service('logger')->nullOnInvalid())
        ->arg('$logLevel', param('ckrack_messenger_logging.log_levels.handled'))
        ->arg('$processingDurationTracker', service(ProcessingDurationTracker::class));
    $services->set(WorkerMessageFailedEventSubscriber::class)
        ->arg('$logger', service('logger')->nullOnInvalid())
        ->arg('$logLevel', param('ckrack_messenger_logging.log_levels.failed'))
        ->arg('$processingDurationTracker', service(ProcessingDurationTracker::class));
    $services->set(WorkerMessageRetriedEventSubscriber::class)
        ->arg('$logger', service('logger')->nullOnInvalid())
        ->arg('$logLevel', param('ckrack_messenger_logging.log_levels.retry_scheduled'));
};
