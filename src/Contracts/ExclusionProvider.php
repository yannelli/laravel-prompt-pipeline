<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Contracts;

interface ExclusionProvider
{
    /**
     * Get fragment slugs to exclude from rendering.
     *
     * @return array<string>
     */
    public function excludedFragments(): array;

    /**
     * Get XML tags to exclude from rendering.
     *
     * @return array<string>
     */
    public function excludedTags(): array;
}
