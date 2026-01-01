<?php

use Yannelli\PromptPipeline\Structure\XmlBuilder;

describe('XmlBuilder', function () {
    it('creates simple tags', function () {
        $result = XmlBuilder::make()
            ->tag('test', 'content')
            ->build();

        expect($result)->toBe('<test>content</test>');
    });

    it('creates tags with attributes', function () {
        $result = XmlBuilder::make()
            ->tag('test', 'content', ['id' => '123', 'class' => 'foo'])
            ->build();

        expect($result)->toBe('<test id="123" class="foo">content</test>');
    });

    it('creates empty tags', function () {
        $result = XmlBuilder::make()
            ->tag('test')
            ->build();

        expect($result)->toBe('<test></test>');
    });

    it('handles nested content with closures', function () {
        $result = XmlBuilder::make()
            ->tag('outer', function ($xml) {
                $xml->tag('inner', 'content');
            })
            ->build();

        expect($result)->toContain('<outer>');
        expect($result)->toContain('<inner>content</inner>');
        expect($result)->toContain('</outer>');
    });

    it('adds raw content', function () {
        $result = XmlBuilder::make()
            ->raw('Some raw text')
            ->build();

        expect($result)->toBe('Some raw text');
    });

    it('adds blank lines', function () {
        $result = XmlBuilder::make()
            ->tag('first', 'content')
            ->blank()
            ->tag('second', 'content')
            ->build();

        expect($result)->toContain("\n\n");
    });

    it('conditionally adds tags with when()', function () {
        $result = XmlBuilder::make()
            ->when(true, 'included', 'yes')
            ->when(false, 'excluded', 'no')
            ->build();

        expect($result)->toContain('<included>yes</included>');
        expect($result)->not->toContain('excluded');
    });

    it('creates open and close tags', function () {
        $result = XmlBuilder::make()
            ->open('wrapper')
            ->tag('inner', 'content')
            ->close('wrapper')
            ->build();

        expect($result)->toContain('<wrapper>');
        expect($result)->toContain('</wrapper>');
    });

    it('wraps content with static method', function () {
        $result = XmlBuilder::wrap('tag', 'content', ['attr' => 'value']);

        expect($result)->toBe('<tag attr="value">content</tag>');
    });

    describe('predefined methods', function () {
        it('creates system_instructions tag', function () {
            $result = XmlBuilder::make()
                ->systemInstructions('You are a helpful assistant.')
                ->build();

            expect($result)->toContain('<system_instructions>');
            expect($result)->toContain('You are a helpful assistant.');
            expect($result)->toContain('</system_instructions>');
        });

        it('creates context tag with label', function () {
            $result = XmlBuilder::make()
                ->context('Some context', 'background')
                ->build();

            expect($result)->toContain('<context label="background">');
        });

        it('creates constraints list', function () {
            $result = XmlBuilder::make()
                ->constraints(['Rule 1', 'Rule 2'])
                ->build();

            expect($result)->toContain('<constraints>');
            expect($result)->toContain('- Rule 1');
            expect($result)->toContain('- Rule 2');
        });

        it('creates thinking tag', function () {
            $result = XmlBuilder::make()
                ->thinking()
                ->build();

            expect($result)->toBe('<thinking></thinking>');
        });

        it('adds basic CoT instruction', function () {
            $result = XmlBuilder::make()
                ->cotBasic()
                ->build();

            expect($result)->toContain('Think step-by-step');
        });

        it('adds structured CoT instruction', function () {
            $result = XmlBuilder::make()
                ->cotStructured()
                ->build();

            expect($result)->toContain('<thinking>');
            expect($result)->toContain('<answer>');
        });
    });

    describe('documents', function () {
        it('creates documents structure', function () {
            $docs = [
                ['name' => 'doc1.pdf', 'content' => 'Content 1'],
                ['name' => 'doc2.pdf', 'content' => 'Content 2'],
            ];

            $result = XmlBuilder::make()
                ->documents($docs)
                ->build();

            expect($result)->toContain('<documents>');
            expect($result)->toContain('<document name="doc1.pdf">');
            expect($result)->toContain('<document_content>Content 1</document_content>');
            expect($result)->toContain('</documents>');
        });
    });

    describe('examples', function () {
        it('creates example pairs', function () {
            $result = XmlBuilder::make()
                ->examplePair('input text', 'output text')
                ->build();

            expect($result)->toContain('<example>');
            expect($result)->toContain('<input>input text</input>');
            expect($result)->toContain('<output>output text</output>');
        });

        it('creates multiple examples', function () {
            $examples = [
                ['input' => 'in1', 'output' => 'out1'],
                ['input' => 'in2', 'output' => 'out2'],
            ];

            $result = XmlBuilder::make()
                ->examples($examples)
                ->build();

            expect($result)->toContain('<examples>');
            expect($result)->toContain('</examples>');
            expect(substr_count($result, '<example>'))->toBe(2);
        });
    });

    describe('dynamic methods', function () {
        it('converts camelCase to snake_case', function () {
            $result = XmlBuilder::make()
                ->patientDemographics('content')
                ->build();

            expect($result)->toContain('<patient_demographics>');
        });

        it('preserves case when configured', function () {
            $result = XmlBuilder::make()
                ->preserveCase()
                ->patientDemographics('content')
                ->build();

            expect($result)->toContain('<patientDemographics>');
        });
    });

    it('supports compact mode without newlines', function () {
        $result = XmlBuilder::make()
            ->compact()
            ->tag('a', 'x')
            ->tag('b', 'y')
            ->build();

        expect($result)->toBe('<a>x</a><b>y</b>');
    });
});
