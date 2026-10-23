<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest\Highlight;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Event\RenderEvent;
use MarkupCarve\Carve\Extension\ExtensionInterface;
use MarkupCarve\Carve\Node\Block\CodeBlock;
use MarkupCarve\Carve\Renderer\HtmlRenderer;
use MarkupCarve\TempestHighlight\CarveLanguage;
use Tempest\Highlight\Highlighter;

/**
 * Highlights fenced code in HTML output with tempest/highlight.
 *
 * Fences in a language the highlighter does not know, and fences carrying
 * attributes or a label, keep the engine's default markup.
 */
final class TempestHighlightExtension implements ExtensionInterface
{
    private ?Highlighter $highlighter;

    public function __construct(?Highlighter $highlighter = null)
    {
        $this->highlighter = $highlighter;
    }

    public function register(CarveConverter $converter): void
    {
        if (!$converter->getRenderer() instanceof HtmlRenderer) {
            return;
        }
        $converter->on('render.code_block', function (RenderEvent $event): void {
            $node = $event->getNode();
            if (!$node instanceof CodeBlock || $node->getAttributes() !== [] || $node->getLabel() !== null) {
                return;
            }
            $language = $node->getLanguage();
            if ($language === null) {
                return;
            }
            $highlighter = $this->highlighter();
            if (!in_array(strtolower($language), $highlighter->getSupportedLanguageNames(), true)) {
                return;
            }
            $code = $highlighter->parse($node->getContent(), strtolower($language));
            $event->setHtml(sprintf(
                "<pre><code class=\"language-%s\">%s</code></pre>\n",
                htmlspecialchars($language, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5),
                $code,
            ));
        });
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
