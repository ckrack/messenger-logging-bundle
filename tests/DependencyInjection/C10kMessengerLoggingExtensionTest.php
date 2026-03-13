<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Tests\DependencyInjection;

use C10k\MessengerLoggingBundle\C10kMessengerLoggingBundle;
use C10k\MessengerLoggingBundle\DependencyInjection\Configuration;
use C10k\MessengerLoggingBundle\DependencyInjection\C10kMessengerLoggingExtension;
use C10k\MessengerLoggingBundle\EventSubscriber\SendMessageToTransportsEventSubscriber;
use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageFailedEventSubscriber;
use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageHandledEventSubscriber;
use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageReceivedEventSubscriber;
use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageRetriedEventSubscriber;
use C10k\MessengerLoggingBundle\EventSubscriber\WorkerMessageSkipEventSubscriber;
use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use C10k\MessengerLoggingBundle\Tests\Fixtures\ConfiguredBusNameStampNormalizer;
use C10k\MessengerLoggingBundle\Tests\Fixtures\CustomStamp;
use C10k\MessengerLoggingBundle\Tests\Fixtures\CustomStampNormalizer;
use C10k\MessengerLoggingBundle\Tests\Fixtures\DummyMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageSkipEvent;
use Symfony\Component\Messenger\Stamp\BusNameStamp;

#[CoversClass(C10kMessengerLoggingBundle::class)]
#[CoversClass(C10kMessengerLoggingExtension::class)]
#[CoversClass(Configuration::class)]
final class C10kMessengerLoggingExtensionTest extends TestCase
{
    public function testItLoadsServicesWhenEnabled(): void
    {
        $container = new ContainerBuilder();
        $extension = new C10kMessengerLoggingExtension();

        $extension->load([], $container);

        self::assertTrue($container->hasParameter('ckrack_messenger_logging.enabled'));
        self::assertTrue($container->getParameter('ckrack_messenger_logging.enabled'));
        self::assertNull($container->getParameter('ckrack_messenger_logging.log_channel'));
        self::assertTrue($container->hasDefinition(MessengerLogContextBuilder::class));
        self::assertSame([], $container->getParameter('ckrack_messenger_logging.stamp_normalizers'));
        self::assertTrue($container->hasDefinition(SendMessageToTransportsEventSubscriber::class));
        self::assertTrue($container->hasDefinition(WorkerMessageReceivedEventSubscriber::class));
        self::assertTrue($container->hasDefinition(WorkerMessageHandledEventSubscriber::class));
        self::assertTrue($container->hasDefinition(WorkerMessageFailedEventSubscriber::class));
        self::assertTrue($container->hasDefinition(WorkerMessageRetriedEventSubscriber::class));
        self::assertSame(
            class_exists(WorkerMessageSkipEvent::class),
            $container->hasDefinition(WorkerMessageSkipEventSubscriber::class),
        );
        self::assertSame('info', $container->getParameter('ckrack_messenger_logging.log_levels.queued'));
        self::assertSame('error', $container->getParameter('ckrack_messenger_logging.log_levels.failed'));
        self::assertSame([], $container->getDefinition(SendMessageToTransportsEventSubscriber::class)->getTag('monolog.logger'));
    }

    public function testItSkipsServicesWhenDisabled(): void
    {
        $container = new ContainerBuilder();
        $extension = new C10kMessengerLoggingExtension();

        $extension->load([['enabled' => false]], $container);

        self::assertFalse($container->getParameter('ckrack_messenger_logging.enabled'));
    }

    public function testItLoadsCustomLogLevels(): void
    {
        $container = new ContainerBuilder();
        $extension = new C10kMessengerLoggingExtension();

        $extension->load([['log_levels' => ['queued' => 'debug', 'failed' => 'info']]], $container);

        self::assertSame('debug', $container->getParameter('ckrack_messenger_logging.log_levels.queued'));
        self::assertSame('info', $container->getParameter('ckrack_messenger_logging.log_levels.failed'));
    }

