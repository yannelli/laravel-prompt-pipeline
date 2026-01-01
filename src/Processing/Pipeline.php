<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Processing;

use Illuminate\Database\Eloquent\Model;
use Yannelli\PromptPipeline\Contracts\ExclusionProvider;
use Yannelli\PromptPipeline\Contracts\InputProcessor;
use Yannelli\PromptPipeline\Contracts\OutputProcessor;
use Yannelli\PromptPipeline\Events\PromptRendered;
use Yannelli\PromptPipeline\Events\PromptRendering;
use Yannelli\PromptPipeline\Exceptions\ProcessorException;
use Yannelli\PromptPipeline\Exclusions\ExclusionSet;
use Yannelli\PromptPipeline\Models\PromptTemplate;
use Yannelli\PromptPipeline\Rendering\TwigRenderer;
use Yannelli\PromptPipeline\Rendering\VariableResolver;

/**
 * @final
 */
class Pipeline
{
    protected ?PromptTemplate $template = null;

    protected ?string $rawTemplate = null;

    protected ?string $rawOutput = null;

    /**
     * @var array<string, mixed>
     */
    protected array $variables = [];

    protected ?Model $model = null;

    /**
     * @var array<array{class: class-string<InputProcessor>, config: array<string, mixed>}>
     */
    protected array $inputProcessors = [];

    /**
     * @var array<array{class: class-string<OutputProcessor>, config: array<string, mixed>}>
     */
    protected array $outputProcessors = [];

    protected ?ExclusionSet $exclusions = null;

    protected ?ExclusionProvider $exclusionProvider = null;

    /**
     * @var array<string>
     */
    protected array $excludedFragments = [];

    /**
     * @var array<string>
     */
    protected array $excludedTags = [];

    protected bool $useDefaultProcessors = true;

    protected ?TwigRenderer $renderer = null;

    protected ?VariableResolver $variableResolver = null;

    /**
     * Create a pipeline from a PromptTemplate.
     */
    public static function make(PromptTemplate $template): static
    {
        $pipeline = new static;
        $pipeline->template = $template;

        return $pipeline;
    }

    /**
     * Create a pipeline from a raw template string.
     */
    public static function fromString(string $template): static
    {
        $pipeline = new static;
        $pipeline->rawTemplate = $template;

        return $pipeline;
    }

    /**
     * Create a pipeline for processing output only.
     */
    public static function forOutput(string $output): static
    {
        $pipeline = new static;
        $pipeline->rawOutput = $output;

        return $pipeline;
    }

    /**
     * Add variables for rendering.
     *
     * @param  array<string, mixed>  $variables
     */
    public function withVariables(array $variables): static
    {
        $this->variables = array_merge($this->variables, $variables);

        return $this;
    }

    /**
     * Add a model for variable resolution.
     */
    public function withModel(Model $model): static
    {
        $this->model = $model;

        // If model uses HasPromptTemplates trait, merge its variables
        if (method_exists($model, 'promptTemplateVariables')) {
            /** @var array<string, mixed> $modelVariables */
            $modelVariables = $model->promptTemplateVariables();
            $this->variables = array_merge($modelVariables, $this->variables);
        }

        return $this;
    }

    /**
     * Add an input processor.
     *
     * @param  class-string<InputProcessor>  $class
     * @param  array<string, mixed>  $config
     */
    public function inputProcessor(string $class, array $config = []): static
    {
        $this->inputProcessors[] = ['class' => $class, 'config' => $config];

        return $this;
    }

    /**
     * Add an output processor.
     *
     * @param  class-string<OutputProcessor>  $class
     * @param  array<string, mixed>  $config
     */
    public function outputProcessor(string $class, array $config = []): static
    {
        $this->outputProcessors[] = ['class' => $class, 'config' => $config];

        return $this;
    }

    /**
     * Disable default processors from config.
     */
    public function withoutDefaultProcessors(): static
    {
        $this->useDefaultProcessors = false;

        return $this;
    }

    /**
     * Set an ExclusionSet for this render.
     */
    public function withExclusions(ExclusionSet $exclusions): static
    {
        $this->exclusions = $exclusions;

        return $this;
    }

    /**
     * Set an ExclusionProvider for this render.
     */
    public function withExclusionProvider(ExclusionProvider $provider): static
    {
        $this->exclusionProvider = $provider;

        return $this;
    }

    /**
     * Exclude specific fragments.
     *
     * @param  array<string>  $slugs
     */
    public function excludeFragments(array $slugs): static
    {
        $this->excludedFragments = array_merge($this->excludedFragments, $slugs);

        return $this;
    }

    /**
     * Exclude specific tags.
     *
     * @param  array<string>  $tags
     */
    public function excludeTags(array $tags): static
    {
        $this->excludedTags = array_merge($this->excludedTags, $tags);

        return $this;
    }

    /**
     * Render the template.
     */
    public function render(): string
    {
        // Get the template content
        $templateContent = $this->getTemplateContent();

        // Initialize renderer
        $this->initializeRenderer();

        // Build exclusions
        $exclusions = $this->buildExclusionSet();

        // Apply exclusions
        $this->renderer->setExcludedTags($exclusions->getTags());
        $this->renderer->getFragmentRegistry()->setExcluded($exclusions->getFragments());

        // Set owner on fragment registry if model provided
        if ($this->model !== null) {
            $this->renderer->getFragmentRegistry()->setOwner($this->model);
        } elseif ($this->template !== null && $this->template->templateable !== null) {
            $this->renderer->getFragmentRegistry()->setOwner($this->template->templateable);
        }

        // Resolve all variables
        $variables = $this->resolveVariables();

        // Fire rendering event
        if ($this->template !== null) {
            $event = new PromptRendering($this->template, $variables);
            event($event);
            $variables = $event->variables;
        }

        // Process input variables
        $variables = $this->processInput($variables);

        // Render
        $output = $this->renderer->render($templateContent, $variables);

        // Process output
        $output = $this->processOutput($output);

        // Fire rendered event
        if ($this->template !== null) {
            PromptRendered::dispatch($this->template, $output);
        }

        return $output;
    }

