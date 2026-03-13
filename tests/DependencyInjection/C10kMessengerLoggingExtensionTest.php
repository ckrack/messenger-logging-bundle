<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Tests\DependencyInjection;

use C10k\MessengerLoggingBundle\DependencyInjection\Configuration;
use C10k\MessengerLoggingBundle\DependencyInjection\C10kMessengerLoggingExtension;
use C10k\MessengerLoggingBundle\EventSubscriber\SendMessageToTransportsEventSubscriber;
use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageFailedEventSubscriber;
use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageHandledEventSubscriber;
use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageReceivedEventSubscriber;
use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageRetriedEventSubscriber;
use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageSkipEventSubscriber;
use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Messenger\Event\WorkerMessageSkipEvent;

#[CoversClass(C10kMessengerLoggingExtension::class)]
#[CoversClass(Configuration::class)]
final class C10kMessengerLoggingExtensionTest extends TestCase
{
    public function testItLoadsServicesWhenEnabled(): void
    {
        $container = new ContainerBuilder();
        $extension = new C10kMessengerLoggingExtension();

        $extension->load([], $container);

        self::assertTrue($container->hasParameter('c10k_messenger_logging.enabled'));
        self::assertTrue($container->getParameter('c10k_messenger_logging.enabled'));
        self::assertNull($container->getParameter('c10k_messenger_logging.log_channel'));
        self::assertTrue($container->hasDefinition(MessengerLogContextBuilder::class));
        self::assertTrue($container->hasDefinition(SendMessageToTransportsEventSubscriber::class));
        self::assertTrue($container->hasDefinition(WorkerMessageReceivedEventSubscriber::class));
        self::assertTrue($container->hasDefinition(WorkerMessageHandledEventSubscriber::class));
        self::assertTrue($container->hasDefinition(WorkerMessageFailedEventSubscriber::class));
        self::assertTrue($container->hasDefinition(WorkerMessageRetriedEventSubscriber::class));
        self::assertSame(
            class_exists(WorkerMessageSkipEvent::class),
            $container->hasDefinition(WorkerMessageSkipEventSubscriber::class),
        );
        self::assertSame('info', $container->getParameter('c10k_messenger_logging.log_levels.queued'));
        self::assertSame('error', $container->getParameter('c10k_messenger_logging.log_levels.failed'));
        self::assertSame([], $container->getDefinition(SendMessageToTransportsEventSubscriber::class)->getTag('monolog.logger'));
    }

    public function testItSkipsServicesWhenDisabled(): void
    {
        $container = new ContainerBuilder();
        $extension = new C10kMessengerLoggingExtension();

        $extension->load([['enabled' => false]], $container);

        self::assertFalse($container->getParameter('c10k_messenger_logging.enabled'));
    }

    public function testItLoadsCustomLogLevels(): void
    {
        $container = new ContainerBuilder();
        $extension = new C10kMessengerLoggingExtension();

        $extension->load([['log_levels' => ['queued' => 'debug', 'failed' => 'info']]], $container);

        self::assertSame('debug', $container->getParameter('c10k_messenger_logging.log_levels.queued'));
        self::assertSame('info', $container->getParameter('c10k_messenger_logging.log_levels.failed'));
    }

    public function testItAddsConfiguredLogChannelToAllSubscribers(): void
    {
        $container = new ContainerBuilder();
        $extension = new C10kMessengerLoggingExtension();

        $extension->load([['log_channel' => 'messenger']], $container);

        self::assertSame('messenger', $container->getParameter('c10k_messenger_logging.log_channel'));

        foreach ($this->expectedSubscriberServiceIds() as $subscriberServiceId) {
            self::assertSame(
                [['channel' => 'messenger']],
                $container->getDefinition($subscriberServiceId)->getTag('monolog.logger'),
            );
        }
    }

    /** @return list<class-string> */
    private function expectedSubscriberServiceIds(): array
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
