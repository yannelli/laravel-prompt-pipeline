<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Exceptions;

class FragmentDepthExceededException extends PromptPipelineException
{
    public static function maxDepthReached(int $maxDepth): self
    {
        return new self("Fragment nesting exceeded maximum depth of {$maxDepth}.");
    }
}
