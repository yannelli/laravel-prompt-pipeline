<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Processing;

class ValidationResult
{
    /**
     * @param  array<string>  $errors
     */
    public function __construct(
        public readonly bool $valid,
        public readonly array $errors = []
    ) {}

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    /**
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }
}
