<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class CacheClearCommand extends Command
{
    protected $signature = 'prompt-pipeline:cache:clear';

    protected $description = 'Clear compiled template cache';

    public function handle(Filesystem $filesystem): int
    {
        $cacheConfig = config('prompt-pipeline.cache', []);

        if (! ($cacheConfig['enabled'] ?? true)) {
            $this->info('Template caching is disabled.');

            return self::SUCCESS;
        }

        $cachePath = $cacheConfig['path'] ?? storage_path('framework/cache/prompt-pipeline');

        if (! is_dir($cachePath)) {
            $this->info('Cache directory does not exist.');

            return self::SUCCESS;
        }

        $files = $filesystem->glob("{$cachePath}/*");

        if (empty($files)) {
            $this->info('No cached templates found.');

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($files as $file) {
            if ($filesystem->isFile($file)) {
                $filesystem->delete($file);
                $count++;
            } elseif ($filesystem->isDirectory($file)) {
                $filesystem->deleteDirectory($file);
                $count++;
            }
        }

        $this->info("Cleared {$count} cached items.");

        return self::SUCCESS;
    }
}
