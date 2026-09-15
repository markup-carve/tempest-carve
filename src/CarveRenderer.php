<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest;

use MarkupCarve\Carve\CarveConverter;

final readonly class CarveRenderer
{
    public function __construct(private CarveConverter $converter)
    {
    }

    public static function safe(): self
    {
        return new self(new CarveConverter(safeMode: true));
    }

    public function render(string $source): string
    {
        return $this->converter->convert($source);
    }
}
