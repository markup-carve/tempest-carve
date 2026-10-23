<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest\Mapper;

use InvalidArgumentException;
use Stringable;
use Tempest\Mapper\Serializer;

final readonly class CarveSerializer implements Serializer
{
    /**
     * @throws \InvalidArgumentException
     */
    public function serialize(mixed $input): string
    {
        return match (true) {
            $input instanceof CarveContent => $input->source,
            is_string($input) => $input,
            $input instanceof Stringable => (string)$input,
            default => throw new InvalidArgumentException('A Carve property must contain string source.'),
        };
    }
}
