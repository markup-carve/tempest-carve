<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest;

use Closure;
use InvalidArgumentException;
use MarkupCarve\Carve\Carve;
use MarkupCarve\Carve\Diagnostics\Diagnostic;
use MarkupCarve\Carve\Include\IncludeResolver;
use MarkupCarve\Carve\Output\WriterRegistry;
use MarkupCarve\Carve\RenderResult;
use MarkupCarve\Tempest\Internal\OutputFormat;
use Tempest\Cache\Cache;
use Throwable;
use UnexpectedValueException;

final class CarveRenderer
{
    /**
     * @var string
     */
    private const CACHE_SCHEMA = '3';

    /**
     * @var list<\MarkupCarve\Carve\Extension\ExtensionDefinition|\MarkupCarve\Carve\Extension\ExtensionProvider>
     */
    private array $extensions;

    /**
     * @var array<string, self>
     */
    private array $namedRenderers;

    /**
     * @var array<int, string>
     */
    private array $extensionKeys;

    private readonly WriterRegistry $writers;

    private readonly string $configurationFingerprint;

    /**
     * @param \MarkupCarve\Tempest\CarveConfig $config
     * @param \Tempest\Cache\Cache|null $cache
     * @param iterable<\MarkupCarve\Carve\Extension\ExtensionDefinition|\MarkupCarve\Carve\Extension\ExtensionProvider> $extensions
     * @param array<string, self> $namedRenderers
     * @param \MarkupCarve\Carve\Include\IncludeResolver|null $includeResolver
     * @param array<int, string> $extensionKeys
     */
    public function __construct(
        private readonly CarveConfig $config = new CarveConfig(),
        private readonly ?Cache $cache = null,
        iterable $extensions = [],
        array $namedRenderers = [],
        private readonly ?IncludeResolver $includeResolver = null,
        array $extensionKeys = [],
    ) {
        $this->extensions = array_values([...$extensions]);
        $this->namedRenderers = $namedRenderers;
        $this->extensionKeys = $extensionKeys;
        $this->writers = Carve::writers();
        foreach ($config->writers as $writer) {
            $this->writers->register($writer);
        }
        $this->configurationFingerprint = $this->configurationFingerprint();
    }

    /**
     * @param \MarkupCarve\Tempest\CarveConfig|null $config
     * @param \Tempest\Cache\Cache|null $cache
     * @param iterable<\MarkupCarve\Carve\Extension\ExtensionDefinition|\MarkupCarve\Carve\Extension\ExtensionProvider> $extensions
     * @param array<string, self> $namedRenderers
     * @param \MarkupCarve\Carve\Include\IncludeResolver|null $includeResolver
     */
    public static function safe(
        ?CarveConfig $config = null,
        ?Cache $cache = null,
        iterable $extensions = [],
        array $namedRenderers = [],
        ?IncludeResolver $includeResolver = null,
    ): self {
        return new self($config ?? new CarveConfig(), $cache, $extensions, $namedRenderers, $includeResolver);
    }

    public function render(string $source, CarveProfile|string|null $profile = null): string
    {
        return $this->renderHtml($source, $profile);
    }

    public function renderHtml(string $source, CarveProfile|string|null $profile = null): string
    {
        return $this->renderOutput(OutputFormat::Html, $source, $profile);
    }

    public function renderMarkdown(string $source, CarveProfile|string|null $profile = null): string
    {
        return $this->renderOutput(OutputFormat::Markdown, $source, $profile);
    }

    public function renderPlainText(string $source, CarveProfile|string|null $profile = null): string
    {
        return $this->renderOutput(OutputFormat::Plain, $source, $profile);
    }

    public function renderAnsi(string $source, CarveProfile|string|null $profile = null): string
    {
        return $this->renderOutput(OutputFormat::Ansi, $source, $profile);
    }

    public function named(string $name): self
    {
        return $this->namedRenderers[$name]
            ?? throw new InvalidArgumentException(sprintf('Unknown Carve renderer %s.', $name));
    }

