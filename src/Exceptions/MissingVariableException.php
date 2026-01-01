<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Exceptions;

class MissingVariableException extends PromptPipelineException
{
    public static function forVariable(string $variable): self
    {
        return new self("Required variable '{$variable}' was not provided.");
    }
}
