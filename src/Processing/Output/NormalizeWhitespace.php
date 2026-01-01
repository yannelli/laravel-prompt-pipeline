<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Processing\Output;

use Yannelli\PromptPipeline\Contracts\OutputProcessor;

class NormalizeWhitespace implements OutputProcessor
{
    /**
     * @param  array{max_newlines?: int}  $config
     */
    public function __construct(
        protected array $config = []
    ) {}

    public function process(string $output): string
    {
        $maxNewlines = $this->config['max_newlines'] ?? 2;

        // Normalize multiple spaces to single (except at line start for indentation)
        $output = (string) preg_replace('/(?<=\S) {2,}/', ' ', $output);

        // Normalize multiple newlines
        $pattern = '/\n{'.($maxNewlines + 1).',}/';
        $replacement = str_repeat("\n", $maxNewlines);
        $output = (string) preg_replace($pattern, $replacement, $output);

        // Trim trailing whitespace on each line
        $lines = explode("\n", $output);
        $lines = array_map('rtrim', $lines);
        $output = implode("\n", $lines);

        return trim($output);
    }
}
