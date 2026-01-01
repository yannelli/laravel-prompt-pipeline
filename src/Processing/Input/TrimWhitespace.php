<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Processing\Input;

use Yannelli\PromptPipeline\Contracts\InputProcessor;

class TrimWhitespace implements InputProcessor
{
    /**
     * @param  array{recursive?: bool}  $config
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
        $recursive = $this->config['recursive'] ?? true;

        if ($recursive) {
            return $this->trimRecursive($variables);
        }

        return array_map(function ($value) {
            return is_string($value) ? trim($value) : $value;
        }, $variables);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function trimRecursive(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->trimRecursive($value);
            }
        }

        return $data;
    }
}
