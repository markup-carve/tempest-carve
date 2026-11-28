<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest;

use Tempest\DateTime\Duration;

final readonly class CarveConfig
{
    /**
     * @param list<\MarkupCarve\Carve\Extension\ExtensionDefinition|\MarkupCarve\Carve\Extension\ExtensionProvider|string|array<string, mixed>> $extensions
     * @param list<\MarkupCarve\Carve\Output\OutputWriter> $writers
     * @param string $cacheKeySalt
     * @param string|null $cacheStore
     * @param \Tempest\DateTime\Duration|null $cacheExpiration
     * @param bool $cacheEnabled
     * @param \MarkupCarve\Tempest\CarveProfile|string $profile
     * @param bool $smartTypography
     * @param string $softBreakMode
     * @param bool $sourceLines
     * @param string $headingIds
     * @param string $mode
     * @param bool $sections
     * @param bool $rawHtml
     * @param array<string, mixed> $urlPolicy
     * @param array<string, self> $namedRenderers
     * @param class-string<\MarkupCarve\Carve\Include\IncludeResolver>|null $includeResolver
     * @param array<string, 'standard'|\Closure|array{standard: array<string, string>}> $renderers
     */
    public function __construct(
        public CarveProfile|string $profile = CarveProfile::Full,
        public bool $smartTypography = true,
        public bool $sourceLines = false,
        public string $headingIds = 'keep-case',
        public string $mode = 'interactive',
        public bool $sections = true,
        public bool $rawHtml = false,
        public array $urlPolicy = [],
        public array $extensions = [],
        public array $writers = [],
        public bool $cacheEnabled = false,
        public ?Duration $cacheExpiration = null,
        public ?string $cacheStore = null,
        public string $cacheKeySalt = '',
        public array $namedRenderers = [],
        public ?string $includeResolver = null,
        public string $softBreakMode = 'newline',
        public array $renderers = [],
    ) {
    }

    public function profileName(): string
    {
        return $this->profile instanceof CarveProfile ? $this->profile->value : $this->profile;
    }
}
