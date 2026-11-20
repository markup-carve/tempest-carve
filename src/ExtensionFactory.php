<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest;

use Closure;
use InvalidArgumentException;
use MarkupCarve\Carve\Extension\Claim;
use MarkupCarve\Carve\Extension\ExtensionDefinition;
use MarkupCarve\Carve\Extension\ExtensionProvider;
use MarkupCarve\Carve\Extension\RenderHost;
use MarkupCarve\Carve\Extension\Tabs;
use MarkupCarve\Carve\Extension\Wikilinks;
use MarkupCarve\Carve\Node;
use MarkupCarve\Tempest\Highlight\TempestHighlightExtension;

final class ExtensionFactory
{
    /**
     * @param array<string, mixed>|string $entry
     *
     * @throws \InvalidArgumentException
     */
    public function create(array|string $entry): ExtensionDefinition|ExtensionProvider
    {
        $config = is_string($entry) ? ['type' => $entry] : $entry;
        $type = $config['type'] ?? null;
        if (!is_string($type) || $type === '') {
            throw new InvalidArgumentException('A Carve extension entry needs a string "type".');
        }
        unset($config['type']);
        if ($type === 'tempest_highlight') {
            $this->only($config, [], $type);

            return new TempestHighlightExtension();
        }
        if ($type === 'tabs') {
            $this->only($config, [], $type);

            return new class implements ExtensionProvider {
                public function definitions(): array
                {
                    return Tabs::extensions(true);
                }
            };
        }
        if ($type === 'wikilinks') {
            $this->only($config, ['url_template', 'url_generator', 'css_class', 'new_window'], $type);
            $template = $config['url_template'] ?? null;
            $generator = $config['url_generator'] ?? null;
            $cssClass = $config['css_class'] ?? 'wikilink';
            $newWindow = $config['new_window'] ?? false;
            if ($template !== null && !is_string($template)) {
                throw new InvalidArgumentException('The wiki URL template must be a string.');
            }
            if ($generator !== null && !$generator instanceof Closure) {
                throw new InvalidArgumentException('The wiki URL generator must be a closure.');
            }
            if (!is_string($cssClass) || !is_bool($newWindow)) {
                throw new InvalidArgumentException('Wikilinks css_class must be a string and new_window a boolean.');
            }
            if ($generator === null && $template !== null) {
                $generator = static fn (string $page): string => str_replace('{page}', Wikilinks::slug($page), $template);
            }

            return Wikilinks::bundle($generator, $cssClass, $newWindow);
        }
        if ($type === 'mermaid' || $type === 'fenced_render' || ($type === 'plantuml' && array_key_exists('language', $config))) {
            $engineOptions = ['css_class', 'tag', 'content_mode', 'wrap_in_figure', 'figure_class', 'label'];
            if (array_intersect(array_keys($config), $engineOptions) !== []) {
                $engineEntry = $config + ['language' => $type === 'plantuml' ? 'plantuml' : 'mermaid'];
                $engineEntry['type'] = 'fenced_render';

                return $this->fromEngine($engineEntry);
            }
            $this->only($config, ['language'], $type);
            $language = $config['language'] ?? ($type === 'plantuml' ? 'plantuml' : 'mermaid');
            if (!is_string($language) || preg_match('/^[a-z][a-z0-9_-]*$/', $language) !== 1) {
                throw new InvalidArgumentException('The fenced render language must be a simple lowercase name.');
            }

            return new ExtensionDefinition(
                'tempest.fenced.' . $language,
                '1',
                matcher: ['form' => 'fence', 'name' => $language],
                transform: static function (Claim $claim) use ($language): Node {
                    $node = new Node('block_extension', []);
                    $node->f = [
                        'name' => 'tempest.fenced.' . $language,
                        'version' => '1',
                        'props' => ['body' => $claim->source['body'] ?? ''],
                        'source' => $claim->source,
                        'fallback' => [$claim->copyNode()],
                    ];

                    return $node;
                },
                renderer: static function (Node $node, RenderHost $host) use ($language): string {
                    $props = $node->f['props'] ?? null;
                    $body = is_array($props) && is_string($props['body'] ?? null) ? $props['body'] : '';

                    return sprintf(
                        '<pre class="%s">%s</pre>',
                        RenderHost::escapeAttribute($language),
                        RenderHost::escapeText($body),
                    );
                },
            );
        }

        try {
            return $this->fromEngine($entry);
        } catch (InvalidArgumentException $exception) {
            if (str_contains($exception->getMessage(), 'is not available yet')) {
                throw new InvalidArgumentException(sprintf('Unknown Carve extension "%s".', $type), 0, $exception);
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed>|string $entry
     */
    private function fromEngine(array|string $entry): ExtensionDefinition|ExtensionProvider
    {
        $definitions = (new EngineExtensionFactory())->create($entry);
        if (count($definitions) === 1) {
            return $definitions[0];
        }

        return new class ($definitions) implements ExtensionProvider {
            /**
             * @param list<\MarkupCarve\Carve\Extension\ExtensionDefinition|\MarkupCarve\Carve\Extension\ExtensionProvider> $definitions
             */
            public function __construct(private readonly array $definitions)
            {
            }

            public function definitions(): array
            {
                $out = [];
                foreach ($this->definitions as $definition) {
                    if ($definition instanceof ExtensionProvider) {
                        array_push($out, ...$definition->definitions());
                    } else {
                        $out[] = $definition;
                    }
                }

                return $out;
            }
        };
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string> $allowed
     * @param string $type
     *
     * @throws \InvalidArgumentException
     */
    private function only(array $config, array $allowed, string $type): void
    {
        foreach (array_keys($config) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new InvalidArgumentException(sprintf('Unknown option "%s" for Carve extension "%s".', $key, $type));
            }
        }
    }

    /**
     * @param array<string, mixed>|string $entry
     */
    public function fingerprint(array|string $entry): ?string
    {
        if (!$this->isPlainData($entry)) {
            return null;
        }
        $encoded = json_encode($entry);

        return $encoded === false ? null : 'entry:' . hash('sha256', $encoded);
    }

    private function isPlainData(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (!$this->isPlainData($item)) {
                    return false;
                }
            }

            return true;
        }

        return $value === null || is_scalar($value);
    }
}