    /**
     * Run output processing only (for processOutput()).
     */
    public function run(): string
    {
        if ($this->rawOutput === null) {
            throw new \RuntimeException('run() can only be called on output-only pipelines. Use render() for template pipelines.');
        }

        return $this->processOutput($this->rawOutput);
    }

    /**
     * Validate the template.
     */
    public function validate(): ValidationResult
    {
        $this->initializeRenderer();
        $templateContent = $this->getTemplateContent();

        $isValid = $this->renderer->validate($templateContent);
        $errors = $isValid ? [] : $this->renderer->getErrors($templateContent);

        return new ValidationResult($isValid, $errors);
    }

    /**
     * Get the template content.
     */
    protected function getTemplateContent(): string
    {
        if ($this->template !== null) {
            return $this->template->content;
        }

        if ($this->rawTemplate !== null) {
            return $this->rawTemplate;
        }

        throw new \RuntimeException('No template provided.');
    }

    /**
     * Initialize the renderer.
     */
    protected function initializeRenderer(): void
    {
        if ($this->renderer !== null) {
            return;
        }

        $this->renderer = app(TwigRenderer::class);
        $this->variableResolver = app(VariableResolver::class);
        $this->variableResolver->loadFromConfig();
    }

    /**
     * Build the combined ExclusionSet.
     */
    protected function buildExclusionSet(): ExclusionSet
    {
        // Start with config exclusions
        $set = ExclusionSet::fromConfig();

        // Merge with provided ExclusionSet
        if ($this->exclusions !== null) {
            $set = $set->merge($this->exclusions);
        }

        // Merge with provider exclusions
        if ($this->exclusionProvider !== null) {
            $set = $set->merge(new ExclusionSet(
                $this->exclusionProvider->excludedFragments(),
                $this->exclusionProvider->excludedTags()
            ));
        }

        // Merge with inline exclusions
        if (! empty($this->excludedFragments) || ! empty($this->excludedTags)) {
            $set = $set->merge(new ExclusionSet(
                $this->excludedFragments,
                $this->excludedTags
            ));
        }

        return $set;
    }

    /**
     * Resolve all variables from various sources.
     *
     * @return array<string, mixed>
     */
    protected function resolveVariables(): array
    {
        return $this->variableResolver->resolve($this->variables, $this->model);
    }

    /**
     * Process input variables through processors.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    protected function processInput(array $variables): array
    {
        $processors = $this->getInputProcessors();

        foreach ($processors as $processorInfo) {
            try {
                $processor = $this->createInputProcessor($processorInfo['class'], $processorInfo['config']);
                $variables = $processor->process($variables);
            } catch (\Throwable $e) {
                throw ProcessorException::processingFailed($processorInfo['class'], $e->getMessage());
            }
        }

        return $variables;
    }

    /**
     * Process output through processors.
     */
    protected function processOutput(string $output): string
    {
        $processors = $this->getOutputProcessors();

        foreach ($processors as $processorInfo) {
            try {
                $processor = $this->createOutputProcessor($processorInfo['class'], $processorInfo['config']);
                $output = $processor->process($output);
            } catch (\Throwable $e) {
                throw ProcessorException::processingFailed($processorInfo['class'], $e->getMessage());
            }
        }

        return $output;
    }

    /**
     * Get all input processors to run.
     *
     * @return array<array{class: class-string<InputProcessor>, config: array<string, mixed>}>
     */
    protected function getInputProcessors(): array
    {
        $processors = [];

        if ($this->useDefaultProcessors) {
            $defaults = config('prompt-pipeline.processors.input', []);
            foreach ($defaults as $class => $config) {
                if (is_string($config)) {
                    $processors[] = ['class' => $config, 'config' => []];
                } else {
                    $processors[] = ['class' => $class, 'config' => $config];
                }
            }
        }

        return array_merge($processors, $this->inputProcessors);
    }

    /**
     * Get all output processors to run.
     *
     * @return array<array{class: class-string<OutputProcessor>, config: array<string, mixed>}>
     */
    protected function getOutputProcessors(): array
    {
        $processors = [];

        if ($this->useDefaultProcessors) {
            $defaults = config('prompt-pipeline.processors.output', []);
            foreach ($defaults as $class => $config) {
                if (is_string($config)) {
                    $processors[] = ['class' => $config, 'config' => []];
                } else {
                    $processors[] = ['class' => $class, 'config' => $config];
                }
            }
        }

        return array_merge($processors, $this->outputProcessors);
    }

    /**
     * Create an input processor instance.
     *
     * @param  class-string<InputProcessor>  $class
     * @param  array<string, mixed>  $config
     */
    protected function createInputProcessor(string $class, array $config): InputProcessor
    {
        return new $class($config);
    }

    /**
     * Create an output processor instance.
     *
     * @param  class-string<OutputProcessor>  $class
     * @param  array<string, mixed>  $config
     */
    protected function createOutputProcessor(string $class, array $config): OutputProcessor
    {
        return new $class($config);
    }
}
