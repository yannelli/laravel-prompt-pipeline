<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Contracts;

interface Renderer
{
    /**
     * Render a template string with the given variables.
     *
     * @param  array<string, mixed>  $variables
     */
    public function render(string $template, array $variables = []): string;

    /**
     * Validate a template string for syntax errors.
     */
    public function validate(string $template): bool;

    /**
     * Get validation errors for a template.
     *
     * @return array<string>
     */
    public function getErrors(string $template): array;
}
