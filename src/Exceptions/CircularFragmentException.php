<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Exceptions;

class CircularFragmentException extends PromptPipelineException
{
    /**
     * @param  array<string>  $chain
     */
    public static function detected(array $chain): self
    {
        $chainStr = implode(' -> ', $chain);

        return new self("Circular fragment reference detected: {$chainStr}");
    }
}
