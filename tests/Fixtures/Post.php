<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use MarkupCarve\Tempest\Mapper\CarveContent;
use MarkupCarve\Tempest\Validation\ValidCarve;

final class Post
{
    public string $title;

    #[ValidCarve(lint: true)]
    public CarveContent $body;
}
