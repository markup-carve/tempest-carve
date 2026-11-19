<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest;

use MarkupCarve\Carve2\Diagnostics\Diagnostic;

final readonly class LintFinding
{
    public function __construct(
        public string $rule,
        public string $message,
        public ?string $file,
        public int $line,
        public int $column,
        public Diagnostic $diagnostic,
    ) {
    }
}
