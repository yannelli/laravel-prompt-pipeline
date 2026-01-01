<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Structure;

use Closure;

/**
 * @final
 */
class XmlBuilder
{
    /**
     * @var array<string>
     */
    protected array $parts = [];

    protected bool $useSnakeCase = true;

    protected bool $addNewlines = true;

    /**
     * Create a new XmlBuilder instance.
     */
    public static function make(): static
    {
        return new static;
    }

    /**
     * Add a tag with optional content and attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function tag(string $name, string|Closure|null $content = null, array $attributes = []): static
    {
        if ($content instanceof Closure) {
            $nested = new static;
            $nested->useSnakeCase = $this->useSnakeCase;
            $nested->addNewlines = $this->addNewlines;
            $content($nested);
            $content = $nested->build();
        }

        $this->parts[] = self::buildTag($name, $content, $attributes);

        return $this;
    }

    /**
     * Add raw content without wrapping.
     */
    public function raw(string $content): static
    {
        $this->parts[] = $content;

        return $this;
    }

    /**
     * Add a blank line.
     */
    public function blank(): static
    {
        $this->parts[] = '';

        return $this;
    }

    /**
     * Conditionally add a tag.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function when(bool $condition, string $name, string|Closure|null $content = null, array $attributes = []): static
    {
        if ($condition) {
            $this->tag($name, $content, $attributes);
        }

        return $this;
    }

    /**
     * Add an opening tag.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function open(string $tag, array $attributes = []): static
    {
        $this->parts[] = self::buildOpenTag($tag, $attributes);

        return $this;
    }

    /**
     * Add a closing tag.
     */
    public function close(string $tag): static
    {
        $this->parts[] = "</{$tag}>";

        return $this;
    }

    /**
     * Build and return the final string.
     */
    public function build(): string
    {
        $separator = $this->addNewlines ? "\n" : '';

        return implode($separator, $this->parts);
    }

    /**
     * Get parts as array (for inspection).
     *
     * @return array<string>
     */
    public function toArray(): array
    {
        return $this->parts;
    }

    /**
     * Preserve original case for method names.
     */
    public function preserveCase(): static
    {
        $this->useSnakeCase = false;

        return $this;
    }

    /**
     * Use snake_case for method names (default).
     */
    public function useSnakeCase(): static
    {
        $this->useSnakeCase = true;

        return $this;
    }

    /**
     * Disable newlines between parts.
     */
    public function compact(): static
    {
        $this->addNewlines = false;

        return $this;
    }

    /**
     * Enable newlines between parts (default).
     */
    public function expanded(): static
    {
        $this->addNewlines = true;

        return $this;
    }

    /**
     * Static helper to wrap content in a tag.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function wrap(string $tag, string $content, array $attributes = []): string
    {
        return self::buildTag($tag, $content, $attributes);
    }

    // ========================================
    // System & Instructions
    // ========================================

    /**
     * Add system instructions tag.
     */
    public function systemInstructions(string|Closure|null $content = null): static
    {
        return $this->tag('system_instructions', $content);
    }

    /**
     * Add instructions tag.
     */
    public function instructions(string|Closure|null $content = null): static
    {
        return $this->tag('instructions', $content);
    }

    /**
     * Add task tag.
     */
    public function task(string|Closure|null $content = null): static
    {
        return $this->tag('task', $content);
    }

    /**
     * Add context tag with optional label.
     */
    public function context(string|Closure|null $content = null, ?string $label = null): static
    {
        $attributes = $label !== null ? ['label' => $label] : [];

        return $this->tag('context', $content, $attributes);
    }

    /**
     * Add constraints as a bulleted list.
     *
     * @param  array<string>  $items
     */
    public function constraints(array $items): static
    {
        $content = implode("\n", array_map(fn ($item) => "- {$item}", $items));

        return $this->tag('constraints', $content);
    }

    /**
     * Add rules as a bulleted list.
     *
     * @param  array<string>  $items
     */
    public function rules(array $items): static
    {
        $content = implode("\n", array_map(fn ($item) => "- {$item}", $items));

        return $this->tag('rules', $content);
    }

    // ========================================
    // Chain of Thought
    // ========================================

    /**
     * Add thinking tag.
     */
    public function thinking(?string $content = null): static
    {
        return $this->tag('thinking', $content ?? '');
    }

    /**
     * Add reasoning tag.
     */
    public function reasoning(?string $content = null): static
    {
        return $this->tag('reasoning', $content ?? '');
    }

    /**
     * Add answer tag.
     */
    public function answer(?string $content = null): static
    {
        return $this->tag('answer', $content ?? '');
    }

    /**
     * Add scratchpad tag.
     */
    public function scratchpad(?string $content = null): static
    {
        return $this->tag('scratchpad', $content ?? '');
    }

    /**
     * Add basic chain of thought instruction.
     */
    public function cotBasic(): static
    {
        return $this->raw('Think step-by-step before providing your answer.');
    }

