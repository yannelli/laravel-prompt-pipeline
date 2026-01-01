<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Rendering;

use Twig\Sandbox\SecurityPolicy;

class SandboxPolicy
{
    /**
     * Allowed Twig tags.
     *
     * @var array<string>
     */
    protected static array $allowedTags = [
        'if',
        'else',
        'elseif',
        'for',
        'set',
        'block',
        'autoescape',
        'verbatim',
        'apply',
        'do',
        'with',
    ];

    /**
     * Allowed Twig filters.
     *
     * @var array<string>
     */
    protected static array $allowedFilters = [
        // String filters
        'capitalize',
        'lower',
        'upper',
        'title',
        'trim',
        'nl2br',
        'striptags',
        'escape',
        'e',
        'raw',
        'replace',
        'split',
        'join',
        'reverse',
        'slice',
        'format',
        'number_format',
        'date',
        'date_modify',

        // Array filters
        'first',
        'last',
        'length',
        'sort',
        'keys',
        'merge',
        'batch',
        'column',
        'filter',
        'map',
        'reduce',

        // Type filters
        'abs',
        'round',
        'default',
        'json_encode',

        // Custom filters (added by package)
        'json',
        'deduplicate',
        'deduplicate_whitespace',
        'deduplicate_lines',
    ];

    /**
     * Allowed Twig functions.
     *
     * @var array<string>
     */
    protected static array $allowedFunctions = [
        // Core functions
        'range',
        'cycle',
        'constant',
        'random',
        'date',
        'min',
        'max',

        // XML functions (added by package)
        'xml',
        'xml_open',
        'xml_close',
        'cdata',

        // Claude tag functions
        'system_instructions',
        'instructions',
        'context',
        'task',
        'constraints',
        'rules',
        'output_format',
        'user_message',
        'query',

        // Chain of thought
        'thinking',
        'thinking_open',
        'thinking_close',
        'reasoning',
        'answer',
        'answer_open',
        'answer_close',
        'scratchpad',
        'cot_basic',
        'cot_guided',
        'cot_structured',

        // Documents
        'document',
        'documents_open',
        'documents_close',
        'document_content',
        'source',

        // Examples
        'examples_open',
        'examples_close',
        'example',
        'example_pair',

        // Fragments
        'fragment',

        // Utilities
        'json',
        'deduplicate',
        'deduplicate_whitespace',
        'deduplicate_lines',
    ];

    /**
     * Blocked tags that should never be allowed.
     *
     * @var array<string>
     */
    protected static array $blockedTags = [
        'include',
        'extends',
        'embed',
        'import',
        'use',
        'macro',
        'from',
        'sandbox',
    ];

    /**
     * Blocked functions that should never be allowed.
     *
     * @var array<string>
     */
    protected static array $blockedFunctions = [
        'include',
        'source',
        'parent',
        'block',
        'attribute',
        'template_from_string',
    ];

    /**
     * Create the security policy for Twig sandbox.
     */
    public static function create(): SecurityPolicy
    {
        $config = config('prompt-pipeline.sandbox', []);

        $filters = array_merge(
            self::$allowedFilters,
            $config['allowed_filters'] ?? []
        );

        $functions = array_merge(
            self::$allowedFunctions,
            $config['allowed_functions'] ?? []
        );

        return new SecurityPolicy(
            self::$allowedTags,
            $filters,
            [], // methods (handled separately)
            [], // properties (handled separately)
            $functions
        );
    }

    /**
     * Get all allowed tags.
     *
     * @return array<string>
     */
    public static function getAllowedTags(): array
    {
        return self::$allowedTags;
    }

    /**
     * Get all allowed filters.
     *
     * @return array<string>
     */
    public static function getAllowedFilters(): array
    {
        $config = config('prompt-pipeline.sandbox', []);

        return array_merge(
            self::$allowedFilters,
            $config['allowed_filters'] ?? []
        );
    }

    /**
     * Get all allowed functions.
     *
     * @return array<string>
     */
    public static function getAllowedFunctions(): array
    {
        $config = config('prompt-pipeline.sandbox', []);

        return array_merge(
            self::$allowedFunctions,
            $config['allowed_functions'] ?? []
        );
    }

    /**
     * Check if a tag is blocked.
     */
    public static function isTagBlocked(string $tag): bool
    {
        return in_array($tag, self::$blockedTags, true);
    }

    /**
     * Check if a function is blocked.
     */
    public static function isFunctionBlocked(string $function): bool
    {
        return in_array($function, self::$blockedFunctions, true);
    }
}
