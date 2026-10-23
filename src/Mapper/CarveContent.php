<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest\Mapper;

use MarkupCarve\Tempest\CarveRenderer;
use Stringable;
use Tempest\Mapper\CastWith;
use Tempest\Mapper\SerializeWith;
use Tempest\Validation\SkipValidation;

/**
 * Carve source stored in a model property, rendered on demand.
 *
 *     final class Post
 *     {
 *         public CarveContent $body;
 *     }
 *
 *     {!! $post->body !!} // rendered HTML
 *     $post->body->source // the stored markup
 *     $post->body->html('comment') // through a named renderer
 *
 * The database column holds the plain source string.
 */
#[CastWith(CarveCaster::class)]
#[SerializeWith(CarveSerializer::class)]
final class CarveContent implements Stringable
{
    /**
     * @var array<string, string>
     */
    private array $rendered = [];

    public function __construct(
        // A model property of this type is validated as a whole; its source is not a nested field.
        #[SkipValidation]
        public readonly string $source,
        private readonly ?CarveRenderer $renderer = null,
    ) {
    }

    public function html(?string $renderer = null): string
    {
        $key = $renderer ?? '';
        if (!isset($this->rendered[$key])) {
            $base = $this->renderer ?? CarveRenderer::safe();
            $this->rendered[$key] = ($renderer === null ? $base : $base->named($renderer))->render($this->source);
        }

        return $this->rendered[$key];
    }

    public function __toString(): string
    {
        return $this->html();
    }
}
