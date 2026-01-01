# Laravel Prompt Pipeline

A Laravel 12 package for managing LLM prompt templates with safe Twig rendering, composable fragments, Claude-optimized structure helpers, chainable input/output processing, tag/fragment exclusions, and content deduplication.

## Table of Contents

1. [Installation](#installation)
2. [Database Schema](#database-schema)
3. [Core Concepts](#core-concepts)
4. [Models & Traits](#models--traits)
5. [XmlBuilder](#xmlbuilder)
6. [Twig Functions](#twig-functions)
7. [Fragments](#fragments)
8. [Processing Pipeline](#processing-pipeline)
9. [Exclusion System](#exclusion-system)
10. [Deduplication](#deduplication)
11. [Configuration](#configuration)
12. [Artisan Commands](#artisan-commands)
13. [Exceptions](#exceptions)
14. [Events](#events)
15. [Full Integration Example](#full-integration-example)
16. [Testing](#testing)
17. [License](#license)

---

## Installation

```bash
composer require yannelli/laravel-prompt-pipeline
```

```bash
php artisan vendor:publish --provider="Yannelli\PromptPipeline\PromptPipelineServiceProvider"
php artisan migrate
```

---

## Database Schema

### `prompt_pipeline_templates` table

| Column            | Type                  | Notes                          |
|-------------------|-----------------------|--------------------------------|
| id                | ulid                  | Primary key                    |
| name              | string(255)           | Human readable identifier      |
| slug              | string(255), nullable | Machine-friendly lookup        |
| content           | text                  | Twig template content          |
| type              | string(100), nullable | Categorization (system, fragment, etc.) |
| metadata          | json, nullable        | Processors, exclusions, etc.   |
| templateable_type | string(255)           | Polymorphic morph type         |
| templateable_id   | string(36)            | Polymorphic morph ID           |
| is_active         | boolean               | Default: true                  |
| sort_order        | integer               | Default: 0                     |
| created_at        | timestamp             |                                |
| updated_at        | timestamp             |                                |

**Indexes:**

- Composite: `(templateable_type, templateable_id, type)`
- Composite unique: `(templateable_type, templateable_id, slug)`

---

## Core Concepts

### Prompt Templates

Database-stored Twig templates that can belong to any Eloquent model via polymorphic relationship. Templates support variable interpolation, composable fragments, and XML structure helpers.

### Fragments

Reusable template snippets (type = 'fragment') that can be embedded in other templates. Safe alternative to Twig's native `include` which is blocked for security.

### Processing Pipeline

Chainable processors that transform data before rendering (input processors) and after rendering (output processors). Follows Laravel's Pipeline pattern.

### Exclusions

System for excluding specific fragments or XML tags from rendering. Supports global config, per-render overrides, and custom providers.

### Safe Twig Rendering

Twig templating in sandbox mode with explicitly whitelisted functions, filters, and tags. No arbitrary PHP execution.

---

## Models & Traits

### PromptTemplate Model

```php
namespace Yannelli\PromptPipeline\Models;

class PromptTemplate extends Model
{
    protected $table = 'prompt_pipeline_templates';

    // Relationships
    public function templateable(): MorphTo

    // Scopes
    public function scopeActive(Builder $query): Builder
    public function scopeOfType(Builder $query, string $type): Builder
    public function scopeBySlug(Builder $query, string $slug): Builder
    public function scopeFragments(Builder $query): Builder

    // Methods
    public function render(array $variables = []): string
    public function isFragment(): bool

    // Casts
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
```

### HasPromptTemplates Trait

Apply to any model that owns templates.

```php
namespace Yannelli\PromptPipeline\Traits;

trait HasPromptTemplates
{
    // Relationships
    public function promptTemplates(): MorphMany
    public function activePromptTemplates(): MorphMany

    // Scoped Relationships
    public function promptTemplatesOfType(string $type): MorphMany
    public function fragments(): MorphMany

    // Finders
    public function findPromptTemplate(string $slugOrId): ?PromptTemplate
    public function findFragment(string $slugOrId): ?PromptTemplate

    // Creators
    public function createPromptTemplate(array $attributes): PromptTemplate
    public function createFragment(string $slug, string $content, array $attributes = []): PromptTemplate

    // Rendering
    public function renderPromptTemplate(string $slugOrId, array $variables = []): string

    // Override in model to provide automatic variables
    public function promptTemplateVariables(): array
}
```

**Usage:**

```php
use Yannelli\PromptPipeline\Traits\HasPromptTemplates;

class Organization extends Model
{
    use HasPromptTemplates;

    public function promptTemplateVariables(): array
    {
        return [
            'organization_name' => $this->name,
            'organization_npi' => $this->npi,
        ];
    }
}
```

---

## XmlBuilder

Fluent builder for constructing XML-tagged prompt structures. Supports both predefined methods and dynamic tag creation via `__call()`.

### Basic Usage

```php
use Yannelli\PromptPipeline\Structure\XmlBuilder;

$prompt = XmlBuilder::make()
    ->systemInstructions('You are a helpful assistant.')
    ->context($patientContext)
    ->task('Summarize the encounter.')
    ->build();
```

### Dynamic Tags

Any undefined method becomes an XML tag automatically. Method names convert from camelCase to snake_case.

```php
$prompt = XmlBuilder::make()
    ->patientDemographics('Name: John Doe')      // <patient_demographics>
    ->clinicalFindings($findings)                 // <clinical_findings>
    ->myCustomSection('content')                  // <my_custom_section>
    ->build();
```

### Method Signatures

All tag methods accept the same signature:

```php
// Content only
->tagName('content')

// Content with attributes
->tagName('content', ['id' => '123'])

// Closure for nested content
->tagName(function (XmlBuilder $xml) {
    $xml->nested('content');
})

// Empty tag
->tagName()

// Attributes only
->tagName(null, ['status' => 'pending'])
```

### Core Methods

```php
class XmlBuilder
{
    // Factory
    public static function make(): static

    // Core tag method (all others delegate to this)
    public function tag(string $name, string|Closure|null $content = null, array $attributes = []): static

    // Raw content (no wrapping)
    public function raw(string $content): static

    // Blank line
    public function blank(): static

    // Conditional tag
    public function when(bool $condition, string $name, string|Closure|null $content = null, array $attributes = []): static

    // Manual open/close
    public function open(string $tag, array $attributes = []): static
    public function close(string $tag): static

    // Build final string
    public function build(): string

    // Get as array (for inspection)
    public function toArray(): array

    // Case transformation
    public function preserveCase(): static
    public function useSnakeCase(): static

    // Static helper
    public static function wrap(string $tag, string $content, array $attributes = []): string
}
```

### Predefined Claude Methods

These are explicitly defined for consistency and IDE autocomplete:

```php
// System & Instructions
->systemInstructions(string $content): static
->instructions(string $content): static
->task(string $content): static
->context(string $content, ?string $label = null): static
->constraints(array $items): static
->rules(array $items): static

// Chain of Thought
->thinking(?string $content = null): static
->reasoning(?string $content = null): static
->answer(?string $content = null): static
->scratchpad(?string $content = null): static

// Documents
->document(string $content, array $attributes = []): static
->documents(array $docs, string $contentKey = 'content', ?string $nameKey = 'name'): static
->documentContent(string $content): static
->source(string $value): static

// Examples (Multishot)
->example(string|array $content, ?string $label = null): static
->examples(array $examples): static
->examplePair(string $input, string $output, ?string $label = null): static

// Output
->outputFormat(string|array $schema): static

// User Input
->userMessage(string $content): static
->query(string $content): static

// Utilities
->cdata(string $content): static
```

### Chain of Thought Helpers

```php
// Add basic CoT instruction text
->cotBasic(): static
// Adds: "Think step-by-step before providing your answer."

// Add guided CoT with specific steps
->cotGuided(array $steps): static
// Adds numbered steps

// Add structured CoT instruction
->cotStructured(): static
// Adds instructions to use <thinking> and <answer> tags
```

### Document Helpers

```php
// Wrap multiple documents with proper structure
XmlBuilder::make()
    ->documents([
        ['name' => 'intake.pdf', 'content' => '...'],
        ['name' => 'notes.pdf', 'content' => '...'],
    ])
    ->build();
```

**Output:**

```xml
<documents>
<document name="intake.pdf">
<document_content>...</document_content>
</document>
<document name="notes.pdf">
<document_content>...</document_content>
</document>
</documents>
```

### Example (Multishot) Helpers

```php
// Single example pair
XmlBuilder::make()
    ->examplePair(
        input: 'Patient reports sadness for 2 weeks',
        output: '{"symptom": "depressed_mood"}'
    )
    ->build();

// Multiple examples
XmlBuilder::make()
    ->examples([
        ['input' => '...', 'output' => '...'],
        ['input' => '...', 'output' => '...'],
    ])
    ->build();
```

**Output:**

```xml
<examples>
<example>
<input>Patient reports sadness for 2 weeks</input>
<output>{"symptom": "depressed_mood"}</output>
</example>
</examples>
```

### Long Context Pattern

For large document processing, Anthropic recommends documents at top, query at bottom.

```php
XmlBuilder::make()
    ->documents($largeDocumentArray)     // TOP: Long content first
    ->instructions($taskInstructions)    // MIDDLE: Instructions
    ->scratchpad()                       // For recall assistance
    ->thinking()                         // CoT
    ->query($userQuery)                  // BOTTOM: Query last (30% improvement)
    ->build();
```

### Full XmlBuilder Example

```php
$prompt = XmlBuilder::make()
    ->systemInstructions('You are a board-certified psychiatrist.')
    ->constraints([
        'Maintain HIPAA compliance',
        'Use clinical terminology',
        'Do not diagnose without sufficient evidence',
    ])
    ->patientContext(function ($xml) use ($patient) {
        $xml->name($patient->full_name);
        $xml->dob($patient->dob->format('Y-m-d'));
        $xml->mrn($patient->mrn);
    })
    ->documents($encounterDocuments)
    ->examples([
        [
            'input' => 'Patient reports feeling hopeless',
            'output' => '{"finding": "hopelessness", "category": "mood"}',
        ],
    ])
    ->context($clinicalHistory)
    ->cotStructured()
    ->thinking()
    ->task('Generate a clinical assessment.')
    ->outputFormat($schema)
    ->build();
```

---

## Twig Functions

All functions are available in templates automatically.

### XML Tags

| Function | Signature | Output |
|----------|-----------|--------|
| `xml` | `xml(tag, content, attrs = {})` | `<tag>content</tag>` |
| `xml_open` | `xml_open(tag, attrs = {})` | `<tag>` |
| `xml_close` | `xml_close(tag)` | `</tag>` |
| `cdata` | `cdata(content)` | `<![CDATA[content]]>` |

### Claude-Specific Tags

| Function | Output |
|----------|--------|
| `system_instructions(content)` | `<system_instructions>...</system_instructions>` |
| `instructions(content)` | `<instructions>...</instructions>` |
| `context(content, label = null)` | `<context>...</context>` |
| `task(content)` | `<task>...</task>` |
| `constraints(items)` | `<constraints>- item1\n- item2</constraints>` |
| `rules(items)` | `<rules>...</rules>` |
| `output_format(content)` | `<output_format>...</output_format>` |
| `user_message(content)` | `<user_message>...</user_message>` |
| `query(content)` | `<query>...</query>` |

### Chain of Thought

| Function | Output |
|----------|--------|
| `thinking(content = null)` | `<thinking>content</thinking>` |
| `thinking_open()` | `<thinking>` |
| `thinking_close()` | `</thinking>` |
| `reasoning(content = null)` | `<reasoning>...</reasoning>` |
| `answer(content = null)` | `<answer>...</answer>` |
| `answer_open()` | `<answer>` |
| `answer_close()` | `</answer>` |
| `scratchpad()` | `<scratchpad></scratchpad>` |
| `cot_basic()` | "Think step-by-step..." |
| `cot_guided(steps)` | Numbered step instructions |
| `cot_structured()` | Instructions to use thinking/answer tags |

### Documents

| Function | Output |
|----------|--------|
| `document(content, attrs = {})` | `<document>...</document>` |
| `documents_open()` | `<documents>` |
| `documents_close()` | `</documents>` |
| `document_content(content)` | `<document_content>...</document_content>` |
| `source(value)` | `<source>value</source>` |

### Examples (Multishot)

| Function | Output |
|----------|--------|
| `examples_open()` | `<examples>` |
| `examples_close()` | `</examples>` |
| `example(content, label = null)` | `<example>...</example>` |
| `example_pair(input, output)` | `<example><input>...<output>...</example>` |

### Fragments

| Function | Output |
|----------|--------|
| `fragment(slug, vars = {})` | Resolved fragment content |

### Utilities

| Function | Output |
|----------|--------|
| `json(value, pretty = false)` | JSON encoded string |
| `deduplicate(content)` | Deduplicated content |
| `deduplicate_whitespace(content)` | Whitespace normalized |
| `deduplicate_lines(content)` | Duplicate lines removed |

---

## Fragments

Fragments are reusable template pieces stored with `type = 'fragment'`.

### Creating Fragments

```php
$organization->createFragment(
    slug: 'hipaa_reminder',
    content: 'Remember: Never include patient identifiers in your response.'
);

$organization->createFragment(
    slug: 'json_output',
    content: <<<'TWIG'
{{ xml_open('output_format') }}
Respond ONLY with valid JSON matching this schema:
```json
{{ schema | json }}
```
{{ xml_close('output_format') }}
TWIG
);
```

### Using Fragments

```twig
{{ fragment('hipaa_reminder') }}

{{ fragment('json_output', { schema: my_schema }) }}
```

### Fragment Resolution Order

1. Fragments owned by the same templateable (model-specific)
2. Global fragments (templateable_type = null, templateable_id = null)
3. Runtime-registered fragments

### Fragment Depth Limit

Fragments can include other fragments, but nesting is limited (default: 3 levels) to prevent circular references.

### Fragment Security

- Fragments pass through the same sandbox policy as main templates
- Circular references are detected and throw `CircularFragmentException`
- Fragments inherit parent variables but can override them

---

## Processing Pipeline

### Basic Usage

```php
use Yannelli\PromptPipeline\Facades\PromptPipeline;

// From a PromptTemplate model
$rendered = PromptPipeline::make($promptTemplate)
    ->withVariables(['patient_name' => 'John'])
    ->render();

// From raw string
$rendered = PromptPipeline::fromString($templateString)
    ->withVariables($variables)
    ->render();

// Process LLM output (no template, just processors)
$cleaned = PromptPipeline::forOutput($llmResponse)
    ->outputProcessor(ExtractJsonBlock::class)
    ->run();
```

### Pipeline Methods

```php
class Pipeline
{
    // Factory methods
    public static function make(PromptTemplate $template): static
    public static function fromString(string $template): static
    public static function processOutput(string $output): static

    // Variables
    public function withVariables(array $variables): static
    public function withModel(Model $model): static

    // Processors
    public function inputProcessor(string $class, array $config = []): static
    public function outputProcessor(string $class, array $config = []): static
    public function withoutDefaultProcessors(): static

    // Exclusions
    public function excludeFragments(array $slugs): static
    public function excludeTags(array $tags): static
    public function withExclusions(ExclusionSet $set): static
    public function withExclusionProvider(ExclusionProvider $provider): static

    // Execute
    public function render(): string
    public function run(): string  // Alias for output-only processing
    public function validate(): ValidationResult
}
```

### Execution Flow

**Full Render:**

1. Collect variables (system providers → model → user)
2. Run input processor chain on variables
3. Resolve fragments (respecting exclusions)
4. Render Twig template (respecting tag exclusions)
5. Run output processor chain on rendered string
6. Return final string

**Output-Only:**

1. Run output processor chain on provided string
2. Return processed string

### Built-in Input Processors

| Class | Purpose | Config |
|-------|---------|--------|
| `TrimWhitespace` | Trims string values recursively | `recursive: bool` |
| `SanitizeInput` | Escapes potentially dangerous content | `strict: bool` |
| `NormalizeLineBreaks` | Converts \r\n to \n | none |
| `JsonEncodeArrays` | JSON encodes array values | `pretty: bool` |
| `EscapeXmlContent` | Escapes XML special chars in specified keys | `keys: array` |

### Built-in Output Processors

| Class | Purpose | Config |
|-------|---------|--------|
| `TrimOutput` | Trims final output | none |
| `ExtractJsonBlock` | Extracts JSON from markdown fences | `fallback_raw: bool` |
| `StripMarkdownFences` | Removes code fences | `preserve_content: bool` |
| `NormalizeWhitespace` | Collapses excessive whitespace | `max_newlines: int` |
| `ExtractXmlTag` | Extracts content from specific tag | `tag: string` |
| `Deduplicate` | Removes duplicate content | `strategies: array` |

### Custom Processor

```php
use Yannelli\PromptPipeline\Contracts\InputProcessor;

class InjectPatientContext implements InputProcessor
{
    public function __construct(
        protected array $config = []
    ) {}

    public function process(array $variables): array
    {
        if (!isset($variables['patient_id'])) {
            return $variables;
        }

        $patient = Patient::find($variables['patient_id']);
        $variables['patient_name'] = $patient->full_name;
        $variables['patient_dob'] = $patient->dob->format('m/d/Y');

        return $variables;
    }
}
```

```php
use Yannelli\PromptPipeline\Contracts\OutputProcessor;

class ExtractClinicalJson implements OutputProcessor
{
    public function process(string $output): string
    {
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $output, $matches)) {
            return $matches[1];
        }

        return $output;
    }
}
```

---

## Exclusion System

Allows excluding specific fragments and XML tags from rendering.

### ExclusionSet

Immutable set of exclusions for per-render use.

```php
use Yannelli\PromptPipeline\Exclusions\ExclusionSet;

$exclusions = ExclusionSet::make()
    ->excludeFragments(['hipaa_reminder', 'legal_disclaimer'])
    ->excludeTags(['scratchpad', 'thinking']);
```

### ExclusionManager

Global exclusion management.

```php
use Yannelli\PromptPipeline\Exclusions\ExclusionManager;

// Add global exclusions
ExclusionManager::excludeFragment('hipaa_reminder');
ExclusionManager::excludeTag('scratchpad');

// Check exclusions
ExclusionManager::isFragmentExcluded('hipaa_reminder');  // true
ExclusionManager::isTagExcluded('thinking');  // false

// Get all
ExclusionManager::excludedFragments();
ExclusionManager::excludedTags();

// Clear
ExclusionManager::clearAll();
```

### ExclusionProvider Contract

Implement for dynamic exclusions (from database, user settings, etc.).

```php
namespace Yannelli\PromptPipeline\Contracts;

interface ExclusionProvider
{
    public function excludedFragments(): array;
    public function excludedTags(): array;
}
```

**Implementation:**

```php
class UserExclusionProvider implements ExclusionProvider
{
    public function __construct(
        protected User $user
    ) {}

    public function excludedFragments(): array
    {
        return $this->user->getSetting('excluded_fragments', []);
    }

    public function excludedTags(): array
    {
        return $this->user->getSetting('excluded_tags', []);
    }
}
```

### Pipeline Integration

```php
// Using ExclusionSet
PromptPipeline::make($template)
    ->withExclusions($exclusions)
    ->render();

// Using provider
PromptPipeline::make($template)
    ->withExclusionProvider(new UserExclusionProvider($user))
    ->render();

// Inline
PromptPipeline::make($template)
    ->excludeFragments(['hipaa_reminder'])
    ->excludeTags(['thinking', 'scratchpad'])
    ->render();
```

### Exclusion Behavior

**Fragments:** `{{ fragment('hipaa_reminder') }}` renders as empty string when excluded.

**Tags:** `{{ xml('thinking', content) }}` renders as empty string when excluded. The content is NOT rendered.

---

## Deduplication

Removes duplicate content, excessive whitespace, and repeated text from prompts.

### Output Processor

```php
use Yannelli\PromptPipeline\Processing\Output\Deduplicate;

PromptPipeline::make($template)
    ->outputProcessor(Deduplicate::class, [
        'strategies' => ['whitespace', 'blankLines', 'duplicateLines'],
    ])
    ->render();
```

### Available Strategies

#### `whitespace`

Normalizes excessive whitespace.

```php
[
    'normalize_spaces' => true,      // Multiple spaces to single
    'trim_lines' => true,            // Trim trailing whitespace per line
    'preserve_indentation' => false, // Keep leading whitespace
]
```

#### `blankLines`

Removes excessive blank lines.

```php
[
    'max_consecutive' => 2,  // Max blank lines in a row
    'trim_start' => true,    // Remove leading blank lines
    'trim_end' => true,      // Remove trailing blank lines
]
```

#### `duplicateLines`

Removes duplicate consecutive lines.

```php
[
    'case_sensitive' => false,
    'ignore_whitespace' => true,
]
```

#### `duplicateSentences`

Removes duplicate sentences anywhere in text.

```php
[
    'case_sensitive' => false,
    'similarity_threshold' => 0.85,  // 1.0 = exact match only
    'keep_first' => true,
]
```

### Twig Filters

```twig
{{ transcript | deduplicate }}
{{ transcript | deduplicate(['whitespace', 'duplicateLines']) }}
{{ content | deduplicate_whitespace }}
{{ content | deduplicate_lines }}
```

### Standalone Usage

```php
use Yannelli\PromptPipeline\Processing\Output\Deduplicate;

$processor = new Deduplicate([
    'strategies' => ['whitespace', 'blankLines', 'duplicateLines'],
]);

$cleaned = $processor->process($dirtyText);
```

---

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag="prompt-pipeline-config"
```

`config/prompt-pipeline.php`

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */

    'table_name' => 'prompt_pipeline_templates',

    'model' => \Yannelli\PromptPipeline\Models\PromptTemplate::class,

    /*
    |--------------------------------------------------------------------------
    | Rendering
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'enabled' => env('PROMPT_PIPELINE_CACHE', true),
        'path' => storage_path('framework/cache/prompt-pipeline'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fragments
    |--------------------------------------------------------------------------
    */

    'fragments' => [
        'max_depth' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sandbox Policy
    |--------------------------------------------------------------------------
    | Additional filters/functions to whitelist beyond defaults.
    | Blocked items cannot be overridden.
    */

    'sandbox' => [
        'allowed_filters' => [],
        'allowed_functions' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Variable Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        \Yannelli\PromptPipeline\Providers\DateTimeVariables::class,
        \Yannelli\PromptPipeline\Providers\EnvironmentVariables::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Processors
    |--------------------------------------------------------------------------
    */

    'processors' => [
        'input' => [],
        'output' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Exclusions
    |--------------------------------------------------------------------------
    */

    'exclusions' => [
        'fragments' => [],
        'tags' => [],
        'provider' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Deduplication Defaults
    |--------------------------------------------------------------------------
    */

    'deduplication' => [
        'default_strategies' => ['whitespace', 'blankLines'],

        'whitespace' => [
            'normalize_spaces' => true,
            'preserve_indentation' => false,
        ],

        'blankLines' => [
            'max_consecutive' => 2,
        ],

        'duplicateLines' => [
            'case_sensitive' => false,
        ],

        'duplicateSentences' => [
            'similarity_threshold' => 0.85,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Structure Defaults
    |--------------------------------------------------------------------------
    */

    'structure' => [
        'xml_method_case' => 'snake',  // snake, camel, preserve
        'xml_newlines' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Missing Variable Behavior
    |--------------------------------------------------------------------------
    | How to handle undefined variables in templates.
    | Options: 'empty', 'error', 'keep'
    */

    'missing_variable_behavior' => 'empty',
];
```

---

## Artisan Commands

| Command | Purpose |
|---------|---------|
| `prompt-pipeline:validate {--file=} {--string=}` | Validate template syntax and sandbox compliance |
| `prompt-pipeline:render {template} {--variables=}` | Test render a template |
| `prompt-pipeline:variables` | List all registered system variables |
| `prompt-pipeline:fragments {--owner=}` | List available fragments |
| `prompt-pipeline:cache:clear` | Clear compiled template cache |

**Examples:**

```bash
php artisan prompt-pipeline:validate --file=template.twig
php artisan prompt-pipeline:render my-template --variables='{"name":"value"}'
php artisan prompt-pipeline:variables
php artisan prompt-pipeline:fragments --owner="App\Models\Organization:1"
php artisan prompt-pipeline:cache:clear
```

---

## Exceptions

| Exception | When Thrown |
|-----------|-------------|
| `PromptRenderException` | Twig syntax error or render failure |
| `SandboxViolationException` | Template uses blocked feature |
| `FragmentNotFoundException` | Referenced fragment doesn't exist |
| `CircularFragmentException` | Circular fragment reference detected |
| `FragmentDepthExceededException` | Fragment nesting exceeds max_depth |
| `MissingVariableException` | Required variable not provided (when behavior = 'error') |
| `ProcessorException` | Processor fails during execution |

All exceptions extend `PromptPipelineException`.

---

## Events

| Event | Payload |
|-------|---------|
| `PromptTemplateCreated` | `PromptTemplate $template` |
| `PromptTemplateUpdated` | `PromptTemplate $template` |
| `PromptTemplateDeleted` | `PromptTemplate $template` |
| `PromptRendering` | `PromptTemplate $template, array &$variables` |
| `PromptRendered` | `PromptTemplate $template, string $output` |
| `FragmentResolved` | `string $slug, PromptTemplate $fragment` |

---

## Full Integration Example

### Setup

```php
// app/Models/Organization.php

use Yannelli\PromptPipeline\Traits\HasPromptTemplates;

class Organization extends Model
{
    use HasPromptTemplates;

    public function promptTemplateVariables(): array
    {
        return [
            'organization_name' => $this->name,
            'organization_npi' => $this->npi,
        ];
    }
}
```

### Create Templates

```php
// Create fragments
$org->createFragment('clinical_role', 'You are a clinical documentation specialist.');

$org->createFragment('hipaa_reminder', 'Never include patient identifiers in your response.');

$org->createFragment('json_schema', <<<'TWIG'
{{ xml_open('output_format') }}
Respond with valid JSON matching this schema:
{{ schema | json }}
{{ xml_close('output_format') }}
TWIG);

// Create main template
$org->createPromptTemplate([
    'name' => 'Clinical Assessment',
    'slug' => 'clinical_assessment',
    'type' => 'system',
    'content' => <<<'TWIG'
{{ xml_open('system_instructions') }}
{{ fragment('clinical_role') }}

{{ fragment('hipaa_reminder') }}

{{ cot_structured() }}
{{ xml_close('system_instructions') }}

{{ constraints([
    'Use clinical terminology',
    'Include only stated or implied information',
    'Do not fabricate symptoms',
]) }}

{{ xml_open('patient_context') }}
Name: {{ patient_name }}
DOB: {{ patient_dob }}
Encounter: {{ encounter_type }}
{{ xml_close('patient_context') }}

{{ xml('transcript', transcript | deduplicate) }}

{{ thinking() }}

{{ fragment('json_schema', { schema: output_schema }) }}

{{ task('Generate a clinical assessment from the transcript.') }}
TWIG,
]);
```

### Render in Action

```php
// app/Actions/AI/Patient/GenerateClinicalAssessment.php

class GenerateClinicalAssessment
{
    public static function run(Encounter $encounter, User $user): array
    {
        $patient = $encounter->patient;
        $organization = $encounter->organization;

        // Build exclusions from user settings
        $exclusions = ExclusionSet::make()
            ->excludeFragments($user->getSetting('excluded_fragments', []))
            ->excludeTags($user->getSetting('excluded_tags', []));

        // Render prompt
        $prompt = PromptPipeline::make(
            $organization->findPromptTemplate('clinical_assessment')
        )
            ->withModel($patient)
            ->withVariables([
                'transcript' => $encounter->transcript,
                'encounter_type' => $encounter->type->label(),
                'output_schema' => ClinicalAssessmentSchema::toArray(),
            ])
            ->withExclusions($exclusions)
            ->inputProcessor(SanitizeInput::class)
            ->outputProcessor(Deduplicate::class)
            ->outputProcessor(TrimOutput::class)
            ->render();

        // Send to LLM
        $response = PrismAnthropicClient::structured(
            schema: ClinicalAssessmentSchema::forPrism(),
            userMessage: $prompt,
            temperature: 0.3,
        )->asStructured();

        // Process response
        $result = PromptPipeline::forOutput($response->text)
            ->outputProcessor(ExtractJsonBlock::class)
            ->run();

        return json_decode($result, true);
    }
}
```

### Using XmlBuilder Directly

```php
use Yannelli\PromptPipeline\Structure\XmlBuilder;

$prompt = XmlBuilder::make()
    ->systemInstructions('You are a clinical assistant.')
    ->constraints([
        'Maintain HIPAA compliance',
        'Use clinical terminology',
    ])
    ->patientInfo(function ($xml) use ($patient) {
        $xml->name($patient->full_name);
        $xml->dob($patient->dob->format('Y-m-d'));
        $xml->conditions($patient->conditions->pluck('name')->join(', '));
    })
    ->documents($encounter->documents->map(fn ($d) => [
        'name' => $d->filename,
        'content' => $d->content,
    ])->toArray())
    ->cotStructured()
    ->thinking()
    ->task('Summarize this clinical encounter.')
    ->outputFormat($schema)
    ->build();
```

---

## Testing

```bash
composer test
```

---

## Dependencies

### Required

- `php: ^8.3`
- `laravel/framework: ^12.0`
- `twig/twig: ^3.0`
- `spatie/laravel-package-tools: ^1.16`

### Development

- `pestphp/pest: ^3.0`
- `orchestra/testbench: ^10.0`

---

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

---

[Ryan Yannelli](https://ryanyannelli.com)
