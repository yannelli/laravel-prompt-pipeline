<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Yannelli\PromptPipeline\Models\PromptTemplate;

class PromptRendered
{
    use Dispatchable;

    public function __construct(
        public readonly PromptTemplate $template,
        public readonly string $output
    ) {}
}
