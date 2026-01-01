<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Exclusions;

class ExclusionManager
{
    /**
     * Global excluded fragments.
     *
     * @var array<string>
     */
    protected static array $excludedFragments = [];

    /**
     * Global excluded tags.
     *
     * @var array<string>
     */
    protected static array $excludedTags = [];

    /**
     * Whether config has been loaded.
     */
    protected static bool $configLoaded = false;

    /**
     * Add a fragment to the global exclusion list.
     */
    public static function excludeFragment(string $slug): void
    {
        self::loadConfig();

        if (! in_array($slug, self::$excludedFragments, true)) {
            self::$excludedFragments[] = $slug;
        }
    }

    /**
     * Add multiple fragments to the global exclusion list.
     *
     * @param  array<string>  $slugs
     */
    public static function excludeFragments(array $slugs): void
    {
        foreach ($slugs as $slug) {
            self::excludeFragment($slug);
        }
    }

    /**
     * Add a tag to the global exclusion list.
     */
    public static function excludeTag(string $tag): void
    {
        self::loadConfig();

        if (! in_array($tag, self::$excludedTags, true)) {
            self::$excludedTags[] = $tag;
        }
    }

    /**
     * Add multiple tags to the global exclusion list.
     *
     * @param  array<string>  $tags
     */
    public static function excludeTags(array $tags): void
    {
        foreach ($tags as $tag) {
            self::excludeTag($tag);
        }
    }

    /**
     * Check if a fragment is excluded.
     */
    public static function isFragmentExcluded(string $slug): bool
    {
        self::loadConfig();

        return in_array($slug, self::$excludedFragments, true);
    }

    /**
     * Check if a tag is excluded.
     */
    public static function isTagExcluded(string $tag): bool
    {
        self::loadConfig();

        return in_array($tag, self::$excludedTags, true);
    }

    /**
     * Get all excluded fragments.
     *
     * @return array<string>
     */
    public static function excludedFragments(): array
    {
        self::loadConfig();

        return self::$excludedFragments;
    }

    /**
     * Get all excluded tags.
     *
     * @return array<string>
     */
    public static function excludedTags(): array
    {
        self::loadConfig();

        return self::$excludedTags;
    }

    /**
     * Remove a fragment from the exclusion list.
     */
    public static function removeFragmentExclusion(string $slug): void
    {
        self::$excludedFragments = array_filter(
            self::$excludedFragments,
            fn ($s) => $s !== $slug
        );
    }

    /**
     * Remove a tag from the exclusion list.
     */
    public static function removeTagExclusion(string $tag): void
    {
        self::$excludedTags = array_filter(
            self::$excludedTags,
            fn ($t) => $t !== $tag
        );
    }

    /**
     * Clear all exclusions.
     */
    public static function clearAll(): void
    {
        self::$excludedFragments = [];
        self::$excludedTags = [];
        self::$configLoaded = true;
    }

    /**
     * Reset to config defaults.
     */
    public static function reset(): void
    {
        self::$configLoaded = false;
        self::$excludedFragments = [];
        self::$excludedTags = [];
    }

    /**
     * Load exclusions from config if not already loaded.
     */
    protected static function loadConfig(): void
    {
        if (self::$configLoaded) {
            return;
        }

        $config = config('prompt-pipeline.exclusions', []);

        self::$excludedFragments = $config['fragments'] ?? [];
        self::$excludedTags = $config['tags'] ?? [];

        self::$configLoaded = true;
    }

    /**
     * Get an ExclusionSet from the current global exclusions.
     */
    public static function toSet(): ExclusionSet
    {
        return new ExclusionSet(
            self::excludedFragments(),
            self::excludedTags()
        );
    }
}
