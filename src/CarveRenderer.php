<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Exception\ParseWarning;
use MarkupCarve\Carve\Extension\ExtensionInterface;
use MarkupCarve\Carve\ProfileViolation;
use MarkupCarve\Carve\Renderer\AnsiRenderer;
use MarkupCarve\Carve\Renderer\MarkdownRenderer;
use MarkupCarve\Carve\Renderer\PlainTextRenderer;
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
     * @param \MarkupCarve\Tempest\CarveConfig $config
     * @param \Tempest\Cache\Cache|null $cache
     * @param iterable<\MarkupCarve\Carve\Extension\ExtensionInterface> $extensions
     */
    public function __construct(
        private readonly CarveConfig $config = new CarveConfig(),
        private readonly ?Cache $cache = null,
        iterable $extensions = [],
    ) {
        $this->extensions = array_values([...$extensions]);
        $this->configurationFingerprint = $this->configurationFingerprint();
    }

    /**
     * Construct a safe-by-default renderer outside the Tempest container.
     *
     * @param \MarkupCarve\Tempest\CarveConfig|null $config
     * @param \Tempest\Cache\Cache|null $cache
     * @param iterable<\MarkupCarve\Carve\Extension\ExtensionInterface> $extensions
     */
    public static function safe(
        ?CarveConfig $config = null,
        ?Cache $cache = null,
        iterable $extensions = [],
    ): self {
        return new self($config ?? new CarveConfig(), $cache, $extensions);
    }

    public function render(string $source): string
    {
        return $this->renderHtml($source);
    }

    public function renderHtml(string $source): string
    {
        return $this->renderOutput(OutputFormat::Html, $source);
    }

    public function renderMarkdown(string $source): string
    {
        return $this->renderOutput(OutputFormat::Markdown, $source);
    }

    public function renderPlainText(string $source): string
    {
        return $this->renderOutput(OutputFormat::Plain, $source);
    }

    public function renderAnsi(string $source): string
    {
        return $this->renderOutput(OutputFormat::Ansi, $source);
    }

    public function renderWithReport(
        string $source,
        bool $strictLosses = false,
        int $maxRenderLosses = 100,
    ): RenderReport {
        $converter = $this->converter(OutputFormat::Report);
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

    private function renderOutput(OutputFormat $output, string $source): string
    {
        $render = fn (): string => $this->converter($output)->convert($source);

        if (!$this->config->cacheEnabled || $this->cache === null) {
            return $render();
        }

        $key = $this->cacheKey($output, $source);
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

    private function converter(OutputFormat $output): CarveConverter
    {
        if (isset($this->converters[$output->value])) {
            return $this->converters[$output->value];
        }

        $renderer = match ($output) {
            OutputFormat::Html, OutputFormat::Report => null,
            OutputFormat::Markdown => new MarkdownRenderer(),
            OutputFormat::Plain => new PlainTextRenderer(),
            OutputFormat::Ansi => new AnsiRenderer(),
        };

        $converter = $this->createConverter($renderer, $output === OutputFormat::Report);
        $converter->addExtensions($this->cloneExtensions());
        $this->converters[$output->value] = $converter;

        return $converter;
    }

    private function createConverter(
        MarkdownRenderer|PlainTextRenderer|AnsiRenderer|null $renderer,
        bool $warnings,
    ): CarveConverter {
        if ($renderer === null) {
            return new CarveConverter(
                warnings: $warnings,
                safeMode: true,
                profile: $this->config->resolveProfile(),
                softBreakMode: $this->config->softBreakMode,
                smartTypography: $this->config->smartTypography,
                sourceLines: $this->config->sourceLines,
            );
        }

        $renderer->setSoftBreakMode($this->config->softBreakMode);

        return new CarveConverter(
            warnings: $warnings,
            profile: $this->config->resolveProfile(),
            smartTypography: $this->config->smartTypography,
            renderer: $renderer,
            sourceLines: $this->config->sourceLines,
        );
    }

    private function cacheKey(OutputFormat $output, string $source): string
    {
        $configuration = json_encode([
            'schema' => self::CACHE_SCHEMA,
            'carve' => CarveConverter::LIB_VERSION,
            'output' => $output->value,
            'configuration' => $this->configurationFingerprint,
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
