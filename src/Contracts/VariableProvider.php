<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Contracts;

interface VariableProvider
{
    /**
     * Get variables to be made available to templates.
     *
     * @return array<string, mixed>
     */
    public function getVariables(): array;
}
