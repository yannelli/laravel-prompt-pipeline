<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Processing\Output;

use Yannelli\PromptPipeline\Contracts\OutputProcessor;

class ExtractJsonBlock implements OutputProcessor
{
    /**
     * @param  array{fallback_raw?: bool}  $config
     */
    public function __construct(
        protected array $config = []
    ) {}

    public function process(string $output): string
    {
        // Try to extract JSON from markdown code fences
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $output, $matches)) {
            $potential = trim($matches[1]);

            // Validate it's actual JSON
            json_decode($potential);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $potential;
            }
        }

        // Try to find raw JSON object or array
        if (preg_match('/(\{[\s\S]*\}|\[[\s\S]*\])/', $output, $matches)) {
            $potential = $matches[1];

            json_decode($potential);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $potential;
            }
        }

        // Fallback: return raw if configured, otherwise original
        return ($this->config['fallback_raw'] ?? true) ? $output : '';
    }
}
