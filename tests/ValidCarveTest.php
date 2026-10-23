<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use MarkupCarve\Tempest\CarveProfile;
use MarkupCarve\Tempest\CarveRenderer;
use MarkupCarve\Tempest\Mapper\CarveCaster;
use MarkupCarve\Tempest\Mapper\CarveContent;
use MarkupCarve\Tempest\Mapper\CarveSerializer;
use MarkupCarve\Tempest\Validation\ValidCarve;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValidCarveTest extends TestCase
{
    #[Test]
    public function testAcceptsCarveAndEmptyValues(): void
    {
        $rule = new ValidCarve();

        self::assertTrue($rule->isValid("# Heading\n\n/text/"));
        self::assertTrue($rule->isValid(''));
        self::assertTrue($rule->isValid(null));
    }

    #[Test]
    public function testRejectsNonStrings(): void
    {
        $rule = new ValidCarve();

        self::assertFalse($rule->isValid(42));
        self::assertSame('The value is not valid Carve markup: value must be a string', $rule->getErrorMessage());
    }

    #[Test]
    public function testRejectsMarkupTheProfileForbids(): void
    {
        $rule = new ValidCarve(profile: CarveProfile::Minimal);

        self::assertTrue($rule->isValid('/fine/'));
        self::assertFalse($rule->isValid("# Heading\n"));
        self::assertStringContainsString('not allowed by the minimal profile', $rule->getErrorMessage());
    }

    #[Test]
    public function testEnforcesTheMaximumLength(): void
    {
        $rule = new ValidCarve(maxLength: 3, message: 'Body: {error}');

        self::assertTrue($rule->isValid('äöü'));
        self::assertFalse($rule->isValid('abcd'));
        self::assertSame('Body: must not exceed 3 characters', $rule->getErrorMessage());
    }

    #[Test]
    public function testRejectsANegativeMaximumLength(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ValidCarve(maxLength: -1);
    }

    #[Test]
    public function testLintFindingsFailOnlyWhenAskedFor(): void
    {
        self::assertTrue((new ValidCarve())->isValid("**bold**\n"));

        $rule = new ValidCarve(lint: true);
        self::assertFalse($rule->isValid("**bold**\n"));
        self::assertStringContainsString('renders as literal asterisks', $rule->getErrorMessage());
    }

    #[Test]
    public function testStrictModeFailsOnParseWarnings(): void
    {
        $source = "[^missing]\n";

        self::assertTrue((new ValidCarve())->isValid($source));
        self::assertFalse((new ValidCarve(strict: true))->isValid($source));
    }

    #[Test]
    public function testCasterAndSerializer(): void
    {
        $caster = new CarveCaster(CarveRenderer::safe());
        $content = $caster->cast('/x/');

        self::assertInstanceOf(CarveContent::class, $content);
        self::assertSame("<p><em>x</em></p>\n", $content->html());
        self::assertNull($caster->cast(null));
        self::assertSame($content, $caster->cast($content));
        self::assertSame('/x/', (new CarveSerializer())->serialize($content));
        self::assertSame('/y/', (new CarveSerializer())->serialize('/y/'));

        $this->expectException(InvalidArgumentException::class);
        $caster->cast(['not', 'source']);
    }
}
