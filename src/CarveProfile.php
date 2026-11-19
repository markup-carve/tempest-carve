<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest;

enum CarveProfile: string
{
    case Full = 'full';
    case Article = 'article';
    case Comment = 'comment';
    case Minimal = 'minimal';
}
