<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest\Internal;

enum OutputFormat: string
{
    case Html = 'html';
    case Report = 'report';
    case Markdown = 'markdown';
    case Plain = 'plain';
    case Ansi = 'ansi';
}
