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

        $extensions = array_map(
            fn (string $extension): ExtensionInterface => $this->resolveExtension($container, $extension),
            $config->extensions,
        );

        return new CarveRenderer($config, $cache, $extensions);
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
