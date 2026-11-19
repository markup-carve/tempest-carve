<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use MarkupCarve\Carve\Include\IncludeResolver;
use MarkupCarve\Tempest\IncludeCacheKeyProvider;

final class DatabaseIncludeResolver implements IncludeResolver, IncludeCacheKeyProvider
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

    public function resolve(array $request): array
    {
        if ($this->changeVersionOnResolve) {
            $this->version = '2';
        }

        $path = $request['path'];
        if (!isset($this->documents[$path])) {
            return ['error' => 'not_found'];
        }

        return ['text' => $this->documents[$path], 'id' => 'db:' . $path];
    }

    public function includeCacheKey(array $dependencies): string
    {
        return $this->version;
    }
}
