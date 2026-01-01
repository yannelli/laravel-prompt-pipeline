<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Exceptions;

class FragmentNotFoundException extends PromptPipelineException
{
    public static function forSlug(string $slug): self
    {
        return new self("Fragment '{$slug}' not found.");
    }
}