    public function renderIncluded(
        string $source,
        ?IncludeOptions $options = null,
        CarveProfile|string|null $profile = null,
    ): IncludeRenderResult {
        if ($this->includeResolver === null) {
            throw new InvalidArgumentException('No Carve include resolver is configured.');
        }
        $options ??= new IncludeOptions();
        if ($options->resolverCallLimit !== 1000) {
            throw new InvalidArgumentException('Carve has no separate include resolver-call limit.');
        }
        $resolverKeyBefore = $this->includeResolver instanceof IncludeCacheKeyProvider
            ? $this->includeResolver->includeCacheKey([])
            : null;
        $includeSources = [];
        $resolver = $this->capturingResolver($this->includeResolver, $includeSources);
        $engineOptions = $this->options($profile);
        $engineOptions['includes'] = [
            'resolver' => $resolver,
            'file' => $options->currentPath,
            'depth' => $options->depthLimit,
            'maxDiagnostics' => $options->warningLimit,
        ];
        if ($options->byteBudget !== null) {
            $engineOptions['includes']['bytes'] = $options->byteBudget;
        }
        $parsed = Carve::parseWithDiagnostics($source, [
            'extensions' => $this->extensions,
            'headingIds' => $this->config->headingIds,
            'includes' => $engineOptions['includes'],
        ]);
        $dependencies = array_values(array_map(
            static fn (array $entry): array => [
                'target' => $entry['file'] ?? $entry['path'],
                'resolved' => $entry['read'],
            ],
            Carve::includeDependencies($parsed->tree),
        ));
        $render = static fn (): RenderResult => Carve::renderWithDiagnostics($parsed->tree, $engineOptions);
        $renderResult = null;
        if ($this->cache !== null && $this->config->cacheEnabled && $this->includeResolver instanceof IncludeCacheKeyProvider) {
            $resolverKeyAfter = $this->includeResolver->includeCacheKey($dependencies);
            if ($resolverKeyBefore === $resolverKeyAfter) {
                $context = json_encode([
                    self::CACHE_SCHEMA, $this->configurationFingerprint, $resolverKeyAfter,
                    $dependencies, $options, $profile instanceof CarveProfile ? $profile->value : $profile,
                ], JSON_THROW_ON_ERROR);
                $key = 'tempest-carve.include.' . hash('sha256', $context . "\0" . $source);
                $cached = $this->cache->resolve($key, $render, $this->config->cacheExpiration);
                if ($cached instanceof RenderResult) {
                    $renderResult = $cached;
                } else {
                    $this->cache->remove($key);
                }
            }
        }
        $renderResult ??= $render();

        return new IncludeRenderResult(
            $renderResult->html,
            array_values(array_map(
                fn (Diagnostic $d): array => $this->diagnosticArray($d, $source, $includeSources),
                [...$parsed->diagnostics, ...$renderResult->diagnostics],
            )),
            $dependencies,
            $parsed->suppressedDiagnostics + $renderResult->suppressedDiagnostics,
        );
    }

    public function renderWithReport(
        string $source,
        bool $strictLosses = false,
        int $maxRenderLosses = 100,
        CarveProfile|string|null $profile = null,
    ): RenderReport {
        $options = $this->options($profile);
        $result = Carve::renderWithDiagnostics($source, $options);
        $parse = [];
        $losses = [];
        foreach ($result->diagnostics as $diagnostic) {
            $row = $this->diagnosticArray($diagnostic, $source);
            if ($diagnostic->channel === 'render-loss') {
                $losses[] = $row;
            } else {
                $parse[] = $row;
            }
        }
        if ($strictLosses && $losses !== []) {
            Carve::render($source, $options + ['strictLosses' => true]);
        }

        return new RenderReport(
            $result->html,
            $parse,
            array_values(array_filter($losses, static fn (array $row): bool => is_string($row['code'] ?? null) && str_starts_with($row['code'], 'profile-'))),
            array_slice($losses, 0, max(0, $maxRenderLosses)),
            count($losses),
            count($losses) > $maxRenderLosses,
        );
    }

