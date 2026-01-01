<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Processing\Output;

use Yannelli\PromptPipeline\Contracts\OutputProcessor;

class ExtractXmlTag implements OutputProcessor
{
    private string $tag;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->tag = isset($config['tag']) && is_string($config['tag']) ? $config['tag'] : '';
    }

    public function process(string $output): string
    {
        if ($this->tag === '') {
            return $output;
        }

        // Match the tag and extract content
        $pattern = '/<'.preg_quote($this->tag, '/').'[^>]*>([\s\S]*?)<\/'.preg_quote($this->tag, '/').'>/';

        if (preg_match($pattern, $output, $matches)) {
            return trim($matches[1]);
        }

        return $output;
    }
}
