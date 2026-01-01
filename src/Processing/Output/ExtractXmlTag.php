<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Processing\Output;

use Yannelli\PromptPipeline\Contracts\OutputProcessor;

class ExtractXmlTag implements OutputProcessor
{
    /**
     * @param  array{tag: string}  $config
     */
    public function __construct(
        protected array $config = []
    ) {}

    public function process(string $output): string
    {
        $tag = $this->config['tag'] ?? '';

        if (empty($tag)) {
            return $output;
        }

        // Match the tag and extract content
        $pattern = '/<'.preg_quote($tag, '/').'[^>]*>([\s\S]*?)<\/'.preg_quote($tag, '/').'>/';

        if (preg_match($pattern, $output, $matches)) {
            return trim($matches[1]);
        }

        return $output;
    }
}
