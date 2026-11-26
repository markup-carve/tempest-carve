<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Closure;
use MarkupCarve\Carve\Extension\ExtensionProvider;

final class NonSerializableCarveExtension implements ExtensionProvider
{
    private Closure $callback;

    public function __construct()
    {
        $this->callback = static fn (): null => null;
    }

    public function definitions(): array
    {
        ($this->callback)();

        return [];
    }
}
