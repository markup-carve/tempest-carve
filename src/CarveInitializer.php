<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest;

use MarkupCarve\Carve\CarveConverter;
use Tempest\Container\Container;
use Tempest\Container\Initializer;
use Tempest\Container\Singleton;

final class CarveInitializer implements Initializer
{
    #[Singleton]
    public function initialize(Container $container): CarveConverter
    {
        return new CarveConverter(safeMode: true);
    }
}
