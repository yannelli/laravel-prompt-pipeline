<?php

use Yannelli\PromptPipeline\Facades\PromptPipeline;
use Yannelli\PromptPipeline\Models\PromptTemplate;
use Yannelli\PromptPipeline\Processing\Input\TrimWhitespace;
use Yannelli\PromptPipeline\Processing\Output\TrimOutput;
use Yannelli\PromptPipeline\Processing\Pipeline;

describe('Pipeline', function () {
    it('renders from string template', function () {
        $result = Pipeline::fromString('Hello {{ name }}')
            ->withVariables(['name' => 'World'])
            ->render();

        expect($result)->toBe('Hello World');
    });

    it('renders from PromptTemplate model', function () {
        $template = PromptTemplate::create([
            'name' => 'Test Template',
            'slug' => 'test',
            'content' => 'Hello {{ name }}',
        ]);

        $result = Pipeline::make($template)
            ->withVariables(['name' => 'World'])
            ->render();

        expect($result)->toBe('Hello World');
    });

    it('applies input processors', function () {
        $result = Pipeline::fromString('{{ name }}')
            ->withVariables(['name' => '  John  '])
            ->inputProcessor(TrimWhitespace::class)
            ->render();

        expect($result)->toBe('John');
    });

    it('applies output processors', function () {
        $result = Pipeline::fromString('  Hello  ')
            ->outputProcessor(TrimOutput::class)
            ->render();

        expect($result)->toBe('Hello');
    });

    it('processes output only', function () {
        $result = Pipeline::forOutput('  raw output  ')
            ->outputProcessor(TrimOutput::class)
            ->run();

        expect($result)->toBe('raw output');
    });

    it('validates templates', function () {
        $valid = Pipeline::fromString('Hello {{ name }}')->validate();
        $invalid = Pipeline::fromString('Hello {{ name')->validate();

        expect($valid->isValid())->toBeTrue();
        expect($invalid->isValid())->toBeFalse();
        expect($invalid->hasErrors())->toBeTrue();
    });

    it('excludes fragments', function () {
        // Register a runtime fragment
        PromptPipeline::registerFragment('test_fragment', 'Fragment Content');

        $result = Pipeline::fromString("{{ fragment('test_fragment') }}")
            ->excludeFragments(['test_fragment'])
            ->render();

        expect($result)->toBe('');
    });

    it('excludes tags', function () {
        $result = Pipeline::fromString("{{ thinking('content') }}")
            ->excludeTags(['thinking'])
            ->render();

        expect($result)->toBe('');
    });
});

describe('PromptPipeline Facade', function () {
    it('creates pipeline from string', function () {
        $result = PromptPipeline::fromString('Test {{ var }}')
            ->withVariables(['var' => 'value'])
            ->render();

        expect($result)->toBe('Test value');
    });

    it('creates pipeline from template', function () {
        $template = PromptTemplate::create([
            'name' => 'Test',
            'content' => 'Hello {{ name }}',
        ]);

        $result = PromptPipeline::make($template)
            ->withVariables(['name' => 'World'])
            ->render();

        expect($result)->toBe('Hello World');
    });

    it('validates templates', function () {
        expect(PromptPipeline::validate('{{ valid }}'))->toBeTrue();
        expect(PromptPipeline::validate('{{ invalid'))->toBeFalse();
    });

    it('registers runtime fragments', function () {
        PromptPipeline::registerFragment('runtime_test', 'Runtime Content');

        $result = PromptPipeline::fromString("{{ fragment('runtime_test') }}")
            ->render();

        expect($result)->toBe('Runtime Content');
    });
});
