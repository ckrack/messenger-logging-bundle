<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\DependencyInjection;

use C10k\MessengerLoggingBundle\EventSubscriber\SendMessageToTransportsEventSubscriber;
use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageFailedEventSubscriber;
use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageHandledEventSubscriber;
use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageReceivedEventSubscriber;
use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageRetriedEventSubscriber;
use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageSkipEventSubscriber;
use C10k\MessengerLoggingBundle\Logging\StampNormalizerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Messenger\Event\WorkerMessageSkipEvent;

final class C10kMessengerLoggingExtension extends Extension
{
    /** @param array<int, array<string, mixed>> $configs */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $enabled = (bool) $config['enabled'];
        /** @var string|null $logChannel */
        $logChannel = is_string($config['log_channel']) ? $config['log_channel'] : null;
        /** @var array<string, string> $logLevels */
        $logLevels = $config['log_levels'];
        /** @var array<class-string, class-string<StampNormalizerInterface>> $stampNormalizers */
        $stampNormalizers = $config['stamp_normalizers'] ?? [];

        $container->registerForAutoconfiguration(StampNormalizerInterface::class)
            ->addTag(StampNormalizerInterface::SERVICE_TAG);

        $container->setParameter('c10k_messenger_logging.enabled', $enabled);
        $container->setParameter('c10k_messenger_logging.log_channel', $logChannel);
        $container->setParameter('c10k_messenger_logging.stamp_normalizers', $stampNormalizers);

        foreach ($logLevels as $event => $logLevel) {
            $container->setParameter(
                'c10k_messenger_logging.log_levels.'.$event,
                $logLevel,
            );
        }

        if ($enabled !== true) {
            return;
        }

        $loader = new PhpFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config'),
        );

        $loader->load('services.php');

        foreach ($stampNormalizers as $normalizerClass) {
            if ($container->hasDefinition($normalizerClass) || $container->hasAlias($normalizerClass)) {
                continue;
            }

            $container
                ->register($normalizerClass, $normalizerClass)
                ->setAutowired(true)
                ->setAutoconfigured(false);
        }

        if (!is_string($logChannel)) {
            return;
        }

        foreach (self::subscriberServiceIds() as $subscriberServiceId) {
            $container
                ->getDefinition($subscriberServiceId)
                ->addTag('monolog.logger', ['channel' => $logChannel]);
        }
    }

    /**
     * @return list<class-string>
     */
    private static function subscriberServiceIds(): array
    {
        $subscriberServiceIds = [
            SendMessageToTransportsEventSubscriber::class,
            WorkerMessageReceivedEventSubscriber::class,
            WorkerMessageHandledEventSubscriber::class,
            WorkerMessageFailedEventSubscriber::class,
            WorkerMessageRetriedEventSubscriber::class,
        ];

        if (class_exists(WorkerMessageSkipEvent::class)) {
            $subscriberServiceIds[] = WorkerMessageSkipEventSubscriber::class;
        }

        return $subscriberServiceIds;
    }
}
