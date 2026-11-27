<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest;

use MarkupCarve\Carve\Extension\ExtensionProvider;

final readonly class HeadingShiftExtension implements ExtensionProvider
{
    public function __construct(public int $shift)
    {
    }

    public function definitions(): array
    {
        return [];
    }
}
