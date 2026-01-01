<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Exclusions;

class ExclusionSet
{
    /**
     * @param  array<string>  $fragments
     * @param  array<string>  $tags
     */
    public function __construct(
        protected array $fragments = [],
        protected array $tags = []
    ) {}

    /**
     * Create a new ExclusionSet instance.
     */
    public static function make(): static
    {
        return new static;
    }

    /**
     * Add fragments to exclude.
     *
     * @param  array<string>  $slugs
     */
    public function excludeFragments(array $slugs): static
    {
        return new static(
            array_unique([...$this->fragments, ...$slugs]),
            $this->tags
        );
    }

    /**
     * Add a single fragment to exclude.
     */
    public function excludeFragment(string $slug): static
    {
        return $this->excludeFragments([$slug]);
    }

    /**
     * Add tags to exclude.
     *
     * @param  array<string>  $tags
     */
    public function excludeTags(array $tags): static
    {
        return new static(
            $this->fragments,
            array_unique([...$this->tags, ...$tags])
        );
    }

    /**
     * Add a single tag to exclude.
     */
    public function excludeTag(string $tag): static
    {
        return $this->excludeTags([$tag]);
    }

    /**
     * Merge with another ExclusionSet.
     */
    public function merge(ExclusionSet $other): static
    {
        return new static(
            array_unique([...$this->fragments, ...$other->getFragments()]),
            array_unique([...$this->tags, ...$other->getTags()])
        );
    }

    /**
     * Get excluded fragments.
     *
     * @return array<string>
     */
    public function getFragments(): array
    {
        return $this->fragments;
    }

    /**
     * Get excluded tags.
     *
     * @return array<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * Check if a fragment is excluded.
     */
    public function isFragmentExcluded(string $slug): bool
    {
        return in_array($slug, $this->fragments, true);
    }

    /**
     * Check if a tag is excluded.
     */
    public function isTagExcluded(string $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }

    /**
     * Check if any exclusions are set.
     */
    public function isEmpty(): bool
    {
        return empty($this->fragments) && empty($this->tags);
    }

    /**
     * Create from configuration.
     */
    public static function fromConfig(): static
    {
        $config = config('prompt-pipeline.exclusions', []);

        return new static(
            $config['fragments'] ?? [],
            $config['tags'] ?? []
        );
    }
}
