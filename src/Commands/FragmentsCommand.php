<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Commands;

use Illuminate\Console\Command;
use Yannelli\PromptPipeline\Models\PromptTemplate;

class FragmentsCommand extends Command
{
    protected $signature = 'prompt-pipeline:fragments
                            {--owner= : Filter by owner (format: type:id)}
                            {--global : Show only global fragments}';

    protected $description = 'List available fragments';

    public function handle(): int
    {
        $query = PromptTemplate::query()
            ->where('type', 'fragment')
            ->where('is_active', true)
            ->orderBy('templateable_type')
            ->orderBy('templateable_id')
            ->orderBy('slug');

        $owner = $this->option('owner');
        $global = $this->option('global');

        if ($global) {
            $query->whereNull('templateable_type')
                ->whereNull('templateable_id');
        } elseif ($owner) {
            $parts = explode(':', $owner, 2);
            if (count($parts) !== 2) {
                $this->error('Owner format should be type:id (e.g., App\\Models\\Organization:1)');

                return self::FAILURE;
            }

            $query->where('templateable_type', $parts[0])
                ->where('templateable_id', $parts[1]);
        }

        $fragments = $query->get();

        if ($fragments->isEmpty()) {
            $this->info('No fragments found.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($fragments as $fragment) {
            $owner = $fragment->isGlobal()
                ? '<fg=cyan>global</>'
                : "{$fragment->templateable_type}:{$fragment->templateable_id}";

            $contentPreview = str_replace(["\n", "\r"], ' ', $fragment->content);
            if (strlen($contentPreview) > 50) {
                $contentPreview = substr($contentPreview, 0, 47).'...';
            }

            $rows[] = [
                $fragment->slug,
                $fragment->name,
                $owner,
                $contentPreview,
            ];
        }

        $this->table(['Slug', 'Name', 'Owner', 'Content Preview'], $rows);

        $this->newLine();
        $this->info('Total fragments: '.$fragments->count());

        return self::SUCCESS;
    }
}
