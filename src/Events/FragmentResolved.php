<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Yannelli\PromptPipeline\Models\PromptTemplate;

class FragmentResolved
{
    use Dispatchable;

    public function __construct(
        public readonly string $slug,
        public readonly PromptTemplate $fragment
    ) {}
}
