<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Processing\Input;

use Yannelli\PromptPipeline\Contracts\InputProcessor;

class SanitizeInput implements InputProcessor
{
    /**
     * @param  array{strict?: bool}  $config
     */
    public function __construct(
        protected array $config = []
    ) {}

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function process(array $variables): array
    {
        return $this->sanitizeRecursive($variables);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sanitizeRecursive(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = $this->sanitizeString($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizeRecursive($value);
            }
        }

        return $data;
    }

    protected function sanitizeString(string $value): string
    {
        $strict = $this->config['strict'] ?? false;

        // Remove null bytes
        $value = str_replace("\0", '', $value);

        // In strict mode, strip potential template injection
        if ($strict) {
            // Remove Twig delimiters
            $value = preg_replace('/\{\{.*?\}\}/s', '', $value) ?? $value;
            $value = preg_replace('/\{%.*?%\}/s', '', $value) ?? $value;
            $value = preg_replace('/\{#.*?#\}/s', '', $value) ?? $value;
        }

        return $value;
    }
}
