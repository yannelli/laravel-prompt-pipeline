<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    |
    | Configuration for the database table and model used to store prompt
    | templates.
    |
    */

    'table_name' => 'prompt_pipeline_templates',

    'model' => \Yannelli\PromptPipeline\Models\PromptTemplate::class,

    /*
    |--------------------------------------------------------------------------
    | Rendering
    |--------------------------------------------------------------------------
    |
    | Configure the Twig template caching behavior.
    |
    */

    'cache' => [
        'enabled' => env('PROMPT_PIPELINE_CACHE', true),
        'path' => storage_path('framework/cache/prompt-pipeline'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fragments
    |--------------------------------------------------------------------------
    |
    | Configuration for fragment handling. max_depth controls how many levels
    | deep fragments can be nested to prevent infinite recursion.
    |
    */

    'fragments' => [
        'max_depth' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sandbox Policy
    |--------------------------------------------------------------------------
    |
    | Additional filters and functions to whitelist beyond the defaults.
    | Blocked items (include, extends, etc.) cannot be overridden.
    |
    */

    'sandbox' => [
        'allowed_filters' => [],
        'allowed_functions' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Variable Providers
    |--------------------------------------------------------------------------
    |
    | Register classes that implement VariableProvider to automatically
    | inject variables into all templates.
    |
    */

    'providers' => [
        \Yannelli\PromptPipeline\Providers\DateTimeVariables::class,
        \Yannelli\PromptPipeline\Providers\EnvironmentVariables::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Processors
    |--------------------------------------------------------------------------
    |
    | Define processors to run by default on all templates. Can be overridden
    | per-pipeline using withoutDefaultProcessors().
    |
    | Format: ProcessorClass::class => ['config' => 'value']
    | Or just: ProcessorClass::class (no config)
    |
    */

    'processors' => [
        'input' => [
            // \Yannelli\PromptPipeline\Processing\Input\TrimWhitespace::class,
            // \Yannelli\PromptPipeline\Processing\Input\NormalizeLineBreaks::class,
        ],
        'output' => [
            // \Yannelli\PromptPipeline\Processing\Output\TrimOutput::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Exclusions
    |--------------------------------------------------------------------------
    |
    | Global exclusions for fragments and XML tags. These will be excluded
    | from all renders unless specifically included.
    |
    */

    'exclusions' => [
        'fragments' => [],
        'tags' => [],
        'provider' => null, // Class implementing ExclusionProvider
    ],

    /*
    |--------------------------------------------------------------------------
    | Deduplication Defaults
    |--------------------------------------------------------------------------
    |
    | Default configuration for the deduplication processor and filter.
    |
    */

    'deduplication' => [
        'default_strategies' => ['whitespace', 'blankLines'],

        'whitespace' => [
            'normalize_spaces' => true,
            'trim_lines' => true,
            'preserve_indentation' => false,
        ],

        'blankLines' => [
            'max_consecutive' => 2,
            'trim_start' => true,
            'trim_end' => true,
        ],

        'duplicateLines' => [
            'case_sensitive' => false,
            'ignore_whitespace' => true,
        ],

        'duplicateSentences' => [
            'case_sensitive' => false,
            'similarity_threshold' => 0.85,
            'keep_first' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Structure Defaults
    |--------------------------------------------------------------------------
    |
    | Configuration for XmlBuilder behavior.
    |
    */

    'structure' => [
        'xml_method_case' => 'snake', // snake, camel, preserve
        'xml_newlines' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Missing Variable Behavior
    |--------------------------------------------------------------------------
    |
    | How to handle undefined variables in templates.
    | Options: 'empty', 'error', 'keep'
    |
    | - 'empty': Undefined variables render as empty string (default)
    | - 'error': Throw MissingVariableException
    | - 'keep': Keep the Twig expression (e.g., {{ undefined }})
    |
    */

    'missing_variable_behavior' => 'empty',
];
