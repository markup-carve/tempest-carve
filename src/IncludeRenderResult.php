<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest;

final readonly class IncludeRenderResult
{
    /**
     * @param string $html
     * @param list<array{message: string, line: int, column: int, category: string|null, suggestion: string|null, file?: string|null, detail?: string|null, rule?: string|null}> $warnings
     * @param list<array{target: string, resolved: bool}> $dependencies
     * @param int $suppressedWarnings
     */
    public function __construct(
        public string $html,
        public array $warnings,
        public array $dependencies,
        public int $suppressedWarnings,
    ) {
    }
}
