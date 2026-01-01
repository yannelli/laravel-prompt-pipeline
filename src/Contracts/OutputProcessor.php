<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Contracts;

interface OutputProcessor
{
    /**
     * Process output after rendering.
     */
    public function process(string $output): string;
}
