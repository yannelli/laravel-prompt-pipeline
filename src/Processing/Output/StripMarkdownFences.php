<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Processing\Output;

use Yannelli\PromptPipeline\Contracts\OutputProcessor;

class StripMarkdownFences implements OutputProcessor
{
    /**
     * @param  array{preserve_content?: bool}  $config
     */
    public function __construct(
        protected array $config = []
    ) {}

    public function process(string $output): string
    {
        $preserveContent = $this->config['preserve_content'] ?? true;

        if ($preserveContent) {
            // Replace fenced code blocks with just their content
            return (string) preg_replace('/```(?:\w+)?\s*([\s\S]*?)\s*```/', '$1', $output);
        }

        // Remove fenced code blocks entirely
        return (string) preg_replace('/```(?:\w+)?\s*[\s\S]*?\s*```/', '', $output);
    }
}
