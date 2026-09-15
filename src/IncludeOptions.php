<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest;

final readonly class IncludeOptions
{
    public function __construct(
        public ?string $currentPath = null,
        public int $depthLimit = 16,
        public ?int $byteBudget = null,
        public int $resolverCallLimit = 1000,
        public int $warningLimit = 100,
    ) {
    }
}
