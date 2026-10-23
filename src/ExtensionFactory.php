<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest;

use InvalidArgumentException;
use MarkupCarve\Carve\Extension\ExtensionInterface;
use MarkupCarve\Tempest\Highlight\TempestHighlightExtension;
use ReflectionClass;
use ReflectionParameter;
use Throwable;

/**
 * Builds engine extensions from config entries: a name (`'autolink'`) or an
 * array with a `type` key and snake_case constructor options.
 */
final class ExtensionFactory
{
    /**
     * @param array<string, mixed>|string $entry
     *
     * @throws \InvalidArgumentException
     */
    public function create(array|string $entry): ExtensionInterface
    {
        $config = is_string($entry) ? ['type' => $entry] : $entry;
        $type = $config['type'] ?? null;
        if (!is_string($type) || $type === '') {
            throw new InvalidArgumentException('A Carve extension entry needs a string "type".');
        }
        unset($config['type']);

        if ($type === 'mermaid' || $type === 'plantuml') {
            $config += ['language' => $type === 'mermaid' ? 'mermaid' : ['plantuml', 'puml']];
            $type = 'fenced_render';
        }
        if ($type === 'fenced_render') {
            $config += ['language' => 'mermaid'];
        }
        if ($type === 'tempest_highlight') {
            $class = TempestHighlightExtension::class;
        } else {
            $class = 'MarkupCarve\\Carve\\Extension\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', $type))) . 'Extension';
        }
        if (!is_a($class, ExtensionInterface::class, true)) {
            throw new InvalidArgumentException(sprintf('Unknown Carve extension "%s".', $type));
        }

        if ($type === 'wikilinks' && isset($config['url_template'])) {
            if (!is_string($config['url_template'])) {
                throw new InvalidArgumentException('The wikilinks "url_template" option must be a string.');
            }
            $config['url_generator'] = (new WikiLinkUrlTemplate($config['url_template']))(...);
            unset($config['url_template']);
        }

        $aliases = match ($type) {
            'heading_permalinks' => ['class' => 'css_class'],
            'table_of_contents' => ['toc_class' => 'css_class'],
            'wikilinks' => ['link_class' => 'css_class'],
            'mentions' => ['user_url_template' => 'mention_url', 'user_class' => 'mention_class'],
            default => [],
        };
        $arguments = [];
        foreach ($config as $key => $value) {
            $arguments[lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $aliases[$key] ?? $key))))] = $value;
        }

        $constructor = (new ReflectionClass($class))->getConstructor();
        $parameters = $constructor === null
            ? []
            : array_map(static fn (ReflectionParameter $parameter): string => $parameter->getName(), $constructor->getParameters());
        foreach (array_keys($arguments) as $argument) {
            if (!in_array($argument, $parameters, true)) {
                throw new InvalidArgumentException(sprintf('Unknown option "%s" for Carve extension "%s".', $argument, $type));
            }
        }

        try {
            return new $class(...$arguments);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(
                sprintf('Invalid options for Carve extension "%s": %s', $type, $exception->getMessage()),
                0,
                $exception,
            );
        }
    }

    /**
     * A cache fingerprint for an entry, or null when an option is not plain data.
     *
     * Objects and closures are left out because JSON encodes two different
     * closures identically, which would let one configuration serve another's cache.
     *
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