    /**
     * @return list<\MarkupCarve\Tempest\LintFinding>
     */
    public function lint(string $source): array
    {
        return $this->lintFindings(Carve::lint($source, [
            'extensions' => $this->extensions,
            'headingIds' => $this->config->headingIds,
        ]), $source);
    }

    /**
     * @param string $source
     * @param string|null $sourcePath
     * @param \MarkupCarve\Carve\Include\IncludeResolver|null $resolver
     *
     * @throws \InvalidArgumentException
     *
     * @return list<\MarkupCarve\Tempest\LintFinding>
     */
    public function lintIncluded(
        string $source,
        ?string $sourcePath = null,
        ?IncludeResolver $resolver = null,
    ): array {
        $resolver ??= $this->includeResolver
            ?? throw new InvalidArgumentException('No Carve include resolver is configured.');
        $includeSources = [];
        $options = [
            'extensions' => $this->extensions,
            'includes' => ['resolver' => $this->capturingResolver($resolver, $includeSources), 'file' => $sourcePath],
        ];

        return $this->lintFindings(Carve::lint($source, $options), $source, $includeSources);
    }

    public function format(string $source, bool $alignTables = false): string
    {
        return Carve::format($source, ['alignTables' => $alignTables, 'extensions' => $this->extensions]);
    }

    public function fromMarkdown(string $source): string
    {
        return Carve::importDocument($source, ['from' => 'markdown'])->source;
    }

    public function fromHtml(string $source): string
    {
        return Carve::importDocument($source, ['from' => 'html'])->source;
    }

