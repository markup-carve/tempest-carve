<?php

declare(strict_types=1);

namespace Tests;

use MarkupCarve\Tempest\Testing\CarveAssertions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CarveAssertionsTest extends TestCase
{
    use CarveAssertions;

    #[Test]
    public function testProvidesReusableCarveAssertions(): void
    {
        $this->assertCarveRenders('<strong>bold</strong>', '*bold*');
        $this->assertCarveIsSafe("```=html\n<script>x</script>\n```", '<script>x</script>');
        $this->assertCarveHasWarning('reference', '[missing][nope]');
    }
}
