<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest;

use MarkupCarve\Carve\Profile;
use MarkupCarve\Carve\Renderer\SmartTypographyMode;
use MarkupCarve\Carve\Renderer\SoftBreakMode;
use Tempest\DateTime\Duration;

final readonly class CarveConfig
{
    /**
     * @param \MarkupCarve\Tempest\CarveProfile|\MarkupCarve\Carve\Profile|null $profile
     * @param bool $sourceLines
     * @param \MarkupCarve\Carve\Renderer\SmartTypographyMode $smartTypography
     * @param \MarkupCarve\Carve\Renderer\SoftBreakMode $softBreakMode
     * @param list<class-string<\MarkupCarve\Carve\Extension\ExtensionInterface>> $extensions
     * @param string $cacheKeySalt
     * @param string|null $cacheStore
     * @param \Tempest\DateTime\Duration|null $cacheExpiration
     * @param bool $cacheEnabled
     */
    public function __construct(
        public CarveProfile|Profile|null $profile = null,
        public SoftBreakMode $softBreakMode = SoftBreakMode::Newline,
        public SmartTypographyMode $smartTypography = SmartTypographyMode::Glyph,
        public bool $sourceLines = false,
        public array $extensions = [],
        public bool $cacheEnabled = false,
        public ?Duration $cacheExpiration = null,
        public ?string $cacheStore = null,
        public string $cacheKeySalt = '',
    ) {
    }

    public function resolveProfile(): ?Profile
    {
        return $this->profile instanceof CarveProfile
            ? $this->profile->create()
            : $this->profile;
    }
}
