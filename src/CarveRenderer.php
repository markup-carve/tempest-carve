<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest;

use InvalidArgumentException;
use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Exception\ParseWarning;
use MarkupCarve\Carve\Extension\ExtensionInterface;
use MarkupCarve\Carve\Profile;
use MarkupCarve\Carve\ProfileViolation;
use MarkupCarve\Carve\Renderer\AnsiRenderer;
use MarkupCarve\Carve\Renderer\MarkdownRenderer;
use MarkupCarve\Carve\Renderer\PlainTextRenderer;
use MarkupCarve\Carve\Transform\IncludeDependency;
use MarkupCarve\Carve\Transform\IncludeExpander;
use MarkupCarve\Carve\Transform\IncludeResolverInterface;
use MarkupCarve\Tempest\Internal\OutputFormat;
use Tempest\Cache\Cache;
use Throwable;
use UnexpectedValueException;

final class CarveRenderer
{
    /**
     * @var string
     */
    private const CACHE_SCHEMA = '1';

    /**
     * @var list<\MarkupCarve\Carve\Extension\ExtensionInterface>
     */
    private array $extensions;

    /**
     * @var array<string, \MarkupCarve\Carve\CarveConverter>
     */
    private array $converters = [];

    private readonly string $configurationFingerprint;

    /**
     * @var array<string, self>
     */
    private array $namedRenderers;

    /**
     * @param \MarkupCarve\Tempest\CarveConfig $config
     * @param \Tempest\Cache\Cache|null $cache
     * @param iterable<\MarkupCarve\Carve\Extension\ExtensionInterface> $extensions
     * @param array<string, self> $namedRenderers
     * @param \MarkupCarve\Carve\Transform\IncludeResolverInterface|null $includeResolver
     */
    public function __construct(
        private readonly CarveConfig $config = new CarveConfig(),
        private readonly ?Cache $cache = null,
        iterable $extensions = [],
        array $namedRenderers = [],
        private readonly ?IncludeResolverInterface $includeResolver = null,
    ) {
        $this->extensions = array_values([...$extensions]);
        $this->namedRenderers = $namedRenderers;
        $this->configurationFingerprint = $this->configurationFingerprint();
    }

    /**
     * Construct a safe-by-default renderer outside the Tempest container.
     *
     * @param \MarkupCarve\Tempest\CarveConfig|null $config
     * @param \Tempest\Cache\Cache|null $cache
     * @param iterable<\MarkupCarve\Carve\Extension\ExtensionInterface> $extensions
     * @param array<string, self> $namedRenderers
     * @param \MarkupCarve\Carve\Transform\IncludeResolverInterface|null $includeResolver
     */
    public static function safe(
        ?CarveConfig $config = null,
        ?Cache $cache = null,
        iterable $extensions = [],
        array $namedRenderers = [],
        ?IncludeResolverInterface $includeResolver = null,
    ): self {
        return new self($config ?? new CarveConfig(), $cache, $extensions, $namedRenderers, $includeResolver);
    }

    public function render(string $source, CarveProfile|Profile|null $profile = null): string
    {
        return $this->renderHtml($source, $profile);
    }

    public function renderHtml(string $source, CarveProfile|Profile|null $profile = null): string
    {
        return $this->renderOutput(OutputFormat::Html, $source, $profile);
    }

    public function renderMarkdown(string $source, CarveProfile|Profile|null $profile = null): string
    {
        return $this->renderOutput(OutputFormat::Markdown, $source, $profile);
    }

    public function renderPlainText(string $source, CarveProfile|Profile|null $profile = null): string
    {
        return $this->renderOutput(OutputFormat::Plain, $source, $profile);
    }

