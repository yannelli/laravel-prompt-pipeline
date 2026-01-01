<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Commands;

use Illuminate\Console\Command;
use Yannelli\PromptPipeline\Facades\PromptPipeline;

class ValidateCommand extends Command
{
    protected $signature = 'prompt-pipeline:validate
                            {--file= : Path to a template file to validate}
                            {--string= : A template string to validate}';

    protected $description = 'Validate template syntax and sandbox compliance';

    public function handle(): int
    {
        $file = $this->option('file');
        $string = $this->option('string');

        if (! $file && ! $string) {
            $this->error('Please provide either --file or --string option.');

            return self::FAILURE;
        }

        if ($file && $string) {
            $this->error('Please provide only one of --file or --string options.');

            return self::FAILURE;
        }

        $template = $string;

        if ($file) {
            if (! file_exists($file)) {
                $this->error("File not found: {$file}");

                return self::FAILURE;
            }

            $template = file_get_contents($file);
            if ($template === false) {
                $this->error("Could not read file: {$file}");

                return self::FAILURE;
            }
        }

        $isValid = PromptPipeline::validate($template);

        if ($isValid) {
            $this->info('Template is valid.');

            return self::SUCCESS;
        }

        $this->error('Template validation failed:');

        $errors = PromptPipeline::getErrors($template);
        foreach ($errors as $error) {
            $this->line("  - {$error}");
        }

        return self::FAILURE;
    }
}
