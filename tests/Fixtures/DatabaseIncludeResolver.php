<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use MarkupCarve\Carve\Transform\IncludeContext;
use MarkupCarve\Carve\Transform\IncludeResolverInterface;
use MarkupCarve\Carve\Transform\ResolvedInclude;
use MarkupCarve\Tempest\IncludeCacheKeyProvider;

final class DatabaseIncludeResolver implements IncludeResolverInterface, IncludeCacheKeyProvider
{
    /**
     * @param array<string, string> $documents
     * @param string $version
     * @param bool $changeVersionOnResolve
     */
    public function __construct(
        public array $documents = ['chapter' => '# Database chapter'],
        public string $version = '1',
        public bool $changeVersionOnResolve = false,
    ) {
    }

    public function resolve(string $path, IncludeContext $context): ?ResolvedInclude
    {
        if ($this->changeVersionOnResolve) {
            $this->version = '2';
        }

        if (!isset($this->documents[$path])) {
            return null;
        }

        return new ResolvedInclude($this->documents[$path], 'db:' . $path);
    }

    public function includeCacheKey(array $dependencies): string
    {
        return $this->version;
    }
}
