<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest;

use InvalidArgumentException;
use MarkupCarve\Carve\Extension\ExtensionInterface;
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
        $cache = $config->cacheEnabled
            ? $container->get(Cache::class, $config->cacheStore)
            : null;

        [$extensions, $extensionKeys] = $this->resolveExtensions($container, $config->extensions);

        $resolver = $config->includeResolver === null
            ? null
            : $container->get($config->includeResolver);
        $named = [];
        foreach ($config->namedRenderers as $name => $namedConfig) {
            if ($namedConfig->namedRenderers !== []) {
                throw new InvalidArgumentException('Nested named Carve renderers are not supported.');
            }
            [$namedExtensions, $namedKeys] = $this->resolveExtensions($container, $namedConfig->extensions);
            $namedCache = $namedConfig->cacheEnabled
                ? $container->get(Cache::class, $namedConfig->cacheStore)
                : null;
            $namedResolver = $namedConfig->includeResolver === null
                ? null
                : $container->get($namedConfig->includeResolver);
            $named[$name] = new CarveRenderer($namedConfig, $namedCache, $namedExtensions, includeResolver: $namedResolver, extensionKeys: $namedKeys);
        }

        return new CarveRenderer($config, $cache, $extensions, $named, $resolver, $extensionKeys);
    }

    /**
     * @param \Tempest\Container\Container $container
     * @param list<string|array<string, mixed>> $entries
     *
     * @return array{0: list<\MarkupCarve\Carve\Extension\ExtensionInterface>, 1: array<int, string>}
     */
    private function resolveExtensions(Container $container, array $entries): array
    {
        $factory = new ExtensionFactory();
        $extensions = [];
        $keys = [];
        foreach ($entries as $index => $entry) {
            if (is_string($entry) && class_exists($entry)) {
                $extensions[] = $this->resolveExtension($container, $entry);

                continue;
            }
            $extensions[] = $factory->create($entry);
            $key = $factory->fingerprint($entry);
            if ($key !== null) {
                $keys[$index] = $key;
            }
        }

        return [$extensions, $keys];
    }

    /**
     * @param \Tempest\Container\Container $container
     * @param class-string $extension
     *
     * @throws \InvalidArgumentException
     */
    private function resolveExtension(Container $container, string $extension): ExtensionInterface
    {
        $resolved = $container->get($extension);
        if (!$resolved instanceof ExtensionInterface) {
            throw new InvalidArgumentException(sprintf(
                'Configured Carve extension %s must implement %s.',
                $extension,
                ExtensionInterface::class,
            ));
        }

        return $resolved;
    }
}
