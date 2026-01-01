<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Yannelli\PromptPipeline\Facades\PromptPipeline;
use Yannelli\PromptPipeline\Models\PromptTemplate;

/**
 * Trait for models that own prompt templates.
 *
 * @mixin Model
 *
 * @phpstan-require-extends Model
 */
trait HasPromptTemplates
{
    /**
     * Get all prompt templates belonging to this model.
     *
     * @return MorphMany<PromptTemplate, $this>
     */
    public function promptTemplates(): MorphMany
    {
        return $this->morphMany(
            config('prompt-pipeline.model', PromptTemplate::class),
            'templateable'
        )->ordered();
    }

    /**
     * Get only active prompt templates.
     *
     * @return MorphMany<PromptTemplate, $this>
     */
    public function activePromptTemplates(): MorphMany
    {
        return $this->promptTemplates()->active();
    }

    /**
     * Get prompt templates of a specific type.
     *
     * @return MorphMany<PromptTemplate, $this>
     */
    public function promptTemplatesOfType(string $type): MorphMany
    {
        return $this->promptTemplates()->ofType($type);
    }

    /**
     * Get all fragments belonging to this model.
     *
     * @return MorphMany<PromptTemplate, $this>
     */
    public function fragments(): MorphMany
    {
        return $this->promptTemplates()->fragments();
    }

    /**
     * Find a prompt template by slug or ID.
     */
    public function findPromptTemplate(string $slugOrId): ?PromptTemplate
    {
        return $this->promptTemplates()
            ->where(function ($query) use ($slugOrId) {
                $query->where('slug', $slugOrId)
                    ->orWhere('id', $slugOrId);
            })
            ->first();
    }

    /**
     * Find a fragment by slug or ID.
     */
    public function findFragment(string $slugOrId): ?PromptTemplate
    {
        return $this->fragments()
            ->where(function ($query) use ($slugOrId) {
                $query->where('slug', $slugOrId)
                    ->orWhere('id', $slugOrId);
            })
            ->first();
    }

    /**
     * Create a new prompt template for this model.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createPromptTemplate(array $attributes): PromptTemplate
    {
        return $this->promptTemplates()->create($attributes);
    }

    /**
     * Create a new fragment for this model.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createFragment(string $slug, string $content, array $attributes = []): PromptTemplate
    {
        return $this->createPromptTemplate(array_merge($attributes, [
            'slug' => $slug,
            'name' => $attributes['name'] ?? ucfirst(str_replace(['_', '-'], ' ', $slug)),
            'content' => $content,
            'type' => 'fragment',
        ]));
    }

    /**
     * Render a prompt template by slug or ID.
     *
     * @param  array<string, mixed>  $variables
     */
    public function renderPromptTemplate(string $slugOrId, array $variables = []): string
    {
        $template = $this->findPromptTemplate($slugOrId);

        if ($template === null) {
            throw new \InvalidArgumentException("Prompt template '{$slugOrId}' not found.");
        }

        // Merge model variables with provided variables
        $allVariables = array_merge(
            $this->promptTemplateVariables(),
            $variables
        );

        return PromptPipeline::make($template)
            ->withVariables($allVariables)
            ->render();
    }

    /**
     * Override in model to provide automatic variables for templates.
     *
     * @return array<string, mixed>
     */
    public function promptTemplateVariables(): array
    {
        return [];
    }
}
