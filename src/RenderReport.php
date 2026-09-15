<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest;

final readonly class RenderReport
{
    /**
     * @param string $html
     * @param list<array{message: string, line: int, column: int, category: string|null, suggestion: string|null}> $warnings
     * @param list<array{nodeType: string, reason: string, reasonDescription: string|null}> $profileViolations
     * @param list<array<string, mixed>> $losses
     * @param bool $lossesTruncated
     * @param int $totalLosses
     */
    public function __construct(
        public string $html,
        public array $warnings,
        public array $profileViolations,
        public array $losses,
        public int $totalLosses,
        public bool $lossesTruncated,
    ) {
    }
}
