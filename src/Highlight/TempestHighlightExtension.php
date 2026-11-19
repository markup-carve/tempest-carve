<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest\Highlight;

use MarkupCarve\Carve2\Extension\Claim;
use MarkupCarve\Carve2\Extension\ExtensionDefinition;
use MarkupCarve\Carve2\Extension\ExtensionProvider;
use MarkupCarve\Carve2\Extension\RenderHost;
use MarkupCarve\Carve2\Node;
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
                transform: static function (Claim $claim) use ($language): ?Node {
                    if (($claim->node->f['attrs'] ?? []) !== [] || ($claim->node->f['title'] ?? null) !== null) {
                        return null;
                    }
                    $node = new Node('block_extension', []);
                    $node->f = [
                        'name' => 'tempest.highlight.' . $language,
                        'version' => '1',
                        'props' => ['language' => $language, 'code' => $claim->source['body'] ?? ''],
                        'source' => $claim->source,
                        'fallback' => [$claim->copyNode()],
                    ];

                    return $node;
                },
                renderer: function (Node $node, RenderHost $host) use ($language): string {
                    $props = $node->f['props'] ?? null;
                    $code = is_array($props) && is_string($props['code'] ?? null) ? $props['code'] : '';
                    $highlighted = $this->highlighter()->parse($code, $language);

                    return sprintf('<pre><code class="language-%s">%s</code></pre>', RenderHost::escapeAttribute($language), $highlighted);
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
