<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Commands;

use Illuminate\Console\Command;
use Yannelli\PromptPipeline\Facades\PromptPipeline;
use Yannelli\PromptPipeline\Models\PromptTemplate;

class RenderCommand extends Command
{
    protected $signature = 'prompt-pipeline:render
                            {template : Template ID, slug, or file path}
                            {--variables= : JSON string of variables}
                            {--file : Treat template argument as a file path}';

    protected $description = 'Test render a template';

    public function handle(): int
    {
        $templateArg = $this->argument('template');
        $variablesJson = $this->option('variables');
        $isFile = $this->option('file');

        // Parse variables
        $variables = [];
        if ($variablesJson) {
            $variables = json_decode($variablesJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Invalid JSON provided for variables: '.json_last_error_msg());

                return self::FAILURE;
            }
        }

        try {
            if ($isFile) {
                // Load from file
                if (! file_exists($templateArg)) {
                    $this->error("File not found: {$templateArg}");

                    return self::FAILURE;
                }

                $templateContent = file_get_contents($templateArg);
                if ($templateContent === false) {
                    $this->error("Could not read file: {$templateArg}");

                    return self::FAILURE;
                }

                $output = PromptPipeline::fromString($templateContent)
                    ->withVariables($variables)
                    ->render();
            } else {
                // Try to find in database
                $template = PromptTemplate::where('id', $templateArg)
                    ->orWhere('slug', $templateArg)
                    ->first();

                if (! $template) {
                    $this->error("Template not found: {$templateArg}");

                    return self::FAILURE;
                }

                $output = PromptPipeline::make($template)
                    ->withVariables($variables)
                    ->render();
            }

            $this->line($output);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Render failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