    public function testItLoadsCustomStampNormalizerConfiguration(): void
    {
        $container = new ContainerBuilder();
        $extension = new C10kMessengerLoggingExtension();

        $extension->load(
            [
                [
                    'stamp_normalizers' => [
                        'App\\Messenger\\CustomStamp' => 'App\\Messenger\\Logging\\CustomStampNormalizer',
                    ],
                ],
            ],
            $container,
        );

        self::assertSame(
            ['App\\Messenger\\CustomStamp' => 'App\\Messenger\\Logging\\CustomStampNormalizer'],
            $container->getParameter('ckrack_messenger_logging.stamp_normalizers'),
        );
    }

    public function testItAddsConfiguredLogChannelToAllSubscribers(): void
    {
        $container = new ContainerBuilder();
        $extension = new C10kMessengerLoggingExtension();

        $extension->load([['log_channel' => 'messenger']], $container);

        self::assertSame('messenger', $container->getParameter('ckrack_messenger_logging.log_channel'));

        foreach ($this->expectedSubscriberServiceIds() as $subscriberServiceId) {
            self::assertSame(
                [['channel' => 'messenger']],
                $container->getDefinition($subscriberServiceId)->getTag('monolog.logger'),
            );
        }
    }

    public function testItDiscoversTaggedStampNormalizers(): void
    {
        $container = new ContainerBuilder();
        $bundle = new C10kMessengerLoggingBundle();
        $extension = new C10kMessengerLoggingExtension();

        $bundle->build($container);
        $extension->load([], $container);

        $container
            ->register(CustomStampNormalizer::class, CustomStampNormalizer::class)
            ->setAutowired(true)
            ->setAutoconfigured(true);
        $container->getDefinition(MessengerLogContextBuilder::class)->setPublic(true);
        $container->compile();

        $builder = $container->get(MessengerLogContextBuilder::class);
        self::assertInstanceOf(MessengerLogContextBuilder::class, $builder);

        $context = $builder->build(
            new Envelope(
                new DummyMessage('message-1'),
                [new CustomStamp('safe', ['token' => 'secret'])],
            ),
        );
        /** @var list<array{class: string, context: array<string, mixed>}> $stamps */
        $stamps = $context['stamps'];

        self::assertSame(
            ['safe_value' => 'safe'],
            $this->stampContext($stamps, CustomStamp::class),
        );
    }

    public function testConfiguredStampNormalizerMapIsApplied(): void
    {
        $container = new ContainerBuilder();
        $bundle = new C10kMessengerLoggingBundle();
        $extension = new C10kMessengerLoggingExtension();

        $bundle->build($container);
        $extension->load(
            [
                [
                    'stamp_normalizers' => [
                        BusNameStamp::class => ConfiguredBusNameStampNormalizer::class,
                    ],
                ],
            ],
            $container,
        );

        $container->getDefinition(MessengerLogContextBuilder::class)->setPublic(true);
        $container->compile();

        $builder = $container->get(MessengerLogContextBuilder::class);
        self::assertInstanceOf(MessengerLogContextBuilder::class, $builder);

        $context = $builder->build(
            new Envelope(
                new DummyMessage('message-1'),
                [new BusNameStamp('command.bus')],
            ),
        );
        /** @var list<array{class: string, context: array<string, mixed>}> $stamps */
        $stamps = $context['stamps'];

        self::assertSame(
            ['configured_bus_name' => 'configured:command.bus'],
            $this->stampContext($stamps, BusNameStamp::class),
        );
    }

    /**
     * @param list<array{class: string, context: array<string, mixed>}> $stamps
     *
     * @return array<string, mixed>
     */
    private function stampContext(array $stamps, string $stampClass): array
    {
        foreach ($stamps as $stamp) {
            if ($stamp['class'] === $stampClass) {
                return $stamp['context'];
            }
        }

        self::fail('Expected stamp not found: '.$stampClass);
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
