<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Rendering;

use Twig\Environment;
use Twig\Error\Error as TwigError;
use Twig\Extension\SandboxExtension;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Yannelli\PromptPipeline\Contracts\Renderer;
use Yannelli\PromptPipeline\Exceptions\PromptRenderException;
use Yannelli\PromptPipeline\Exceptions\SandboxViolationException;
use Yannelli\PromptPipeline\Exclusions\ExclusionManager;
use Yannelli\PromptPipeline\Structure\XmlBuilder;

class TwigRenderer implements Renderer
{
    protected Environment $twig;

    protected FragmentRegistry $fragmentRegistry;

    /**
     * @var array<string>
     */
    protected array $excludedTags = [];

    public function __construct(
        ?FragmentRegistry $fragmentRegistry = null
    ) {
        $this->fragmentRegistry = $fragmentRegistry ?? new FragmentRegistry;
        $this->initializeTwig();
    }

    /**
     * Set excluded tags for this render.
     *
     * @param  array<string>  $tags
     */
    public function setExcludedTags(array $tags): self
    {
        $this->excludedTags = $tags;

        return $this;
    }

    /**
     * Get the fragment registry.
     */
    public function getFragmentRegistry(): FragmentRegistry
    {
        return $this->fragmentRegistry;
    }

    /**
     * Render a template string with the given variables.
     *
     * @param  array<string, mixed>  $variables
     *
     * @throws PromptRenderException
     * @throws SandboxViolationException
     */
    public function render(string $template, array $variables = []): string
    {
        try {
            // Reset fragment resolution state
            $this->fragmentRegistry->reset();

            // Create a new loader with this template
            $loader = new ArrayLoader(['template' => $template]);
            $this->twig->setLoader($loader);

            // Render with sandbox enabled
            return $this->twig->render('template', $variables);
        } catch (TwigError $e) {
            if (str_contains($e->getMessage(), 'is not allowed')) {
                throw new SandboxViolationException($e->getMessage(), 0, $e);
            }

            throw PromptRenderException::syntaxError($e->getMessage(), $e->getTemplateLine());
        }
    }

