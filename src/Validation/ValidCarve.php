<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest\Validation;

use Attribute;
use InvalidArgumentException;
use LengthException;
use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Exception\ParseException;
use MarkupCarve\Carve\Lint\DocumentLinter;
use MarkupCarve\Tempest\CarveProfile;
use Tempest\Validation\HasErrorMessage;
use Tempest\Validation\Rule;

/**
 * Validates that a value is Carve source that parses under an optional profile.
 *
 *     #[ValidCarve(profile: CarveProfile::Comment, lint: true, maxLength: 5000)]
 *     public string $body;
 *
 * Null and the empty string pass; combine with a required rule to reject them.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class ValidCarve implements Rule, HasErrorMessage
{
    private string $error = '';

    /**
     * @param \MarkupCarve\Tempest\CarveProfile|null $profile Reject markup the profile does not allow
     * @param bool $strict Treat parse warnings as errors
     * @param bool $lint Treat lint findings as errors
     * @param int|null $maxLength Maximum length in characters
     * @param string|null $message Error message template; `{error}` is replaced with the reason
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        private readonly ?CarveProfile $profile = null,
        private readonly bool $strict = false,
        private readonly bool $lint = false,
        private readonly ?int $maxLength = null,
        private readonly ?string $message = null,
    ) {
        if ($maxLength !== null && $maxLength < 0) {
            throw new InvalidArgumentException('Carve maximum length must not be negative.');
        }
    }

    public function isValid(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!is_string($value)) {
            return $this->fail('value must be a string');
        }
        if ($this->maxLength !== null && mb_strlen($value) > $this->maxLength) {
            return $this->fail(sprintf('must not exceed %d characters', $this->maxLength));
        }

        $converter = new CarveConverter(warnings: true, profile: $this->profile?->create());
        try {
            $converter->parse($value);
        } catch (ParseException | LengthException $exception) {
            return $this->fail($exception->getMessage());
        }
        if ($converter->hasProfileViolations()) {
            return $this->fail(sprintf('markup is not allowed by the %s profile', $this->profile->value ?? 'configured'));
        }
        if ($this->strict && $converter->hasWarnings()) {
            return $this->fail($converter->getWarnings()[0]->getMessage());
        }
        if ($this->lint) {
            $findings = (new DocumentLinter())->lint($value);
            if ($findings !== []) {
                return $this->fail($findings[0]->message);
            }
        }

        return true;
    }

    public function getErrorMessage(): string
    {
        return str_replace('{error}', $this->error, $this->message ?? 'The value is not valid Carve markup: {error}');
    }

    private function fail(string $error): false
    {
        $this->error = $error;

        return false;
    }
}
