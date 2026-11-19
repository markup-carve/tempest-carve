<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest;

use InvalidArgumentException;
use MarkupCarve\Carve2\Extension\ExtensionDefinition;
use MarkupCarve\Carve2\Extension\ExtensionProvider;
use Tempest\Cache\Cache;
use Tempest\Container\Container;
use Tempest\Container\Initializer;
use Tempest\Container\Singleton;

final class CarveInitializer implements Initializer
{
    #[Singleton]
    public function initialize(Container $container): CarveRenderer
    {
        $config = $container->get(CarveConfig::class);
        $cache = $config->cacheEnabled ? $container->get(Cache::class, $config->cacheStore) : null;
        [$extensions, $keys] = $this->resolveExtensions($container, $config->extensions);
        $resolver = $config->includeResolver === null ? null : $container->get($config->includeResolver);
        $named = [];
        foreach ($config->namedRenderers as $name => $namedConfig) {
            if ($namedConfig->namedRenderers !== []) {
                throw new InvalidArgumentException('Nested named Carve renderers are not supported.');
            }
            [$namedExtensions, $namedKeys] = $this->resolveExtensions($container, $namedConfig->extensions);
            $namedCache = $namedConfig->cacheEnabled ? $container->get(Cache::class, $namedConfig->cacheStore) : null;
            $namedResolver = $namedConfig->includeResolver === null ? null : $container->get($namedConfig->includeResolver);
            $named[$name] = new CarveRenderer($namedConfig, $namedCache, $namedExtensions, includeResolver: $namedResolver, extensionKeys: $namedKeys);
        }

        return new CarveRenderer($config, $cache, $extensions, $named, $resolver, $keys);
    }

    /**
     * @param \Tempest\Container\Container $container
     * @param list<\MarkupCarve\Carve2\Extension\ExtensionDefinition|\MarkupCarve\Carve2\Extension\ExtensionProvider|string|array<string, mixed>> $entries
     *
     * @throws \InvalidArgumentException
     *
     * @return array{list<\MarkupCarve\Carve2\Extension\ExtensionDefinition|\MarkupCarve\Carve2\Extension\ExtensionProvider>, array<int, string>}
     */
    private function resolveExtensions(Container $container, array $entries): array
    {
        $factory = new ExtensionFactory();
        $extensions = [];
        $keys = [];
        foreach ($entries as $index => $entry) {
            if ($entry instanceof ExtensionDefinition || $entry instanceof ExtensionProvider) {
                $extensions[] = $entry;
            } elseif (is_string($entry) && class_exists($entry)) {
                $resolved = $container->get($entry);
                if (!$resolved instanceof ExtensionDefinition && !$resolved instanceof ExtensionProvider) {
                    throw new InvalidArgumentException(sprintf('Configured Carve2 extension %s must be a definition or provider.', $entry));
                }
                $extensions[] = $resolved;
            } else {
                $extensions[] = $factory->create($entry);
                $fingerprint = $factory->fingerprint($entry);
                if ($fingerprint !== null) {
                    $keys[$index] = $fingerprint;
                }
            }
        }

        return [$extensions, $keys];
    }
}