    private function renderOutput(OutputFormat $output, string $source, CarveProfile|string|null $profile): string
    {
        $render = function () use ($output, $source, $profile): string {
            $options = $this->options($profile);
            if ($output === OutputFormat::Html) {
                return Carve::render($source, $options);
            }
            $target = $output === OutputFormat::Plain
                ? ($this->writers->has('text') ? 'text' : 'plain')
                : $output->value;
            if (!$this->writers->has($target)) {
                throw new InvalidArgumentException(sprintf('%s writer not available yet in Carve.', ucfirst($target)));
            }

            $writerOptions = $target === 'text' ? $options : [
                'smartTypography' => $this->config->smartTypography,
                'urlPolicy' => $this->config->urlPolicy,
            ];

            return Carve::writeTo($target, Carve::parse($source, [
                'extensions' => $this->extensions,
                'headingIds' => $this->config->headingIds,
            ]), $writerOptions, $this->writers);
        };
        if (!$this->config->cacheEnabled || $this->cache === null) {
            return $render();
        }
        $key = $this->cacheKey($output, $source, $profile);
        $value = $this->cache->resolve($key, $render, $this->config->cacheExpiration);
        if (is_string($value)) {
            return $value;
        }
        $this->cache->remove($key);
        $value = $this->cache->resolve($key, $render, $this->config->cacheExpiration);
        if (!is_string($value)) {
            throw new UnexpectedValueException('The configured Tempest cache did not return a string render result.');
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function options(CarveProfile|string|null $profile): array
    {
        $options = [
            'profile' => $profile instanceof CarveProfile ? $profile->value : ($profile ?? $this->config->profileName()),
            'smartTypography' => $this->config->smartTypography,
            'sourceLines' => $this->config->sourceLines,
            'headingIds' => $this->config->headingIds,
            'mode' => $this->config->mode,
            'sections' => $this->config->sections,
            'rawHtml' => $this->config->rawHtml,
            'extensions' => $this->extensions,
        ];
        if ($this->config->urlPolicy !== []) {
            $options['urlPolicy'] = $this->config->urlPolicy;
        }

        return $options;
    }

    /**
     * @param \MarkupCarve\Carve\Include\IncludeResolver $resolver
     * @param array<string, string> $sources
     */
    private function capturingResolver(IncludeResolver $resolver, array &$sources): IncludeResolver
    {
        $capture = static function (string $id, string $text) use (&$sources): void {
            $sources[$id] = $text;
        };

        return new class ($resolver, $capture) implements IncludeResolver {
            public function __construct(private readonly IncludeResolver $resolver, private readonly Closure $capture)
            {
            }

            public function resolve(array $request): array
            {
                $result = $this->resolver->resolve($request);
                if (is_string($result['id'] ?? null) && is_string($result['text'] ?? null)) {
                    ($this->capture)($result['id'], $result['text']);
                }

                return $result;
            }
        };
    }

    /**
     * @param array<\MarkupCarve\Carve\Diagnostics\Diagnostic> $diagnostics
     * @param string $source
     * @param array<string, string> $sources
     *
     * @return list<\MarkupCarve\Tempest\LintFinding>
     */
    private function lintFindings(array $diagnostics, string $source, array $sources = []): array
    {
        return array_values(array_map(function (Diagnostic $d) use ($source, $sources): LintFinding {
            $position = $this->position($d->file === null ? $source : ($sources[$d->file] ?? $source), $d->startOffset);

            return new LintFinding($d->code, $d->message, $d->file, $position[0], $position[1], $d);
        }, $diagnostics));
    }

    /**
     * @param \MarkupCarve\Carve\Diagnostics\Diagnostic $diagnostic
     * @param string $source
     * @param array<string, string> $sources
     *
     * @return array<string, mixed>
     */
    private function diagnosticArray(Diagnostic $diagnostic, string $source, array $sources = []): array
    {
        [$line, $column] = $this->position(
            $diagnostic->file === null ? $source : ($sources[$diagnostic->file] ?? $source),
            $diagnostic->startOffset,
        );
        $nodeType = null;
        if ($diagnostic->code === 'destination-denied') {
            $nodeType = 'inline';
        } elseif (
            $diagnostic->code === 'profile-construct-degraded'
            && preg_match('/: ([a-z_]+)\.$/', $diagnostic->message, $match) === 1
        ) {
            $nodeType = $match[1];
        }

        return $diagnostic->toArray() + [
            'category' => str_starts_with($diagnostic->code, 'include-') ? 'include' : 'reference',
            'line' => $line,
            'column' => $column,
            'nodeType' => $nodeType,
        ];
    }

    /**
     * @return array{int, int}
     */
    private function position(string $source, int $offset): array
    {
        if ($offset < 0) {
            return [1, 1];
        }
        $prefix = substr($source, 0, $offset);
        $line = substr_count($prefix, "\n") + 1;
        $last = strrpos($prefix, "\n");

        return [$line, $offset - ($last === false ? -1 : $last)];
    }

    private function cacheKey(OutputFormat $output, string $source, CarveProfile|string|null $profile): string
    {
        $configuration = json_encode([
            self::CACHE_SCHEMA, $output->value, $this->configurationFingerprint,
            $profile instanceof CarveProfile ? $profile->value : $profile,
        ], JSON_THROW_ON_ERROR);

        return 'tempest-carve.' . hash('sha256', $configuration . "\0" . $source);
    }

    private function configurationFingerprint(): string
    {
        $extensions = [];
        foreach ($this->extensions as $index => $extension) {
            $extensions[] = $this->extensionKeys[$index] ?? $this->objectFingerprint($extension);
        }
        $writers = [];
        foreach ($this->config->writers as $writer) {
            $writers[] = $this->objectFingerprint($writer);
        }
        $configuration = json_encode([
            $this->config->profileName(), $this->config->smartTypography, $this->config->sourceLines,
            $this->config->headingIds, $this->config->mode, $this->config->sections,
            $this->config->rawHtml, $this->config->urlPolicy, $extensions, $writers,
            $this->config->cacheKeySalt,
        ], JSON_THROW_ON_ERROR);

        return hash('sha256', $configuration);
    }

    private function objectFingerprint(object $object): string
    {
        try {
            return hash('sha256', serialize($object));
        } catch (Throwable $exception) {
            if ($this->config->cacheEnabled && $this->config->cacheKeySalt === '') {
                throw new UnexpectedValueException(sprintf(
                    'Cannot fingerprint %s for caching; configure cacheKeySalt explicitly.',
                    $object::class,
                ), previous: $exception);
            }

            return $object::class;
        }
    }
}
