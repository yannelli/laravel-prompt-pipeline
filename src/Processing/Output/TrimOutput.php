<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Processing\Output;

use Yannelli\PromptPipeline\Contracts\OutputProcessor;

class TrimOutput implements OutputProcessor
{
    public function process(string $output): string
    {
        return trim($output);
    }
}
