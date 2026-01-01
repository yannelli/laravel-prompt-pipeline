<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Rendering;

use Illuminate\Database\Eloquent\Model;
use Yannelli\PromptPipeline\Events\FragmentResolved;
use Yannelli\PromptPipeline\Exceptions\CircularFragmentException;
use Yannelli\PromptPipeline\Exceptions\FragmentDepthExceededException;
use Yannelli\PromptPipeline\Exceptions\FragmentNotFoundException;
use Yannelli\PromptPipeline\Models\PromptTemplate;

class FragmentRegistry
{
    /**
     * Runtime-registered fragments.
     *
     * @var array<string, string>
     */
    protected array $runtimeFragments = [];

    /**
     * Current owner model for scoped fragment lookups.
     */
    protected ?Model $owner = null;

    /**
     * Current fragment resolution chain (for circular detection).
     *
     * @var array<string>
     */
    protected array $resolutionChain = [];

    /**
     * Current nesting depth.
     */
    protected int $currentDepth = 0;

    /**
     * Excluded fragment slugs.
     *
     * @var array<string>
     */
    protected array $excludedFragments = [];

    /**
     * Set the owner model for scoped lookups.
     */
    public function setOwner(?Model $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * Get the current owner.
     */
    public function getOwner(): ?Model
    {
        return $this->owner;
    }

    /**
     * Register a runtime fragment.
     */
    public function register(string $slug, string $content): self
    {
        $this->runtimeFragments[$slug] = $content;

        return $this;
    }

    /**
     * Unregister a runtime fragment.
     */
    public function unregister(string $slug): self
    {
        unset($this->runtimeFragments[$slug]);

        return $this;
    }

    /**
     * Set excluded fragments.
     *
     * @param  array<string>  $slugs
     */
    public function setExcluded(array $slugs): self
    {
        $this->excludedFragments = $slugs;

        return $this;
    }

    /**
     * Check if a fragment is excluded.
     */
    public function isExcluded(string $slug): bool
    {
        return in_array($slug, $this->excludedFragments, true);
    }

    /**
     * Resolve a fragment by slug.
     *
     * @param  array<string, mixed>  $variables
     *
     * @throws FragmentNotFoundException
     * @throws CircularFragmentException
     * @throws FragmentDepthExceededException
     */
    public function resolve(string $slug, array $variables = []): string
    {
        // Check if excluded
        if ($this->isExcluded($slug)) {
            return '';
        }

        // Check max depth
        $maxDepth = (int) config('prompt-pipeline.fragments.max_depth', 3);
        if ($this->currentDepth >= $maxDepth) {
            throw FragmentDepthExceededException::maxDepthReached($maxDepth);
        }

        // Check for circular reference
        if (in_array($slug, $this->resolutionChain, true)) {
            throw CircularFragmentException::detected([...$this->resolutionChain, $slug]);
        }

        // Get fragment content
        $content = $this->getFragmentContent($slug);

        if ($content === null) {
            throw FragmentNotFoundException::forSlug($slug);
        }

        return $content;
    }

    /**
     * Begin resolving a fragment (track depth).
     */
    public function beginResolve(string $slug): void
    {
        $this->resolutionChain[] = $slug;
        $this->currentDepth++;
    }

    /**
     * End resolving a fragment.
     */
    public function endResolve(): void
    {
        array_pop($this->resolutionChain);
        $this->currentDepth--;
    }

    /**
     * Reset the resolution state.
     */
    public function reset(): void
    {
        $this->resolutionChain = [];
        $this->currentDepth = 0;
    }

    /**
     * Get fragment content from various sources.
     */
    protected function getFragmentContent(string $slug): ?string
    {
        // 1. Check runtime fragments
        if (isset($this->runtimeFragments[$slug])) {
            return $this->runtimeFragments[$slug];
        }

        // 2. Check owner-scoped fragments
        if ($this->owner !== null) {
            $fragment = $this->findOwnerFragment($slug);
            if ($fragment !== null) {
                FragmentResolved::dispatch($slug, $fragment);

                return $fragment->content;
            }
        }

        // 3. Check global fragments
        $fragment = $this->findGlobalFragment($slug);
        if ($fragment !== null) {
            FragmentResolved::dispatch($slug, $fragment);

            return $fragment->content;
        }

        return null;
    }

    /**
     * Find a fragment owned by the current owner.
     */
    protected function findOwnerFragment(string $slug): ?PromptTemplate
    {
        if ($this->owner === null) {
            return null;
        }

        $modelClass = config('prompt-pipeline.model', PromptTemplate::class);

        return $modelClass::query()
            ->where('templateable_type', $this->owner->getMorphClass())
            ->where('templateable_id', $this->owner->getKey())
            ->where('type', 'fragment')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Find a global fragment (no owner).
     */
    protected function findGlobalFragment(string $slug): ?PromptTemplate
    {
        $modelClass = config('prompt-pipeline.model', PromptTemplate::class);

        return $modelClass::query()
            ->whereNull('templateable_type')
            ->whereNull('templateable_id')
            ->where('type', 'fragment')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get all available fragments.
     *
     * @return array<string, PromptTemplate>
     */
    public function all(): array
    {
        $fragments = [];

        // Add database fragments
        $modelClass = config('prompt-pipeline.model', PromptTemplate::class);
        $query = $modelClass::query()
            ->where('type', 'fragment')
            ->where('is_active', true);

        if ($this->owner !== null) {
            $query->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->where('templateable_type', $this->owner->getMorphClass())
                        ->where('templateable_id', $this->owner->getKey());
                })->orWhere(function ($sub) {
                    $sub->whereNull('templateable_type')
                        ->whereNull('templateable_id');
                });
            });
        } else {
            $query->whereNull('templateable_type')
                ->whereNull('templateable_id');
        }

        foreach ($query->get() as $fragment) {
            $fragments[$fragment->slug] = $fragment;
        }

        return $fragments;
    }

    /**
     * Clear all runtime fragments.
     */
    public function clearRuntime(): void
    {
        $this->runtimeFragments = [];
    }
}
