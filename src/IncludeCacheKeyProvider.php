<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest;

interface IncludeCacheKeyProvider
{
    /**
     * Return a value that identifies this resolver's scope and changes whenever
     * content available through it changes. The renderer calls this once with
     * an empty dependency list before expansion, then with discovered
     * dependencies afterwards, and skips caching if the values differ.
     *
     * @param list<array{target: string, resolved: bool}> $dependencies
     */
    public function includeCacheKey(array $dependencies): string;
}
