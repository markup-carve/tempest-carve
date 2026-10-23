<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest\Mapper;

use InvalidArgumentException;
use MarkupCarve\Tempest\CarveRenderer;
use Stringable;
use Tempest\Mapper\Caster;

final readonly class CarveCaster implements Caster
{
    public function __construct(private CarveRenderer $renderer)
    {
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function cast(mixed $input): ?CarveContent
    {
        return match (true) {
            $input === null => null,
            $input instanceof CarveContent => $input,
            is_string($input), $input instanceof Stringable => new CarveContent((string)$input, $this->renderer),
            default => throw new InvalidArgumentException('A Carve property must contain string source or null.'),
        };
    }
}
