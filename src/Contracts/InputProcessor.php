<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Contracts;

interface InputProcessor
{
    /**
     * Process input variables before rendering.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function process(array $variables): array;
}
