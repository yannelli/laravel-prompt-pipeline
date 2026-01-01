<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Processing\Input;

use Yannelli\PromptPipeline\Contracts\InputProcessor;

class EscapeXmlContent implements InputProcessor
{
    /**
     * @param  array{keys?: array<string>}  $config
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
        $keys = $this->config['keys'] ?? [];

        // If no keys specified, escape all string values
        if (empty($keys)) {
            return $this->escapeAllStrings($variables);
        }

        // Escape only specified keys
        foreach ($keys as $key) {
            if (isset($variables[$key]) && is_string($variables[$key])) {
                $variables[$key] = $this->escapeXml($variables[$key]);
            }
        }

        return $variables;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function escapeAllStrings(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = $this->escapeXml($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->escapeAllStrings($value);
            }
        }

        return $data;
    }

    protected function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
