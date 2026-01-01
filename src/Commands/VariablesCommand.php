<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Commands;

use Illuminate\Console\Command;
use Yannelli\PromptPipeline\Contracts\VariableProvider;
use Yannelli\PromptPipeline\Facades\PromptPipeline;

class VariablesCommand extends Command
{
    protected $signature = 'prompt-pipeline:variables';

    protected $description = 'List all registered system variables';

    public function handle(): int
    {
        $resolver = PromptPipeline::getVariableResolver();
        $providers = $resolver->getProviders();

        if (empty($providers)) {
            $this->info('No variable providers registered.');

            return self::SUCCESS;
        }

        $this->info('Registered Variable Providers:');
        $this->newLine();

        foreach ($providers as $provider) {
            $class = get_class($provider);
            $this->line("<comment>{$class}</comment>");

            $variables = $provider->getVariables();

            if (empty($variables)) {
                $this->line('  (no variables)');
            } else {
                foreach ($variables as $name => $value) {
                    $displayValue = $this->formatValue($value);
                    $this->line("  <info>{$name}</info>: {$displayValue}");
                }
            }

            $this->newLine();
        }

        // Show summary
        $allVariables = $resolver->resolve([]);
        $this->info('Total variables available: '.count($allVariables));

        return self::SUCCESS;
    }

    protected function formatValue(mixed $value): string
    {
        if (is_null($value)) {
            return '<fg=gray>null</>';
        }

        if (is_bool($value)) {
            return $value ? '<fg=green>true</>' : '<fg=red>false</>';
        }

        if (is_array($value)) {
            return '<fg=cyan>[array]</>';
        }

        if (is_object($value)) {
            return '<fg=cyan>['.get_class($value).']</>';
        }

        if (is_string($value) && strlen($value) > 50) {
            return '"'.substr($value, 0, 47).'..."';
        }

        return '"'.$value.'"';
    }
}
