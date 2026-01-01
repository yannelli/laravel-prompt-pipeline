<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Processing\Input;

use Yannelli\PromptPipeline\Contracts\InputProcessor;

class NormalizeLineBreaks implements InputProcessor
{
    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function process(array $variables): array
    {
        return $this->normalizeRecursive($variables);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeRecursive(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Convert \r\n and \r to \n
                $data[$key] = str_replace(["\r\n", "\r"], "\n", $value);
            } elseif (is_array($value)) {
                $data[$key] = $this->normalizeRecursive($value);
            }
        }

        return $data;
    }
}
