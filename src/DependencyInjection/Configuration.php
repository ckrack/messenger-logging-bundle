<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\DependencyInjection;

use Psr\Log\LogLevel;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    private const LOG_LEVELS = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
        LogLevel::WARNING,
        LogLevel::NOTICE,
        LogLevel::INFO,
        LogLevel::DEBUG,
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('c10k_messenger_logging');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->booleanNode('enabled')
                    ->defaultTrue()
                ->end()
                ->scalarNode('log_channel')
                    ->defaultNull()
                    ->cannotBeEmpty()
                    ->validate()
                        ->ifTrue(static fn (mixed $value): bool => $value !== null && !is_string($value))
                        ->thenInvalid('The "log_channel" option must be a string or null.')
                    ->end()
                ->end()
                ->arrayNode('log_levels')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('queued')
                            ->values(self::LOG_LEVELS)
                            ->defaultValue(LogLevel::INFO)
                        ->end()
                        ->enumNode('received')
                            ->values(self::LOG_LEVELS)
                            ->defaultValue(LogLevel::INFO)
                        ->end()
                        ->enumNode('handled')
                            ->values(self::LOG_LEVELS)
                            ->defaultValue(LogLevel::INFO)
                        ->end()
                        ->enumNode('failed')
                            ->values(self::LOG_LEVELS)
                            ->defaultValue(LogLevel::ERROR)
                        ->end()
                        ->enumNode('retried')
                            ->values(self::LOG_LEVELS)
                            ->defaultValue(LogLevel::WARNING)
                        ->end()
                        ->enumNode('skipped')
                            ->values(self::LOG_LEVELS)
                            ->defaultValue(LogLevel::WARNING)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('stamp_normalizers')
                    ->useAttributeAsKey('stamp_class')
                    ->scalarPrototype()
                        ->cannotBeEmpty()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
