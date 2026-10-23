<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest\Console;

use FilesystemIterator;
use InvalidArgumentException;
use MarkupCarve\Carve\Transform\FilesystemIncludeResolver;
use MarkupCarve\Tempest\CarveRenderer;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tempest\Console\Console;
use Tempest\Console\ConsoleArgument;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Throwable;

final readonly class CarveCommands
{
    public function __construct(
        private Console $console,
        private CarveRenderer $carve,
    ) {
    }

    #[ConsoleCommand(name: 'carve:render', description: 'Render a Carve file as HTML, plain text, Markdown or ANSI')]
    public function render(
        #[ConsoleArgument(description: 'The .crv file to render')]
        string $path,
        #[ConsoleArgument(description: 'html, text, markdown or ansi')]
        string $format = 'html',
        #[ConsoleArgument(description: 'A named renderer from CarveConfig::$namedRenderers')]
        ?string $renderer = null,
        #[ConsoleArgument(description: 'Write to this file instead of the console')]
        ?string $output = null,
    ): ExitCode {
        $source = $this->read($path);
        if ($source === null) {
            return ExitCode::ERROR;
        }

        try {
            $carve = $renderer === null || $renderer === '' ? $this->carve : $this->carve->named($renderer);
            $rendered = match ($format) {
                'html' => $carve->renderHtml($source),
                'text', 'plain' => $carve->renderPlainText($source),
                'markdown', 'md' => $carve->renderMarkdown($source),
                'ansi' => $carve->renderAnsi($source),
                default => throw new InvalidArgumentException(sprintf('Unknown format [%s]. Use html, text, markdown or ansi.', $format)),
            };
        } catch (Throwable $exception) {
            $this->console->error($exception->getMessage());

            return ExitCode::ERROR;
        }

        return $this->emit($rendered, $output);
    }

    #[ConsoleCommand(name: 'carve:lint', description: 'Report Carve constructs that probably do not mean what the author intended')]
    public function lint(
        #[ConsoleArgument(description: 'A file, or a directory searched for *.crv')]
        string $path,
        #[ConsoleArgument(description: 'Also lint the files that include directives pull in from below this root')]
        ?string $includeRoot = null,
    ): ExitCode {
        if ($includeRoot === null || $includeRoot === '') {
            return $this->lintFiles([$path], null);
        }
        $resolved = realpath($includeRoot);
        if ($resolved === false || !is_dir($resolved)) {
            $this->console->error(sprintf('Include root [%s] is not a directory.', $includeRoot));

            return ExitCode::ERROR;
        }

        return $this->lintFiles([$path], $resolved);
    }

    #[ConsoleCommand(name: 'carve:fmt', description: 'Print Carve files in canonical form, or rewrite or check them')]
    public function fmt(
        #[ConsoleArgument(description: 'A file, or a directory searched for *.crv')]
        string $path,
        #[ConsoleArgument(description: 'Rewrite each file in place')]
        bool $write = false,
        #[ConsoleArgument(description: 'Fail if a file is not formatted, without changing it')]
        bool $check = false,
        #[ConsoleArgument(description: 'Pad pipe table cells so the columns line up')]
        bool $alignTables = false,
    ): ExitCode {
        if ($write && $check) {
            $this->console->error('--write and --check are mutually exclusive.');

            return ExitCode::INVALID;
        }

        $failed = false;
        $unformatted = 0;
        foreach ($this->files([$path], $failed) as $file) {
            $source = $this->read($file);
            if ($source === null) {
                $failed = true;

                continue;
            }
            $formatted = $this->carve->format($source, $alignTables);
            if ($check) {
                if ($formatted !== $source) {
                    $unformatted++;
                    $this->console->writeln(sprintf('%s is not formatted', $file));
                }

                continue;
            }
            if ($write) {
                if ($formatted !== $source && @file_put_contents($file, $formatted) === false) {
                    $failed = true;
                    $this->console->error(sprintf('File [%s] could not be written.', $file));
                }

                continue;
            }
            $this->console->writeRaw($formatted);
        }

        if ($unformatted > 0) {
            $this->console->error(sprintf('%d file(s) not formatted.', $unformatted));

            return ExitCode::ERROR;
        }

        return $failed ? ExitCode::ERROR : ExitCode::SUCCESS;
    }

    #[ConsoleCommand(name: 'carve:convert', description: 'Convert a Markdown or HTML file into Carve')]
    public function convert(
        #[ConsoleArgument(description: 'The Markdown or HTML file to convert')]
        string $path,
        #[ConsoleArgument(description: 'markdown or html (guessed from the file extension)')]
        ?string $from = null,
        #[ConsoleArgument(description: 'Write to this file instead of the console')]
        ?string $output = null,
    ): ExitCode {
        $source = $this->read($path);
        if ($source === null) {
            return ExitCode::ERROR;
        }

        $from = $from !== null && $from !== '' ? strtolower($from) : match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'md', 'markdown' => 'markdown',
            'html', 'htm' => 'html',
            default => null,
        };
        $converted = match ($from) {
            'markdown', 'md' => $this->carve->fromMarkdown($source),
            'html' => $this->carve->fromHtml($source),
            default => null,
        };
        if ($converted === null) {
            $this->console->error('Cannot tell the input format. Pass --from=markdown or --from=html.');

            return ExitCode::INVALID;
        }

        return $this->emit($converted, $output);
    }

    /**
     * @param array<string> $paths
     * @param string|null $includeRoot
     */
    private function lintFiles(array $paths, ?string $includeRoot): ExitCode
    {
        $missing = false;
        $rows = [];
        $resolver = $includeRoot === null ? null : new FilesystemIncludeResolver($includeRoot);
        foreach ($this->files($paths, $missing) as $file) {
            $source = $this->read($file);
            if ($source === null) {
                $missing = true;

                continue;
            }
            $path = $resolver === null ? $file : ((string)realpath($file) ?: $file);
            $warnings = $resolver === null
                ? $this->carve->lint($source)
                : $this->carve->lintIncluded($source, $path, $resolver);
            foreach ($warnings as $warning) {
                $location = sprintf('%s:%d:%d', $warning->file ?? $path, $warning->line, $warning->column);
                // A file that is both linted and included by another one would report twice.
                $rows[$location . "\0" . $warning->rule . "\0" . $warning->message] = sprintf('%s  %s  %s', $location, $warning->rule, $warning->message);
            }
        }

        if ($rows === []) {
            if (!$missing) {
                $this->console->success('No Carve problems found.');
            }

            return $missing ? ExitCode::ERROR : ExitCode::SUCCESS;
        }

        foreach ($rows as $row) {
            $this->console->writeRaw($row . PHP_EOL);
        }
        $this->console->error(sprintf('%d Carve problem(s) found.', count($rows)));

        return ExitCode::ERROR;
    }

    /**
     * @param array<string> $paths
     * @param bool $missing
     *
     * @return list<string>
     */
    private function files(array $paths, bool &$missing): array
    {
        $files = [];
        foreach ($paths as $path) {
            if (is_dir($path)) {
                $found = [];
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
                /** @var \SplFileInfo $file */
                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'crv') {
                        $found[] = $file->getPathname();
                    }
                }
                sort($found);
                array_push($files, ...$found);
            } elseif (is_file($path)) {
                $files[] = $path;
            } else {
                $missing = true;
                $this->console->warning(sprintf('Skipping [%s]: not found.', $path));
            }
        }

        return array_values(array_unique($files));
    }

    private function read(string $path): ?string
    {
        $source = is_file($path) && is_readable($path) ? @file_get_contents($path) : false;
        if ($source === false) {
            $this->console->error(sprintf('File [%s] is not readable.', $path));

            return null;
        }

        return $source;
    }

    private function emit(string $contents, ?string $output): ExitCode
    {
        if ($output === null || $output === '') {
            $this->console->writeRaw($contents);

            return ExitCode::SUCCESS;
        }
        if (@file_put_contents($output, $contents) === false) {
            $this->console->error(sprintf('Cannot write to [%s].', $output));

            return ExitCode::ERROR;
        }
        $this->console->success(sprintf('Written to [%s].', $output));

        return ExitCode::SUCCESS;
    }
}
