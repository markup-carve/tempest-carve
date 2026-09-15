<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Closure;
use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Extension\ExtensionInterface;

final class NonSerializableExtension implements ExtensionInterface
{
    /**
     * @var \Closure(string): string
     */
    private Closure $transformer;

    public function __construct()
    {
        $this->transformer = static fn (string $output): string => $output;
    }

    public function register(CarveConverter $converter): void
    {
        $converter->addOutputTransformer($this->transformer);
    }
}
