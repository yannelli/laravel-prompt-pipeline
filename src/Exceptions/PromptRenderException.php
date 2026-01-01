<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Exceptions;

class PromptRenderException extends PromptPipelineException
{
    public static function syntaxError(string $message, ?int $line = null): self
    {
        $lineInfo = $line !== null ? " on line {$line}" : '';

        return new self("Template syntax error{$lineInfo}: {$message}");
    }

    public static function renderFailure(string $message): self
    {
        return new self("Template render failure: {$message}");
    }
}
