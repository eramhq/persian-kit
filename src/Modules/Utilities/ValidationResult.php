<?php

namespace PersianKit\Modules\Utilities;

defined('ABSPATH') || exit;

final class ValidationResult
{
    private function __construct(
        private readonly bool  $valid,
        private readonly array $errors = [],
        private readonly array $details = [],
    ) {}

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function details(): array
    {
        return $this->details;
    }

    public static function success(array $details = []): self
    {
        return new self(true, [], $details);
    }

    public static function failure(string|array $errors, array $details = []): self
    {
        return new self(false, (array) $errors, $details);
    }
}
