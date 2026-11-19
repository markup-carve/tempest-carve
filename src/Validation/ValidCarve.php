<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest\Validation;

use Attribute;
use InvalidArgumentException;
use MarkupCarve\Carve2\Carve2;
use MarkupCarve\Carve2\CarveException;
use MarkupCarve\Tempest\CarveProfile;
use Tempest\Validation\HasErrorMessage;
use Tempest\Validation\Rule;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class ValidCarve implements Rule, HasErrorMessage
{
    private string $error = '';

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

        try {
            $result = Carve2::renderWithDiagnostics($value, ['profile' => $this->profile === null ? 'full' : $this->profile->value]);
        } catch (CarveException $exception) {
            return $this->fail($exception->getMessage());
        }
        foreach ($result->diagnostics as $diagnostic) {
            if (str_starts_with($diagnostic->code, 'profile-')) {
                return $this->fail(sprintf('markup is not allowed by the %s profile', $this->profile === null ? 'configured' : $this->profile->value));
            }
            if ($this->strict && $diagnostic->channel === 'parse') {
                return $this->fail($diagnostic->message);
            }
        }
        if ($this->lint) {
            $findings = Carve2::lint($value);
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
