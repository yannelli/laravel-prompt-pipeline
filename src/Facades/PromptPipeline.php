<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Facades;

use Illuminate\Support\Facades\Facade;
use Yannelli\PromptPipeline\Models\PromptTemplate;
use Yannelli\PromptPipeline\Processing\Pipeline;
use Yannelli\PromptPipeline\PromptPipelineManager;
use Yannelli\PromptPipeline\Rendering\FragmentRegistry;
use Yannelli\PromptPipeline\Rendering\TwigRenderer;
use Yannelli\PromptPipeline\Rendering\VariableResolver;

/**
 * @method static Pipeline make(PromptTemplate $template)
 * @method static Pipeline fromString(string $template)
 * @method static Pipeline processOutput(string $output)
 * @method static TwigRenderer getRenderer()
 * @method static VariableResolver getVariableResolver()
 * @method static FragmentRegistry getFragmentRegistry()
 * @method static PromptPipelineManager registerFragment(string $slug, string $content)
 * @method static bool validate(string $template)
 * @method static array getErrors(string $template)
 *
 * @see \Yannelli\PromptPipeline\PromptPipelineManager
 */
class PromptPipeline extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PromptPipelineManager::class;
    }
}
