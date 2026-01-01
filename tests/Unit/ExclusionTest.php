<?php

use Yannelli\PromptPipeline\Exclusions\ExclusionManager;
use Yannelli\PromptPipeline\Exclusions\ExclusionSet;

describe('ExclusionSet', function () {
    it('creates empty set', function () {
        $set = ExclusionSet::make();

        expect($set->isEmpty())->toBeTrue();
        expect($set->getFragments())->toBe([]);
        expect($set->getTags())->toBe([]);
    });

    it('excludes fragments', function () {
        $set = ExclusionSet::make()
            ->excludeFragments(['frag1', 'frag2']);

        expect($set->isFragmentExcluded('frag1'))->toBeTrue();
        expect($set->isFragmentExcluded('frag3'))->toBeFalse();
    });

    it('excludes tags', function () {
        $set = ExclusionSet::make()
            ->excludeTags(['thinking', 'scratchpad']);

        expect($set->isTagExcluded('thinking'))->toBeTrue();
        expect($set->isTagExcluded('answer'))->toBeFalse();
    });

    it('merges sets', function () {
        $set1 = ExclusionSet::make()->excludeFragments(['frag1']);
        $set2 = ExclusionSet::make()->excludeTags(['tag1']);

        $merged = $set1->merge($set2);

        expect($merged->isFragmentExcluded('frag1'))->toBeTrue();
        expect($merged->isTagExcluded('tag1'))->toBeTrue();
    });

    it('is immutable', function () {
        $set1 = ExclusionSet::make();
        $set2 = $set1->excludeFragment('frag1');

        expect($set1->isEmpty())->toBeTrue();
        expect($set2->isEmpty())->toBeFalse();
    });
});

describe('ExclusionManager', function () {
    beforeEach(function () {
        ExclusionManager::clearAll();
    });

    it('adds fragment exclusions', function () {
        ExclusionManager::excludeFragment('test_fragment');

        expect(ExclusionManager::isFragmentExcluded('test_fragment'))->toBeTrue();
    });

    it('adds tag exclusions', function () {
        ExclusionManager::excludeTag('test_tag');

        expect(ExclusionManager::isTagExcluded('test_tag'))->toBeTrue();
    });

    it('removes exclusions', function () {
        ExclusionManager::excludeFragment('to_remove');
        ExclusionManager::removeFragmentExclusion('to_remove');

        expect(ExclusionManager::isFragmentExcluded('to_remove'))->toBeFalse();
    });

    it('clears all exclusions', function () {
        ExclusionManager::excludeFragment('frag');
        ExclusionManager::excludeTag('tag');
        ExclusionManager::clearAll();

        expect(ExclusionManager::excludedFragments())->toBe([]);
        expect(ExclusionManager::excludedTags())->toBe([]);
    });

    it('converts to ExclusionSet', function () {
        ExclusionManager::excludeFragment('frag');
        ExclusionManager::excludeTag('tag');

        $set = ExclusionManager::toSet();

        expect($set->isFragmentExcluded('frag'))->toBeTrue();
        expect($set->isTagExcluded('tag'))->toBeTrue();
    });
});
