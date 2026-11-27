<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest\Highlight;

use MarkupCarve\Carve\Attrs;
use MarkupCarve\Carve\Extension\Claim;
use MarkupCarve\Carve\Extension\ExtensionDefinition;
use MarkupCarve\Carve\Extension\ExtensionProvider;
use MarkupCarve\Carve\Extension\RenderHost;
use MarkupCarve\Carve\Node;
use MarkupCarve\Carve\Resolve\PlainText;
use MarkupCarve\TempestHighlight\CarveLanguage;
use Tempest\Highlight\Highlighter;

final class TempestHighlightExtension implements ExtensionProvider
{
    private ?Highlighter $highlighter;

    public function __construct(?Highlighter $highlighter = null)
    {
        $this->highlighter = $highlighter;
    }

    public function definitions(): array
    {
        $definitions = [];
        foreach (array_unique($this->highlighter()->getSupportedLanguageNames()) as $language) {
            $language = strtolower($language);
            if (preg_match('/^[a-z][a-z0-9_-]*$/', $language) !== 1) {
                continue;
            }
            $definitions[] = new ExtensionDefinition(
                'tempest.highlight.' . $language,
                '1',
                matcher: ['form' => 'fence', 'name' => $language],
                transform: static function (Claim $claim) use ($language): Node {
                    $node = new Node('block_extension', []);
                    $node->f = [
                        'name' => 'tempest.highlight.' . $language,
                        'version' => '1',
                        'props' => ['language' => $language, 'code' => $claim->source['body'] ?? ''],
                        'source' => $claim->source,
                        'fallback' => [$claim->copyNode()],
                    ];
                    $node->f['attrs'] = $claim->node->f['attrs'] ?? null;
                    $node->f['title'] = $claim->node->f['title'] ?? null;

                    return $node;
                },
                renderer: function (Node $node, RenderHost $host) use ($language): string {
                    $props = $node->f['props'] ?? null;
                    $code = is_array($props) && is_string($props['code'] ?? null) ? $props['code'] : '';
                    $highlighted = $this->highlighter()->parse($code, $language);
                    $attrs = $host->attributes($node);
                    $title = $node->f['title'] ?? null;
                    $authored = $node->f['attrs'] ?? null;
                    if (is_array($title) && !($authored instanceof Attrs && $authored->get('title') !== null)) {
                        $attrs .= ' title="' . RenderHost::escapeAttribute(PlainText::of(PlainText::nodes($title))) . '"';
                    }

                    return sprintf('<pre%s><code class="language-%s">%s</code></pre>', $attrs, RenderHost::escapeAttribute($language), $highlighted);
                },
            );
        }

        return $definitions;
    }

    private function highlighter(): Highlighter
    {
        if ($this->highlighter === null) {
            $this->highlighter = new Highlighter();
            if (class_exists(CarveLanguage::class)) {
                $this->highlighter->addLanguage(new CarveLanguage());
            }
        }

        return $this->highlighter;
    }
}
