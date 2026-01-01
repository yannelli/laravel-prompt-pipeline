<?php

use Yannelli\PromptPipeline\Processing\Input\EscapeXmlContent;
use Yannelli\PromptPipeline\Processing\Input\JsonEncodeArrays;
use Yannelli\PromptPipeline\Processing\Input\NormalizeLineBreaks;
use Yannelli\PromptPipeline\Processing\Input\SanitizeInput;
use Yannelli\PromptPipeline\Processing\Input\TrimWhitespace;
use Yannelli\PromptPipeline\Processing\Output\Deduplicate;
use Yannelli\PromptPipeline\Processing\Output\ExtractJsonBlock;
use Yannelli\PromptPipeline\Processing\Output\ExtractXmlTag;
use Yannelli\PromptPipeline\Processing\Output\NormalizeWhitespace;
use Yannelli\PromptPipeline\Processing\Output\StripMarkdownFences;
use Yannelli\PromptPipeline\Processing\Output\TrimOutput;

describe('Input Processors', function () {
    describe('TrimWhitespace', function () {
        it('trims string values', function () {
            $processor = new TrimWhitespace;
            $result = $processor->process(['name' => '  John  ', 'age' => 25]);

            expect($result['name'])->toBe('John');
            expect($result['age'])->toBe(25);
        });

        it('trims recursively by default', function () {
            $processor = new TrimWhitespace;
            $result = $processor->process([
                'user' => [
                    'name' => '  John  ',
                ],
            ]);

            expect($result['user']['name'])->toBe('John');
        });
    });

    describe('SanitizeInput', function () {
        it('removes null bytes', function () {
            $processor = new SanitizeInput;
            $result = $processor->process(['text' => "Hello\0World"]);

            expect($result['text'])->toBe('HelloWorld');
        });

        it('strips Twig delimiters in strict mode', function () {
            $processor = new SanitizeInput(['strict' => true]);
            $result = $processor->process(['text' => 'Hello {{ name }} World']);

            expect($result['text'])->toBe('Hello  World');
        });
    });

    describe('NormalizeLineBreaks', function () {
        it('converts CRLF to LF', function () {
            $processor = new NormalizeLineBreaks;
            $result = $processor->process(['text' => "Line1\r\nLine2"]);

            expect($result['text'])->toBe("Line1\nLine2");
        });

        it('converts CR to LF', function () {
            $processor = new NormalizeLineBreaks;
            $result = $processor->process(['text' => "Line1\rLine2"]);

            expect($result['text'])->toBe("Line1\nLine2");
        });
    });

    describe('JsonEncodeArrays', function () {
        it('encodes arrays to JSON', function () {
            $processor = new JsonEncodeArrays;
            $result = $processor->process(['data' => ['a', 'b', 'c']]);

            expect($result['data'])->toBe('["a","b","c"]');
        });

        it('supports pretty printing', function () {
            $processor = new JsonEncodeArrays(['pretty' => true]);
            $result = $processor->process(['data' => ['key' => 'value']]);

            expect($result['data'])->toContain("\n");
        });
    });

    describe('EscapeXmlContent', function () {
        it('escapes XML special characters', function () {
            $processor = new EscapeXmlContent;
            $result = $processor->process(['text' => '<script>alert("xss")</script>']);

            expect($result['text'])->not->toContain('<script>');
            expect($result['text'])->toContain('&lt;script&gt;');
        });

        it('escapes only specified keys', function () {
            $processor = new EscapeXmlContent(['keys' => ['unsafe']]);
            $result = $processor->process([
                'unsafe' => '<tag>',
                'safe' => '<tag>',
            ]);

            expect($result['unsafe'])->toBe('&lt;tag&gt;');
            expect($result['safe'])->toBe('<tag>');
        });
    });
});

describe('Output Processors', function () {
    describe('TrimOutput', function () {
        it('trims output', function () {
            $processor = new TrimOutput;
            expect($processor->process('  hello  '))->toBe('hello');
        });
    });

    describe('ExtractJsonBlock', function () {
        it('extracts JSON from markdown fences', function () {
            $processor = new ExtractJsonBlock;
            $output = "Here's the result:\n```json\n{\"key\": \"value\"}\n```\nDone!";

            expect($processor->process($output))->toBe('{"key": "value"}');
        });

        it('extracts raw JSON objects', function () {
            $processor = new ExtractJsonBlock;
            $output = 'The answer is {"result": 42}';

            expect($processor->process($output))->toBe('{"result": 42}');
        });

        it('returns original if no valid JSON found', function () {
            $processor = new ExtractJsonBlock;
            $output = 'No JSON here';

            expect($processor->process($output))->toBe('No JSON here');
        });
    });

    describe('StripMarkdownFences', function () {
        it('removes markdown fences preserving content', function () {
            $processor = new StripMarkdownFences;
            $output = "```python\nprint('hello')\n```";

            expect($processor->process($output))->toBe("print('hello')");
        });
    });

    describe('NormalizeWhitespace', function () {
        it('limits consecutive newlines', function () {
            $processor = new NormalizeWhitespace(['max_newlines' => 2]);
            $output = "Line1\n\n\n\n\nLine2";

            expect($processor->process($output))->toBe("Line1\n\nLine2");
        });
    });

    describe('ExtractXmlTag', function () {
        it('extracts content from specified tag', function () {
            $processor = new ExtractXmlTag(['tag' => 'answer']);
            $output = '<thinking>Some reasoning</thinking><answer>The final answer</answer>';

            expect($processor->process($output))->toBe('The final answer');
        });
    });

    describe('Deduplicate', function () {
        it('removes consecutive duplicate lines', function () {
            $processor = new Deduplicate(['strategies' => ['duplicateLines']]);
            $output = "Line 1\nLine 1\nLine 2\nLine 2\nLine 3";

            expect($processor->process($output))->toBe("Line 1\nLine 2\nLine 3");
        });

        it('normalizes whitespace', function () {
            $processor = new Deduplicate(['strategies' => ['whitespace']]);
            $output = "Hello    World";

            expect($processor->process($output))->toBe('Hello World');
        });

        it('removes excessive blank lines', function () {
            $processor = new Deduplicate(['strategies' => ['blankLines']]);
            $output = "Line 1\n\n\n\n\nLine 2";

            $result = $processor->process($output);
            expect(substr_count($result, "\n"))->toBeLessThanOrEqual(3);
        });
    });
});