    public function renderAnsi(string $source, CarveProfile|Profile|null $profile = null): string
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
        CarveProfile|Profile|null $profile = null,
    ): IncludeRenderResult {
        if ($this->includeResolver === null) {
            throw new InvalidArgumentException('No Carve include resolver is configured.');
        }

        $options ??= new IncludeOptions();
        $resolverKeyBefore = $this->includeResolver instanceof IncludeCacheKeyProvider
            ? $this->includeResolver->includeCacheKey([])
            : null;
        $resolvedProfile = $profile instanceof CarveProfile ? $profile->create() : $profile;
        $converter = $this->createConverter(null, true, $resolvedProfile ?? $this->config->resolveProfile());
        $converter->addExtensions($this->cloneExtensions());
        $document = $converter->parse($source);
        $expander = new IncludeExpander(
            resolver: $this->includeResolver,
            currentPath: $options->currentPath,
            depthLimit: $options->depthLimit,
            byteBudget: $options->byteBudget,
            source: $source,
            resolverCallLimit: $options->resolverCallLimit,
            warningLimit: $options->warningLimit,
            extensions: $converter->getExtensions(),
        );
        $expanded = $converter->transform($document, $expander);
        $dependencies = array_map(
            static fn (IncludeDependency $dependency): array => [
                'target' => $dependency->getTarget(),
                'resolved' => $dependency->isResolved(),
            ],
            $expander->getDependencies(),
        );
        $render = fn (): string => $converter->render($expanded);
        $html = null;
        if ($this->cache !== null && $this->config->cacheEnabled && $this->includeResolver instanceof IncludeCacheKeyProvider) {
            $resolverKeyAfter = $this->includeResolver->includeCacheKey($dependencies);
            if ($resolverKeyBefore !== $resolverKeyAfter) {
                return new IncludeRenderResult(
                    html: $render(),
                    warnings: $this->includeWarnings($converter, $expander),
                    dependencies: $dependencies,
                    suppressedWarnings: $expander->getSuppressedWarnings(),
                );
            }
            $cacheContext = json_encode([
                'schema' => self::CACHE_SCHEMA,
                'carve' => CarveConverter::LIB_VERSION,
                'configuration' => $this->configurationFingerprint,
                'profileOverride' => $profile === null ? null : ($profile instanceof CarveProfile ? $profile->value : $this->objectFingerprint($profile)),
                'resolver' => $this->includeResolver::class,
                'resolverKey' => $resolverKeyAfter,
                'dependencies' => $dependencies,
                'options' => [
                    'currentPath' => $options->currentPath,
                    'depthLimit' => $options->depthLimit,
                    'byteBudget' => $options->byteBudget,
                    'resolverCallLimit' => $options->resolverCallLimit,
                    'warningLimit' => $options->warningLimit,
                ],
            ], JSON_THROW_ON_ERROR);
            $key = 'tempest-carve.include.' . hash('sha256', $cacheContext . "\0" . $source);
            $cached = $this->cache->resolve($key, $render, $this->config->cacheExpiration);
            if (is_string($cached)) {
                $html = $cached;
            } else {
                $this->cache->remove($key);
            }
        }
        $html ??= $render();

        return new IncludeRenderResult(
            html: $html,
            warnings: $this->includeWarnings($converter, $expander),
            dependencies: $dependencies,
            suppressedWarnings: $expander->getSuppressedWarnings(),
        );
    }

    /**
     * @return list<array{message: string, line: int, column: int, category: string|null, suggestion: string|null, file?: string|null, detail?: string|null, rule?: string|null}>
     */
    private function includeWarnings(CarveConverter $converter, IncludeExpander $expander): array
    {
        return array_values(array_map(
            static fn (ParseWarning $warning): array => $warning->toArray(),
            [...$converter->getWarnings(), ...$expander->getWarnings()],
        ));
    }

    public function renderWithReport(
        string $source,
        bool $strictLosses = false,
        int $maxRenderLosses = 100,
        CarveProfile|Profile|null $profile = null,
    ): RenderReport {
        $resolvedProfile = $profile instanceof CarveProfile ? $profile->create() : $profile;
        $converter = $profile === null
            ? $this->converter(OutputFormat::Report)
            : $this->converter(OutputFormat::Report, $resolvedProfile);
        $converter->clearWarnings();
        $result = $converter->convertWithReport($source, $strictLosses, $maxRenderLosses);

        return new RenderReport(
            html: $result->value,
            warnings: array_values(array_map(
                static fn (ParseWarning $warning): array => $warning->toArray(),
                $converter->getWarnings(),
            )),
            profileViolations: array_values(array_map(
                static fn (ProfileViolation $violation): array => [
                    'nodeType' => $violation->nodeType,
                    'reason' => $violation->reason,
                    'reasonDescription' => $violation->reasonDescription,
                ],
                $converter->getProfileViolations(),
            )),
            losses: $result->losses,
            totalLosses: $result->totalLosses,
            lossesTruncated: $result->truncated,
        );
    }

    private function renderOutput(OutputFormat $output, string $source, CarveProfile|Profile|null $profile = null): string
    {
        $resolvedProfile = $profile instanceof CarveProfile ? $profile->create() : $profile;
        $converter = $profile === null ? $this->converter($output) : $this->converter($output, $resolvedProfile);
        $render = fn (): string => $converter->convert($source);

        if (!$this->config->cacheEnabled || $this->cache === null) {
            return $render();
        }

        $key = $this->cacheKey($output, $source, $profile);
        $value = $this->cache->resolve(
            $key,
            $render,
            $this->config->cacheExpiration,
        );

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

    private function converter(OutputFormat $output, ?Profile $profile = null): CarveConverter
    {
        $key = $output->value . ':' . ($profile === null ? 'configured' : $this->objectFingerprint($profile));
        if (isset($this->converters[$key])) {
            return $this->converters[$key];
        }

        $renderer = match ($output) {
            OutputFormat::Html, OutputFormat::Report => null,
            OutputFormat::Markdown => new MarkdownRenderer(),
            OutputFormat::Plain => new PlainTextRenderer(),
            OutputFormat::Ansi => new AnsiRenderer(),
        };

        $converter = $this->createConverter($renderer, $output === OutputFormat::Report, $profile ?? $this->config->resolveProfile());
        $converter->addExtensions($this->cloneExtensions());
        $this->converters[$key] = $converter;

        return $converter;
    }

    private function createConverter(
        MarkdownRenderer|PlainTextRenderer|AnsiRenderer|null $renderer,
        bool $warnings,
        ?Profile $profile,
    ): CarveConverter {
        if ($renderer === null) {
            return new CarveConverter(
                warnings: $warnings,
                safeMode: true,
                profile: $profile,
                softBreakMode: $this->config->softBreakMode,
                smartTypography: $this->config->smartTypography,
                sourceLines: $this->config->sourceLines,
            );
        }

        $renderer->setSoftBreakMode($this->config->softBreakMode);

        return new CarveConverter(
            warnings: $warnings,
            profile: $profile,
            smartTypography: $this->config->smartTypography,
            renderer: $renderer,
            sourceLines: $this->config->sourceLines,
        );
    }

    private function cacheKey(OutputFormat $output, string $source, CarveProfile|Profile|null $profile = null): string
    {
        $configuration = json_encode([
            'schema' => self::CACHE_SCHEMA,
            'carve' => CarveConverter::LIB_VERSION,
            'output' => $output->value,
            'configuration' => $this->configurationFingerprint,
            'profileOverride' => $profile === null ? null : ($profile instanceof CarveProfile ? $profile->value : $this->objectFingerprint($profile)),
        ], JSON_THROW_ON_ERROR);

        return 'tempest-carve.' . hash('sha256', $configuration . "\0" . $source);
    }

    private function configurationFingerprint(): string
    {
        $profile = $this->config->profile;
        $profileKey = $profile instanceof CarveProfile
            ? $profile->value
            : ($profile === null ? 'none' : $this->objectFingerprint($profile));
        $extensions = array_map(
            fn (ExtensionInterface $extension): string => $this->objectFingerprint($extension),
            $this->extensions,
        );

        $configuration = json_encode([
            'profile' => $profileKey,
            'softBreakMode' => $this->config->softBreakMode->value,
            'smartTypography' => $this->config->smartTypography->value,
            'sourceLines' => $this->config->sourceLines,
            'extensions' => $extensions,
            'salt' => $this->config->cacheKeySalt,
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

    /**
     * @return list<\MarkupCarve\Carve\Extension\ExtensionInterface>
     */
    private function cloneExtensions(): array
    {
        return array_map(
            static fn (ExtensionInterface $extension): ExtensionInterface => clone $extension,
            $this->extensions,
        );
    }
}