    /**
     * Add guided chain of thought with specific steps.
     *
     * @param  array<string>  $steps
     */
    public function cotGuided(array $steps): static
    {
        $numbered = [];
        foreach ($steps as $index => $step) {
            $numbered[] = ($index + 1).'. '.$step;
        }

        return $this->raw("Follow these steps:\n".implode("\n", $numbered));
    }

    /**
     * Add structured chain of thought instruction.
     */
    public function cotStructured(): static
    {
        return $this->raw('Use <thinking> tags to show your reasoning process, then provide your final answer in <answer> tags.');
    }

    // ========================================
    // Documents
    // ========================================

    /**
     * Add a single document tag.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function document(string|Closure|null $content = null, array $attributes = []): static
    {
        return $this->tag('document', $content, $attributes);
    }

    /**
     * Add multiple documents with proper structure.
     *
     * @param  array<array<string, mixed>>  $docs
     */
    public function documents(array $docs, string $contentKey = 'content', ?string $nameKey = 'name'): static
    {
        $this->open('documents');

        foreach ($docs as $doc) {
            $attributes = [];
            if ($nameKey !== null && isset($doc[$nameKey])) {
                $attributes['name'] = $doc[$nameKey];
            }

            $this->tag('document', function ($xml) use ($doc, $contentKey) {
                $xml->documentContent($doc[$contentKey] ?? '');
            }, $attributes);
        }

        $this->close('documents');

        return $this;
    }

    /**
     * Add document content tag.
     */
    public function documentContent(string|Closure|null $content = null): static
    {
        return $this->tag('document_content', $content);
    }

    /**
     * Add source tag.
     */
    public function source(string $value): static
    {
        return $this->tag('source', $value);
    }

    // ========================================
    // Examples (Multishot)
    // ========================================

    /**
     * Add a single example tag.
     *
     * @param  string|array<string, string>  $content
     */
    public function example(string|array $content, ?string $label = null): static
    {
        $attributes = $label !== null ? ['label' => $label] : [];

        if (is_array($content)) {
            return $this->tag('example', function ($xml) use ($content) {
                if (isset($content['input'])) {
                    $xml->tag('input', $content['input']);
                }
                if (isset($content['output'])) {
                    $xml->tag('output', $content['output']);
                }
            }, $attributes);
        }

        return $this->tag('example', $content, $attributes);
    }

    /**
     * Add multiple examples.
     *
     * @param  array<array<string, string>>  $examples
     */
    public function examples(array $examples): static
    {
        $this->open('examples');

        foreach ($examples as $example) {
            $this->example($example);
        }

        $this->close('examples');

        return $this;
    }

    /**
     * Add an example pair (input/output).
     */
    public function examplePair(string $input, string $output, ?string $label = null): static
    {
        return $this->example(['input' => $input, 'output' => $output], $label);
    }

    // ========================================
    // Output
    // ========================================

    /**
     * Add output format tag.
     *
     * @param  string|array<string, mixed>  $schema
     */
    public function outputFormat(string|array $schema): static
    {
        $content = is_array($schema) ? json_encode($schema, JSON_PRETTY_PRINT) : $schema;

        return $this->tag('output_format', $content);
    }

    // ========================================
    // User Input
    // ========================================

    /**
     * Add user message tag.
     */
    public function userMessage(string|Closure|null $content = null): static
    {
        return $this->tag('user_message', $content);
    }

    /**
     * Add query tag.
     */
    public function query(string|Closure|null $content = null): static
    {
        return $this->tag('query', $content);
    }

    // ========================================
    // Utilities
    // ========================================

    /**
     * Add CDATA wrapped content.
     */
    public function cdata(string $content): static
    {
        $this->parts[] = '<![CDATA['.$content.']]>';

        return $this;
    }

    // ========================================
    // Dynamic Method Handling
    // ========================================

    /**
     * Handle dynamic method calls as tag names.
     *
     * @param  array<mixed>  $arguments
     */
    public function __call(string $method, array $arguments): static
    {
        $tagName = $this->useSnakeCase ? $this->toSnakeCase($method) : $method;

        $content = $arguments[0] ?? null;
        $attributes = $arguments[1] ?? [];

        return $this->tag($tagName, $content, $attributes);
    }

    // ========================================
    // Internal Helpers
    // ========================================

    /**
     * Build a complete XML tag.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected static function buildTag(string $name, ?string $content, array $attributes = []): string
    {
        $openTag = self::buildOpenTag($name, $attributes);
        $closeTag = "</{$name}>";

        if ($content === null || $content === '') {
            return $openTag.$closeTag;
        }

        // Check if content has newlines - if so, format nicely
        if (str_contains($content, "\n")) {
            return $openTag."\n".$content."\n".$closeTag;
        }

        return $openTag.$content.$closeTag;
    }

    /**
     * Build an opening tag with attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected static function buildOpenTag(string $name, array $attributes = []): string
    {
        if (empty($attributes)) {
            return "<{$name}>";
        }

        $attrString = '';
        foreach ($attributes as $key => $value) {
            $escaped = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            $attrString .= " {$key}=\"{$escaped}\"";
        }

        return "<{$name}{$attrString}>";
    }

    /**
     * Convert camelCase to snake_case.
     */
    protected function toSnakeCase(string $input): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }
}
