# Laravel Prompt Pipeline

A Laravel package for managing LLM prompt templates with safe Twig rendering, composable fragments, Claude-optimized structure helpers, chainable input/output processing, tag/fragment exclusions, and content deduplication.

## Installation

```bash
composer require yannelli/laravel-prompt-pipeline
```

```bash
php artisan vendor:publish --provider="Yannelli\PromptPipeline\PromptPipelineServiceProvider"
php artisan migrate
```

## Quick Start

### Basic Template Rendering

```php
use Yannelli\PromptPipeline\Facades\PromptPipeline;

$result = PromptPipeline::fromString('Hello {{ name }}!')
    ->withVariables(['name' => 'World'])
    ->render();
// Output: Hello World!
```

### XmlBuilder for Claude-Optimized Prompts

```php
use Yannelli\PromptPipeline\Structure\XmlBuilder;

$prompt = XmlBuilder::make()
    ->systemInstructions('You are a helpful assistant.')
    ->context('Background information here.')
    ->constraints(['Be concise', 'Use proper grammar'])
    ->cotStructured()
    ->thinking()
    ->task('Summarize the context.')
    ->build();
```

### Database-Stored Templates

```php
use Yannelli\PromptPipeline\Models\PromptTemplate;
use Yannelli\PromptPipeline\Facades\PromptPipeline;

$template = PromptTemplate::create([
    'name' => 'Greeting',
    'slug' => 'greeting',
    'content' => '{{ system_instructions("Be friendly") }} Hello {{ name }}!',
]);

$result = PromptPipeline::make($template)
    ->withVariables(['name' => 'User'])
    ->render();
```

### Model with Templates (Trait)

```php
use Yannelli\PromptPipeline\Traits\HasPromptTemplates;

class Organization extends Model
{
    use HasPromptTemplates;

    public function promptTemplateVariables(): array
    {
        return [
            'organization_name' => $this->name,
        ];
    }
}

// Usage
$org->createPromptTemplate([
    'name' => 'Welcome',
    'slug' => 'welcome',
    'content' => 'Welcome to {{ organization_name }}!',
]);

$org->renderPromptTemplate('welcome');
```

### Fragments (Reusable Template Pieces)

```php
// Create fragment
$org->createFragment('hipaa_reminder', 'Remember: Never include patient identifiers.');

// Use in template
$template = PromptTemplate::create([
    'name' => 'Medical Prompt',
    'content' => '{{ fragment("hipaa_reminder") }} {{ task("Analyze the case.") }}',
]);
```

### Processing Pipeline

```php
use Yannelli\PromptPipeline\Processing\Input\TrimWhitespace;
use Yannelli\PromptPipeline\Processing\Output\ExtractJsonBlock;

$result = PromptPipeline::make($template)
    ->withVariables($variables)
    ->inputProcessor(TrimWhitespace::class)
    ->outputProcessor(ExtractJsonBlock::class)
    ->excludeTags(['thinking'])
    ->render();
```

## Twig Functions Available

### XML Tags
- `xml(tag, content, attrs)` - Generic XML tag
- `xml_open(tag, attrs)` / `xml_close(tag)` - Manual open/close
- `cdata(content)` - CDATA wrapper

### Claude Tags
- `system_instructions(content)`, `instructions(content)`, `task(content)`
- `context(content, label)`, `constraints(items)`, `rules(items)`
- `output_format(content)`, `user_message(content)`, `query(content)`

### Chain of Thought
- `thinking()`, `reasoning()`, `answer()`, `scratchpad()`
- `cot_basic()`, `cot_guided(steps)`, `cot_structured()`

### Documents & Examples
- `document(content, attrs)`, `document_content(content)`
- `example(content, label)`, `example_pair(input, output)`

### Utilities
- `fragment(slug, vars)` - Include fragment
- `json(value, pretty)` - JSON encode
- `deduplicate(content)` - Remove duplicates

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag="prompt-pipeline-config"
```

Key options:
- `table_name` - Database table name
- `fragments.max_depth` - Maximum fragment nesting depth
- `sandbox.allowed_filters` - Additional Twig filters to allow
- `processors.input/output` - Default processors
- `exclusions.fragments/tags` - Global exclusions

## Artisan Commands

```bash
php artisan prompt-pipeline:validate --file=template.twig
php artisan prompt-pipeline:render {template} --variables='{"name":"value"}'
php artisan prompt-pipeline:variables
php artisan prompt-pipeline:fragments
php artisan prompt-pipeline:cache:clear
```

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
