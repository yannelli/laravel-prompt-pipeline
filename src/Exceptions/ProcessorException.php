<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Exceptions;

class ProcessorException extends PromptPipelineException
{
    public static function processingFailed(string $processor, string $message): self
    {
        return new self("Processor '{$processor}' failed: {$message}");
    }
}
