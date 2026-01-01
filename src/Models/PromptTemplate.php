<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Yannelli\PromptPipeline\Events\PromptTemplateCreated;
use Yannelli\PromptPipeline\Events\PromptTemplateDeleted;
use Yannelli\PromptPipeline\Events\PromptTemplateUpdated;
use Yannelli\PromptPipeline\Facades\PromptPipeline;

/**
 * @property string $id
 * @property string $name
 * @property string|null $slug
 * @property string $content
 * @property string|null $type
 * @property array<string, mixed>|null $metadata
 * @property string $templateable_type
 * @property string $templateable_id
 * @property bool $is_active
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|null $templateable
 */
class PromptTemplate extends Model
{
    use HasUlids;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'content',
        'type',
        'metadata',
        'templateable_type',
        'templateable_id',
        'is_active',
        'sort_order',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @var array<string, string>
     */
    protected $dispatchesEvents = [
        'created' => PromptTemplateCreated::class,
        'updated' => PromptTemplateUpdated::class,
        'deleted' => PromptTemplateDeleted::class,
    ];

    public function getTable(): string
    {
        return config('prompt-pipeline.table_name', 'prompt_pipeline_templates');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the parent templateable model.
     *
     * @return MorphTo<Model, $this>
     */
    public function templateable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to active templates.
     *
     * @param  Builder<PromptTemplate>  $query
     * @return Builder<PromptTemplate>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to templates of a specific type.
     *
     * @param  Builder<PromptTemplate>  $query
     * @return Builder<PromptTemplate>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to find by slug.
     *
     * @param  Builder<PromptTemplate>  $query
     * @return Builder<PromptTemplate>
     */
    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    /**
     * Scope to fragment templates.
     *
     * @param  Builder<PromptTemplate>  $query
     * @return Builder<PromptTemplate>
     */
    public function scopeFragments(Builder $query): Builder
    {
        return $query->where('type', 'fragment');
    }

    /**
     * Scope to global templates (no owner).
     *
     * @param  Builder<PromptTemplate>  $query
     * @return Builder<PromptTemplate>
     */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('templateable_type')
            ->whereNull('templateable_id');
    }

    /**
     * Scope to order by sort_order.
     *
     * @param  Builder<PromptTemplate>  $query
     * @return Builder<PromptTemplate>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Check if this template is a fragment.
     */
    public function isFragment(): bool
    {
        return $this->type === 'fragment';
    }

    /**
     * Check if this template is global (has no owner).
     */
    public function isGlobal(): bool
    {
        return $this->templateable_type === null && $this->templateable_id === null;
    }

    /**
     * Render this template with the given variables.
     *
     * @param  array<string, mixed>  $variables
     */
    public function render(array $variables = []): string
    {
        return PromptPipeline::make($this)
            ->withVariables($variables)
            ->render();
    }

    /**
     * Get a metadata value using dot notation.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return data_get($this->metadata, $key, $default);
    }

    /**
     * Set a metadata value using dot notation.
     */
    public function setMeta(string $key, mixed $value): self
    {
        $metadata = $this->metadata ?? [];
        data_set($metadata, $key, $value);
        $this->metadata = $metadata;

        return $this;
    }
}
