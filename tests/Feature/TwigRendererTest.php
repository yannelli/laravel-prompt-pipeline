<?php

use Yannelli\PromptPipeline\Exceptions\PromptRenderException;
use Yannelli\PromptPipeline\Rendering\TwigRenderer;

describe('TwigRenderer', function () {
    beforeEach(function () {
        $this->renderer = app(TwigRenderer::class);
    });

    it('renders simple templates', function () {
        $result = $this->renderer->render('Hello, {{ name }}!', ['name' => 'World']);

        expect($result)->toBe('Hello, World!');
    });

    it('renders with conditionals', function () {
        $template = '{% if show %}Visible{% endif %}';

        expect($this->renderer->render($template, ['show' => true]))->toBe('Visible');
        expect($this->renderer->render($template, ['show' => false]))->toBe('');
    });

    it('renders with loops', function () {
        $template = '{% for item in items %}{{ item }},{% endfor %}';
        $result = $this->renderer->render($template, ['items' => ['a', 'b', 'c']]);

        expect($result)->toBe('a,b,c,');
    });

    describe('XML functions', function () {
        it('renders xml() function', function () {
            $result = $this->renderer->render('{{ xml("tag", "content") }}');

            expect($result)->toBe('<tag>content</tag>');
        });

        it('renders xml_open() and xml_close()', function () {
            $result = $this->renderer->render('{{ xml_open("div") }}content{{ xml_close("div") }}');

            expect($result)->toBe('<div>content</div>');
        });

        it('renders cdata()', function () {
            $result = $this->renderer->render('{{ cdata("content") }}');

            expect($result)->toBe('<![CDATA[content]]>');
        });
    });

    describe('Claude functions', function () {
        it('renders system_instructions()', function () {
            $result = $this->renderer->render('{{ system_instructions("Be helpful") }}');

            expect($result)->toContain('<system_instructions>');
            expect($result)->toContain('Be helpful');
        });

        it('renders task()', function () {
            $result = $this->renderer->render('{{ task("Do this thing") }}');

            expect($result)->toContain('<task>Do this thing</task>');
        });

        it('renders constraints()', function () {
            $result = $this->renderer->render("{{ constraints(['Rule 1', 'Rule 2']) }}");

            expect($result)->toContain('<constraints>');
            expect($result)->toContain('- Rule 1');
        });

        it('renders thinking tags', function () {
            $result = $this->renderer->render('{{ thinking() }}');

            expect($result)->toBe('<thinking></thinking>');
        });

        it('renders cot_basic()', function () {
            $result = $this->renderer->render('{{ cot_basic() }}');

            expect($result)->toContain('step-by-step');
        });
    });

    describe('filters', function () {
        it('applies json filter', function () {
            $result = $this->renderer->render('{{ data | json }}', ['data' => ['key' => 'value']]);

            expect($result)->toBe('{"key":"value"}');
        });

        it('applies deduplicate filter', function () {
            $result = $this->renderer->render('{{ text | deduplicate }}', ['text' => "Line\nLine\nOther"]);

            expect(substr_count($result, 'Line'))->toBe(1);
        });
    });

    describe('validation', function () {
        it('validates correct templates', function () {
            expect($this->renderer->validate('Hello {{ name }}'))->toBeTrue();
        });

        it('invalidates incorrect templates', function () {
            expect($this->renderer->validate('Hello {{ name'))->toBeFalse();
        });

        it('returns errors for invalid templates', function () {
            $errors = $this->renderer->getErrors('Hello {{ name');

            expect($errors)->not->toBeEmpty();
        });
    });

    describe('exclusions', function () {
        it('excludes tags when configured', function () {
            $this->renderer->setExcludedTags(['thinking']);
            $result = $this->renderer->render('{{ thinking("content") }}');

            expect($result)->toBe('');
        });
    });
});
