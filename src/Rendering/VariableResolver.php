<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Rendering;

use Illuminate\Database\Eloquent\Model;
use Yannelli\PromptPipeline\Contracts\VariableProvider;
use Yannelli\PromptPipeline\Traits\HasPromptTemplates;

class VariableResolver
{
    /**
     * Registered variable providers.
     *
     * @var array<VariableProvider>
     */
    protected array $providers = [];

    /**
     * Register a variable provider.
     */
    public function registerProvider(VariableProvider $provider): self
    {
        $this->providers[] = $provider;

        return $this;
    }

    /**
     * Register multiple variable providers.
     *
     * @param  array<VariableProvider>  $providers
     */
    public function registerProviders(array $providers): self
    {
        foreach ($providers as $provider) {
            $this->registerProvider($provider);
        }

        return $this;
    }

    /**
     * Clear all registered providers.
     */
    public function clearProviders(): self
    {
        $this->providers = [];

        return $this;
    }

    /**
     * Resolve all variables from all sources.
     *
     * @param  array<string, mixed>  $userVariables
     * @return array<string, mixed>
     */
    public function resolve(array $userVariables = [], ?Model $model = null): array
    {
        $variables = [];

        // 1. System providers (lowest priority)
        foreach ($this->providers as $provider) {
            $variables = array_merge($variables, $provider->getVariables());
        }

        // 2. Model variables (if model uses HasPromptTemplates)
        if ($model !== null && $this->modelHasPromptTemplates($model)) {
            /** @var HasPromptTemplates $model */
            $variables = array_merge($variables, $model->promptTemplateVariables());
        }

        // 3. User-provided variables (highest priority)
        $variables = array_merge($variables, $userVariables);

        return $variables;
    }

    /**
     * Check if a model uses the HasPromptTemplates trait.
     */
    protected function modelHasPromptTemplates(Model $model): bool
    {
        return method_exists($model, 'promptTemplateVariables');
    }

    /**
     * Get all registered providers.
     *
     * @return array<VariableProvider>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * Load providers from configuration.
     */
    public function loadFromConfig(): self
    {
        $providerClasses = config('prompt-pipeline.providers', []);

        foreach ($providerClasses as $class) {
            if (class_exists($class)) {
                $this->registerProvider(app($class));
            }
        }

        return $this;
    }
}
