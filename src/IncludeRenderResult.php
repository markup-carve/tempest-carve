<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest;

final readonly class IncludeRenderResult
{
    /**
     * @param string $html
     * @param list<array<string, mixed>> $warnings
     * @param list<array{target: string, resolved: bool}> $dependencies
     * @param int|null $suppressedWarnings Null while Carve does not expose this count.
     */
    public function __construct(
        public string $html,
        public array $warnings,
        public array $dependencies,
        public ?int $suppressedWarnings,
    ) {
    }
}