    /**
     * Validate a template string for syntax errors.
     */
    public function validate(string $template): bool
    {
        try {
            $loader = new ArrayLoader(['template' => $template]);
            $this->twig->setLoader($loader);
            $this->twig->parse($this->twig->tokenize(
                new \Twig\Source($template, 'template')
            ));

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get validation errors for a template.
     *
     * @return array<string>
     */
    public function getErrors(string $template): array
    {
        $errors = [];

        try {
            $loader = new ArrayLoader(['template' => $template]);
            $this->twig->setLoader($loader);
            $this->twig->parse($this->twig->tokenize(
                new \Twig\Source($template, 'template')
            ));
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }

        return $errors;
    }

    /**
     * Initialize the Twig environment.
     */
    protected function initializeTwig(): void
    {
        $loader = new ArrayLoader;

        $cacheConfig = config('prompt-pipeline.cache', []);
        $cachePath = $cacheConfig['enabled'] ?? true
            ? ($cacheConfig['path'] ?? storage_path('framework/cache/prompt-pipeline'))
            : false;

        $this->twig = new Environment($loader, [
            'cache' => $cachePath,
            'auto_reload' => true,
            'strict_variables' => config('prompt-pipeline.missing_variable_behavior', 'empty') === 'error',
        ]);

        // Add sandbox extension
        $sandboxExtension = new SandboxExtension(SandboxPolicy::create(), true);
        $this->twig->addExtension($sandboxExtension);

        // Register custom functions
        $this->registerFunctions();
        $this->registerFilters();
    }

    /**
     * Register custom Twig functions.
     */
    protected function registerFunctions(): void
    {
        // XML functions
        $this->twig->addFunction(new TwigFunction('xml', function (string $tag, ?string $content = null, array $attrs = []) {
            return $this->xmlTag($tag, $content, $attrs);
        }));

        $this->twig->addFunction(new TwigFunction('xml_open', function (string $tag, array $attrs = []) {
            return $this->xmlOpen($tag, $attrs);
        }));

        $this->twig->addFunction(new TwigFunction('xml_close', function (string $tag) {
            return "</{$tag}>";
        }));

        $this->twig->addFunction(new TwigFunction('cdata', function (string $content) {
            return '<![CDATA['.$content.']]>';
        }));

        // Claude tag functions
        $this->twig->addFunction(new TwigFunction('system_instructions', fn ($content) => $this->xmlTag('system_instructions', $content)));
        $this->twig->addFunction(new TwigFunction('instructions', fn ($content) => $this->xmlTag('instructions', $content)));
        $this->twig->addFunction(new TwigFunction('context', fn ($content, $label = null) => $this->xmlTag('context', $content, $label ? ['label' => $label] : [])));
        $this->twig->addFunction(new TwigFunction('task', fn ($content) => $this->xmlTag('task', $content)));
        $this->twig->addFunction(new TwigFunction('constraints', fn (array $items) => $this->xmlTag('constraints', implode("\n", array_map(fn ($i) => "- {$i}", $items)))));
        $this->twig->addFunction(new TwigFunction('rules', fn (array $items) => $this->xmlTag('rules', implode("\n", array_map(fn ($i) => "- {$i}", $items)))));
        $this->twig->addFunction(new TwigFunction('output_format', fn ($content) => $this->xmlTag('output_format', $content)));
        $this->twig->addFunction(new TwigFunction('user_message', fn ($content) => $this->xmlTag('user_message', $content)));
        $this->twig->addFunction(new TwigFunction('query', fn ($content) => $this->xmlTag('query', $content)));

        // Chain of thought functions
        $this->twig->addFunction(new TwigFunction('thinking', fn ($content = null) => $this->xmlTag('thinking', $content ?? '')));
        $this->twig->addFunction(new TwigFunction('thinking_open', fn () => $this->xmlOpen('thinking')));
        $this->twig->addFunction(new TwigFunction('thinking_close', fn () => '</thinking>'));
        $this->twig->addFunction(new TwigFunction('reasoning', fn ($content = null) => $this->xmlTag('reasoning', $content ?? '')));
        $this->twig->addFunction(new TwigFunction('answer', fn ($content = null) => $this->xmlTag('answer', $content ?? '')));
        $this->twig->addFunction(new TwigFunction('answer_open', fn () => $this->xmlOpen('answer')));
        $this->twig->addFunction(new TwigFunction('answer_close', fn () => '</answer>'));
        $this->twig->addFunction(new TwigFunction('scratchpad', fn () => $this->xmlTag('scratchpad', '')));

        $this->twig->addFunction(new TwigFunction('cot_basic', fn () => 'Think step-by-step before providing your answer.'));
        $this->twig->addFunction(new TwigFunction('cot_guided', function (array $steps) {
            $numbered = array_map(fn ($s, $i) => ($i + 1).'. '.$s, $steps, array_keys($steps));

            return "Follow these steps:\n".implode("\n", $numbered);
        }));
        $this->twig->addFunction(new TwigFunction('cot_structured', fn () => 'Use <thinking> tags to show your reasoning process, then provide your final answer in <answer> tags.'));

        // Document functions
        $this->twig->addFunction(new TwigFunction('document', fn ($content, $attrs = []) => $this->xmlTag('document', $content, $attrs)));
        $this->twig->addFunction(new TwigFunction('documents_open', fn () => $this->xmlOpen('documents')));
        $this->twig->addFunction(new TwigFunction('documents_close', fn () => '</documents>'));
        $this->twig->addFunction(new TwigFunction('document_content', fn ($content) => $this->xmlTag('document_content', $content)));
        $this->twig->addFunction(new TwigFunction('source', fn ($value) => $this->xmlTag('source', $value)));

        // Example functions
        $this->twig->addFunction(new TwigFunction('examples_open', fn () => $this->xmlOpen('examples')));
        $this->twig->addFunction(new TwigFunction('examples_close', fn () => '</examples>'));
        $this->twig->addFunction(new TwigFunction('example', fn ($content, $label = null) => $this->xmlTag('example', $content, $label ? ['label' => $label] : [])));
        $this->twig->addFunction(new TwigFunction('example_pair', function ($input, $output) {
            return $this->xmlTag('example',
                $this->xmlTag('input', $input)."\n".$this->xmlTag('output', $output)
            );
        }));

        // Fragment function
        $this->twig->addFunction(new TwigFunction('fragment', function (string $slug, array $vars = []) {
            return $this->resolveFragment($slug, $vars);
        }));

        // Utility functions
        $this->twig->addFunction(new TwigFunction('json', fn ($value, $pretty = false) => json_encode($value, $pretty ? JSON_PRETTY_PRINT : 0)));
        $this->twig->addFunction(new TwigFunction('deduplicate', fn ($content) => $this->deduplicate($content)));
        $this->twig->addFunction(new TwigFunction('deduplicate_whitespace', fn ($content) => $this->deduplicateWhitespace($content)));
        $this->twig->addFunction(new TwigFunction('deduplicate_lines', fn ($content) => $this->deduplicateLines($content)));
    }

    /**
     * Register custom Twig filters.
     */
    protected function registerFilters(): void
    {
        $this->twig->addFilter(new TwigFilter('json', fn ($value, $pretty = false) => json_encode($value, $pretty ? JSON_PRETTY_PRINT : 0)));
        $this->twig->addFilter(new TwigFilter('deduplicate', fn ($content) => $this->deduplicate($content)));
        $this->twig->addFilter(new TwigFilter('deduplicate_whitespace', fn ($content) => $this->deduplicateWhitespace($content)));
        $this->twig->addFilter(new TwigFilter('deduplicate_lines', fn ($content) => $this->deduplicateLines($content)));
    }

    /**
     * Build an XML tag.
     *
     * @param  array<string, mixed>  $attrs
     */
    protected function xmlTag(string $tag, ?string $content, array $attrs = []): string
    {
        // Check if tag is excluded
        if ($this->isTagExcluded($tag)) {
            return '';
        }

        return XmlBuilder::wrap($tag, $content ?? '', $attrs);
    }

    /**
     * Build an opening XML tag.
     *
     * @param  array<string, mixed>  $attrs
     */
    protected function xmlOpen(string $tag, array $attrs = []): string
    {
        // Check if tag is excluded
        if ($this->isTagExcluded($tag)) {
            return '';
        }

        if (empty($attrs)) {
            return "<{$tag}>";
        }

        $attrString = '';
        foreach ($attrs as $key => $value) {
            $escaped = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            $attrString .= " {$key}=\"{$escaped}\"";
        }

        return "<{$tag}{$attrString}>";
    }

    /**
     * Check if a tag is excluded.
     */
    protected function isTagExcluded(string $tag): bool
    {
        return in_array($tag, $this->excludedTags, true)
            || ExclusionManager::isTagExcluded($tag);
    }

    /**
     * Resolve a fragment and render it.
     *
     * @param  array<string, mixed>  $vars
     */
    protected function resolveFragment(string $slug, array $vars): string
    {
        // Check if excluded
        if ($this->fragmentRegistry->isExcluded($slug)) {
            return '';
        }

        $content = $this->fragmentRegistry->resolve($slug, $vars);

        // Track depth
        $this->fragmentRegistry->beginResolve($slug);

        try {
            // Render the fragment content
            $rendered = $this->render($content, $vars);
        } finally {
            $this->fragmentRegistry->endResolve();
        }

        return $rendered;
    }

    /**
     * Deduplicate content using default strategies.
     */
    protected function deduplicate(string $content): string
    {
        $content = $this->deduplicateWhitespace($content);
        $content = $this->deduplicateLines($content);

        return $content;
    }

    /**
     * Normalize whitespace.
     */
    protected function deduplicateWhitespace(string $content): string
    {
        // Normalize multiple spaces to single
        $content = (string) preg_replace('/[ \t]+/', ' ', $content);

        // Normalize multiple newlines to max 2
        $content = (string) preg_replace('/\n{3,}/', "\n\n", $content);

        // Trim lines
        $lines = explode("\n", $content);
        $lines = array_map('rtrim', $lines);

        return implode("\n", $lines);
    }

    /**
     * Remove consecutive duplicate lines.
     */
    protected function deduplicateLines(string $content): string
    {
        $lines = explode("\n", $content);
        $result = [];
        $prevLine = null;

        foreach ($lines as $line) {
            $normalized = trim($line);
            if ($normalized !== $prevLine || $normalized === '') {
                $result[] = $line;
                $prevLine = $normalized;
            }
        }

        return implode("\n", $result);
    }
}
