<?php

declare(strict_types=1);

namespace Yannelli\PromptPipeline\Processing\Output;

use Yannelli\PromptPipeline\Contracts\OutputProcessor;

class Deduplicate implements OutputProcessor
{
    /**
     * @param  array{strategies?: array<string>, whitespace?: array<string, mixed>, blankLines?: array<string, mixed>, duplicateLines?: array<string, mixed>, duplicateSentences?: array<string, mixed>}  $config
     */
    public function __construct(
        protected array $config = []
    ) {}

    public function process(string $output): string
    {
        $strategies = $this->config['strategies'] ??
            config('prompt-pipeline.deduplication.default_strategies', ['whitespace', 'blankLines']);

        foreach ($strategies as $strategy) {
            $output = match ($strategy) {
                'whitespace' => $this->normalizeWhitespace($output),
                'blankLines' => $this->removeExcessiveBlankLines($output),
                'duplicateLines' => $this->removeDuplicateLines($output),
                'duplicateSentences' => $this->removeDuplicateSentences($output),
                default => $output,
            };
        }

        return $output;
    }

    protected function normalizeWhitespace(string $content): string
    {
        $config = $this->config['whitespace'] ??
            config('prompt-pipeline.deduplication.whitespace', []);

        $normalizeSpaces = $config['normalize_spaces'] ?? true;
        $trimLines = $config['trim_lines'] ?? true;
        $preserveIndentation = $config['preserve_indentation'] ?? false;

        if ($normalizeSpaces) {
            if ($preserveIndentation) {
                // Only normalize spaces after the first non-space character
                $content = (string) preg_replace('/(?<=\S)[ \t]+/', ' ', $content);
            } else {
                $content = (string) preg_replace('/[ \t]+/', ' ', $content);
            }
        }

        if ($trimLines) {
            $lines = explode("\n", $content);
            $lines = array_map('rtrim', $lines);
            $content = implode("\n", $lines);
        }

        return $content;
    }

    protected function removeExcessiveBlankLines(string $content): string
    {
        $config = $this->config['blankLines'] ??
            config('prompt-pipeline.deduplication.blankLines', []);

        $maxConsecutive = $config['max_consecutive'] ?? 2;
        $trimStart = $config['trim_start'] ?? true;
        $trimEnd = $config['trim_end'] ?? true;

        // Replace excessive newlines
        $pattern = '/\n{'.($maxConsecutive + 2).',}/';
        $replacement = str_repeat("\n", $maxConsecutive + 1);
        $content = (string) preg_replace($pattern, $replacement, $content);

        if ($trimStart) {
            $content = ltrim($content, "\n");
        }

        if ($trimEnd) {
            $content = rtrim($content, "\n");
        }

        return $content;
    }

    protected function removeDuplicateLines(string $content): string
    {
        $config = $this->config['duplicateLines'] ??
            config('prompt-pipeline.deduplication.duplicateLines', []);

        $caseSensitive = $config['case_sensitive'] ?? false;
        $ignoreWhitespace = $config['ignore_whitespace'] ?? true;

        $lines = explode("\n", $content);
        $result = [];
        $prevLine = null;

        foreach ($lines as $line) {
            $normalized = $ignoreWhitespace ? trim($line) : $line;
            $compareLine = $caseSensitive ? $normalized : strtolower($normalized);

            // Allow blank lines through, but prevent consecutive duplicates
            if ($normalized === '' || $compareLine !== $prevLine) {
                $result[] = $line;
                $prevLine = $compareLine;
            }
        }

        return implode("\n", $result);
    }

    protected function removeDuplicateSentences(string $content): string
    {
        $config = $this->config['duplicateSentences'] ??
            config('prompt-pipeline.deduplication.duplicateSentences', []);

        $caseSensitive = $config['case_sensitive'] ?? false;
        $threshold = $config['similarity_threshold'] ?? 0.85;
        $keepFirst = $config['keep_first'] ?? true;

        // Split into sentences
        $sentences = preg_split('/(?<=[.!?])\s+/', $content, -1, PREG_SPLIT_NO_EMPTY);

        if ($sentences === false || count($sentences) < 2) {
            return $content;
        }

        $seen = [];
        $result = [];

        foreach ($sentences as $sentence) {
            $normalized = $caseSensitive ? $sentence : strtolower($sentence);
            $isDuplicate = false;

            foreach ($seen as $seenSentence) {
                if ($threshold >= 1.0) {
                    // Exact match only
                    if ($normalized === $seenSentence) {
                        $isDuplicate = true;
                        break;
                    }
                } else {
                    // Use similarity check
                    similar_text($normalized, $seenSentence, $percent);
                    if ($percent / 100 >= $threshold) {
                        $isDuplicate = true;
                        break;
                    }
                }
            }

            if (! $isDuplicate || ! $keepFirst) {
                $result[] = $sentence;
                $seen[] = $normalized;
            }
        }

        return implode(' ', $result);
    }
}
