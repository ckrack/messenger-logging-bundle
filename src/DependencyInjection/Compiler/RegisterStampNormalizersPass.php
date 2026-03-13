<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\DependencyInjection\Compiler;

use C10k\MessengerLoggingBundle\Logging\MessengerLogContextBuilder;
use C10k\MessengerLoggingBundle\Logging\StampNormalizerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Stamp\StampInterface;

use function array_values;
use function is_string;

final class RegisterStampNormalizersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(MessengerLogContextBuilder::class)) {
            return;
        }

        /** @var array<class-string<StampInterface>, class-string<StampNormalizerInterface>> $configuredNormalizers */
        $configuredNormalizers = $container->getParameter('ckrack_messenger_logging.stamp_normalizers');
        $normalizers = [];
        $registeredBy = [];

        /** @var array<string, list<array<string, mixed>>> $taggedNormalizers */
        $taggedNormalizers = $container->findTaggedServiceIds(StampNormalizerInterface::SERVICE_TAG, true);

        foreach ($taggedNormalizers as $serviceId => $tags) {
            foreach ($this->supportedStampClasses($container, $serviceId, $tags) as $stampClass) {
                if (
                    isset($registeredBy[$stampClass])
                    && $registeredBy[$stampClass] !== $serviceId
                    && !isset($configuredNormalizers[$stampClass])
                ) {
                    throw new InvalidArgumentException(sprintf(
                        'Multiple stamp normalizers are registered for "%s": "%s" and "%s". Configure an explicit mapping to resolve the ambiguity.',
                        $stampClass,
                        $registeredBy[$stampClass],
                        $serviceId,
                    ));
                }

                $normalizers[$stampClass] = new Reference($serviceId);
                $registeredBy[$stampClass] = $serviceId;
            }
        }

        foreach ($configuredNormalizers as $stampClass => $normalizerClass) {
            $this->assertStampClass($stampClass);
            $this->assertNormalizerClass($normalizerClass);

            $normalizers[$stampClass] = new Reference($normalizerClass);
        }

        $container
            ->getDefinition(MessengerLogContextBuilder::class)
            ->setArgument(
                '$stampNormalizers',
                ServiceLocatorTagPass::register($container, $normalizers, MessengerLogContextBuilder::class),
            );
    }

    /**
     * @param list<array<string, mixed>> $tags
     *
     * @return list<class-string<StampInterface>>
     */
    private function supportedStampClasses(ContainerBuilder $container, string $serviceId, array $tags): array
    {
        $definition = $container->findDefinition($serviceId);
        $class = $definition->getClass();
        $class = is_string($class) ? $container->getParameterBag()->resolveValue($class) : $class;

        if (!is_string($class) || $class === '') {
            throw new InvalidArgumentException(sprintf(
                'Stamp normalizer service "%s" must have a concrete class.',
                $serviceId,
            ));
        }

        $this->assertNormalizerClass($class);

        /** @var array<class-string<StampInterface>, class-string<StampInterface>> $uniqueStampClasses */
        $uniqueStampClasses = [];

        foreach ($tags as $tag) {
            $stampClass = isset($tag['stamp_class'])
                ? $tag['stamp_class']
                : $class::getSupportedStampClass();

            if (!is_string($stampClass)) {
                throw new InvalidArgumentException(sprintf(
                    'Stamp normalizer service "%s" must declare stamp classes as strings.',
                    $serviceId,
                ));
            }

            $this->assertStampClass($stampClass);
            $uniqueStampClasses[$stampClass] = $stampClass;
        }

        /** @var list<class-string<StampInterface>> $supportedStampClasses */
        $supportedStampClasses = array_values($uniqueStampClasses);

        return $supportedStampClasses;
    }

    private function assertStampClass(string $stampClass): void
    {
        if (!is_a($stampClass, StampInterface::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'Configured stamp class "%s" must implement "%s".',
                $stampClass,
                StampInterface::class,
            ));
        }
    }

    private function assertNormalizerClass(string $normalizerClass): void
    {
        if (!is_a($normalizerClass, StampNormalizerInterface::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'Configured normalizer class "%s" must implement "%s".',
                $normalizerClass,
                StampNormalizerInterface::class,
            ));
        }
    }
}
