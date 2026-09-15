<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest;

use MarkupCarve\Carve\Profile;

enum CarveProfile: string
{
    case Full = 'full';
    case Article = 'article';
    case Comment = 'comment';
    case Minimal = 'minimal';

    public function create(): Profile
    {
        return match ($this) {
            self::Full => Profile::full(),
            self::Article => Profile::article(),
            self::Comment => Profile::comment(),
            self::Minimal => Profile::minimal(),
        };
    }
}
