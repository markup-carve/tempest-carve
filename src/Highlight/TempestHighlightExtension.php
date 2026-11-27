<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest\Highlight;

use MarkupCarve\Carve\Attrs;
use MarkupCarve\Carve\Extension\ExtensionProvider;
use MarkupCarve\Carve\Node;
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
        // Code blocks stay code blocks so the engine retains their caption figures.
        return [];
    }

    public function renderCodeBlock(Node $node, string $defaultHtml): ?string
    {
        $syntax = $node->f['syntax'] ?? null;
        if (!is_string($syntax) || $syntax === '') {
            return null;
        }
        $language = strtolower($syntax);
        if (!in_array($language, array_map('strtolower', $this->highlighter()->getSupportedLanguageNames()), true)) {
            return null;
        }
        if (preg_match('~\A(<pre\b[^>]*><code\b[^>]*>).*?(</code></pre>)\z~s', $defaultHtml, $parts) !== 1) {
            return null;
        }

        $attrs = $node->f['attrs'] ?? null;
        $attrs = $attrs instanceof Attrs ? $attrs : null;
        $numbered = $attrs !== null && in_array('line-numbers', $attrs->classes, true);
        $startValue = $attrs?->get('data-line-start');
        $validStart = is_string($startValue) && preg_match('/\A[1-9][0-9]*\z/', $startValue) === 1;
        $start = $validStart ? $startValue : '1';
        $code = $node->f['value'] ?? '';
        $code = is_string($code) ? $code : '';
        $lineCount = substr_count($code, "\n") + (str_ends_with($code, "\n") ? 0 : 1);
        $manualGutter = $numbered && (
            self::compareDecimal($start, (string)PHP_INT_MAX) > 0
            || (int)$start > PHP_INT_MAX - max(0, $lineCount - 1)
        );
        $highlighter = $this->highlighter();
        if ($numbered && !$manualGutter) {
            $highlighter = $highlighter->withGutter((int)$start);
        }

        $html = $highlighter->parse($code, $language);
        $selected = self::selectedLines($attrs?->get('data-highlight'), $lineCount);
        if ($selected !== [] || $manualGutter) {
            $html = self::decorateLines($html, $selected, $manualGutter ? $start : null);
        }

        return $parts[1] . $html . $parts[2];
    }

    /**
     * @return array<int, true>
     */
    private static function selectedLines(?string $value, int $lineCount): array
    {
        if ($value === null || preg_match('/\A[1-9][0-9]*(?:-[1-9][0-9]*)?(?:,[1-9][0-9]*(?:-[1-9][0-9]*)?)*\z/', $value) !== 1) {
            return [];
        }
        $ranges = [];
        foreach (explode(',', $value) as $item) {
            $bounds = explode('-', $item);
            $first = $bounds[0];
            $last = $bounds[1] ?? $first;
            if (self::compareDecimal($first, $last) > 0) {
                return [];
            }
            $ranges[] = [$first, $last];
        }
        $selected = [];
        foreach ($ranges as [$first, $last]) {
            if (self::compareDecimal($first, (string)$lineCount) > 0) {
                continue;
            }
            $end = self::compareDecimal($last, (string)$lineCount) > 0 ? $lineCount : (int)$last;
            for ($line = (int)$first; $line <= $end; $line++) {
                $selected[$line] = true;
            }
        }

        return $selected;
    }

    private static function compareDecimal(string $a, string $b): int
    {
        return strlen($a) <=> strlen($b) ?: strcmp($a, $b);
    }

    private static function nextDecimal(string $value): string
    {
        for ($i = strlen($value) - 1; $i >= 0; $i--) {
            if ($value[$i] !== '9') {
                $value[$i] = strtr($value[$i], '012345678', '123456789');

                return $value;
            }
            $value[$i] = '0';
        }

        return '1' . $value;
    }

    /**
     * Preserve Tempest token spans even when one crosses a line end.
     *
     * @param string $html
     * @param array<int, true> $selected
     * @param string|null $manualStart
     */
    private static function decorateLines(string $html, array $selected, ?string $manualStart): string
    {
        $prefix = $html;
        $tail = '';
        if ($manualStart === null && $selected !== []) {
            $offset = 0;
            for ($line = 0, $last = max(array_keys($selected)); $line < $last; $line++) {
                $next = strpos($html, "\n", $offset);
                if ($next === false) {
                    break;
                }
                $offset = $next + 1;
            }
            if ($line === $last) {
                $prefix = substr($html, 0, $offset);
                $tail = substr($html, $offset);
            }
        }
        $tokens = preg_split('~(</?span\b[^>]*>|\n)~', $prefix, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($tokens === false) {
            return $html;
        }
        $out = '';
        $open = [];
        $current = '';
        $line = 1;
        foreach ($tokens as $token) {
            if ($token === "\n") {
                $out .= self::decorateLine($current . str_repeat('</span>', count($open)), $line, $selected, $manualStart) . "\n";
                $current = implode('', $open);
                $line++;
                if ($manualStart !== null) {
                    $manualStart = self::nextDecimal($manualStart);
                }
            } elseif (str_starts_with($token, '<span')) {
                $open[] = $token;
                $current .= $token;
            } elseif ($token === '</span>') {
                array_pop($open);
                $current .= $token;
            } else {
                $current .= $token;
            }
        }
        if ($tail !== '') {
            return $out . $current . $tail;
        }
        if ($current !== '' || $html === '') {
            $out .= self::decorateLine($current, $line, $selected, $manualStart);
        }

        return $out;
    }

    /**
     * @param string $html
     * @param int $line
     * @param array<int, true> $selected
     * @param string|null $manualStart
     */
    private static function decorateLine(string $html, int $line, array $selected, ?string $manualStart): string
    {
        if ($manualStart !== null) {
            $html = '<span class="hl-gutter">' . $manualStart . '</span>' . $html;
        }
        if (isset($selected[$line])) {
            return '<span class="hl-line-highlighted" style="display:inline-block;width:100%;background:rgb(255 213 0 / 18%)">' . $html . '</span>';
        }

        return $html;
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
