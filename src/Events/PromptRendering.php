<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Yannelli\PromptPipeline\Models\PromptTemplate;

class PromptRendering
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $variables
     */
    public function __construct(
        public readonly PromptTemplate $template,
        public array $variables
    ) {}
}
