<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Closure;
use MarkupCarve\Carve2\Extension\ExtensionProvider;

final class NonSerializableCarve2Extension implements ExtensionProvider
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
