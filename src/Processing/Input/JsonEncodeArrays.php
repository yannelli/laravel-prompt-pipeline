<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Processing\Input;

use Yannelli\PromptPipeline\Contracts\InputProcessor;

class JsonEncodeArrays implements InputProcessor
{
    /**
     * @param  array{pretty?: bool}  $config
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
        $pretty = $this->config['pretty'] ?? false;
        $flags = $pretty ? JSON_PRETTY_PRINT : 0;

        foreach ($variables as $key => $value) {
            if (is_array($value)) {
                // Only encode if it's not an associative array that should be kept
                // For pure indexed arrays or data arrays, encode to JSON
                $variables[$key] = json_encode($value, $flags);
            }
        }

        return $variables;
    }
}
