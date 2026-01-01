<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Yannelli\PromptPipeline\Commands\CacheClearCommand;
use Yannelli\PromptPipeline\Commands\FragmentsCommand;
use Yannelli\PromptPipeline\Commands\RenderCommand;
use Yannelli\PromptPipeline\Commands\ValidateCommand;
use Yannelli\PromptPipeline\Commands\VariablesCommand;
use Yannelli\PromptPipeline\Contracts\Renderer;
use Yannelli\PromptPipeline\Rendering\FragmentRegistry;
use Yannelli\PromptPipeline\Rendering\TwigRenderer;
use Yannelli\PromptPipeline\Rendering\VariableResolver;

class PromptPipelineServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('prompt-pipeline')
            ->hasConfigFile()
            ->hasMigration('create_prompt_pipeline_templates_table')
            ->hasCommands([
                ValidateCommand::class,
                RenderCommand::class,
                VariablesCommand::class,
                FragmentsCommand::class,
                CacheClearCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Register FragmentRegistry as a singleton
        $this->app->singleton(FragmentRegistry::class, function () {
            return new FragmentRegistry;
        });

        // Register VariableResolver as a singleton
        $this->app->singleton(VariableResolver::class, function () {
            $resolver = new VariableResolver;
            $resolver->loadFromConfig();

            return $resolver;
        });

        // Register TwigRenderer as a singleton
        $this->app->singleton(TwigRenderer::class, function ($app) {
            return new TwigRenderer(
                $app->make(FragmentRegistry::class)
            );
        });

        // Bind Renderer contract to TwigRenderer
        $this->app->bind(Renderer::class, TwigRenderer::class);

        // Register the manager as a singleton
        $this->app->singleton(PromptPipelineManager::class, function ($app) {
            return new PromptPipelineManager(
                $app->make(TwigRenderer::class),
                $app->make(VariableResolver::class),
                $app->make(FragmentRegistry::class)
            );
        });
    }

    public function packageBooted(): void
    {
        // Ensure cache directory exists
        $cacheConfig = config('prompt-pipeline.cache', []);
        if (($cacheConfig['enabled'] ?? true) && isset($cacheConfig['path'])) {
            $cachePath = $cacheConfig['path'];
            if (! is_dir($cachePath)) {
                @mkdir($cachePath, 0755, true);
            }
        }
    }
}
