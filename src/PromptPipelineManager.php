<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline;

use Yannelli\PromptPipeline\Models\PromptTemplate;
use Yannelli\PromptPipeline\Processing\Pipeline;
use Yannelli\PromptPipeline\Rendering\FragmentRegistry;
use Yannelli\PromptPipeline\Rendering\TwigRenderer;
use Yannelli\PromptPipeline\Rendering\VariableResolver;

class PromptPipelineManager
{
    protected TwigRenderer $renderer;

    protected VariableResolver $variableResolver;

    protected FragmentRegistry $fragmentRegistry;

    public function __construct(
        TwigRenderer $renderer,
        VariableResolver $variableResolver,
        FragmentRegistry $fragmentRegistry
    ) {
        $this->renderer = $renderer;
        $this->variableResolver = $variableResolver;
        $this->fragmentRegistry = $fragmentRegistry;
    }

    /**
     * Create a pipeline from a PromptTemplate.
     */
    public function make(PromptTemplate $template): Pipeline
    {
        return Pipeline::make($template);
    }

    /**
     * Create a pipeline from a raw template string.
     */
    public function fromString(string $template): Pipeline
    {
        return Pipeline::fromString($template);
    }

    /**
     * Create a pipeline for processing output only.
     */
    public function forOutput(string $output): Pipeline
    {
        return Pipeline::forOutput($output);
    }

    /**
     * Get the underlying renderer.
     */
    public function getRenderer(): TwigRenderer
    {
        return $this->renderer;
    }

    /**
     * Get the variable resolver.
     */
    public function getVariableResolver(): VariableResolver
    {
        return $this->variableResolver;
    }

    /**
     * Get the fragment registry.
     */
    public function getFragmentRegistry(): FragmentRegistry
    {
        return $this->fragmentRegistry;
    }

    /**
     * Register a runtime fragment.
     */
    public function registerFragment(string $slug, string $content): self
    {
        $this->fragmentRegistry->register($slug, $content);

        return $this;
    }

    /**
     * Validate a template string.
     */
    public function validate(string $template): bool
    {
        return $this->renderer->validate($template);
    }

    /**
     * Get validation errors for a template.
     *
     * @return array<string>
     */
    public function getErrors(string $template): array
    {
        return $this->renderer->getErrors($template);
    }
}
