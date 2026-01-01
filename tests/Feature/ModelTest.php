<?php

use Illuminate\Database\Eloquent\Model;
use Yannelli\PromptPipeline\Models\PromptTemplate;
use Yannelli\PromptPipeline\Traits\HasPromptTemplates;

// Create a test model for testing the trait
class TestOrganization extends Model
{
    use HasPromptTemplates;

    protected $table = 'test_organizations';

    protected $guarded = [];

    public $timestamps = false;

    public function promptTemplateVariables(): array
    {
        return [
            'org_name' => $this->name,
        ];
    }
}

beforeEach(function () {
    // Create test table
    \Illuminate\Support\Facades\Schema::create('test_organizations', function ($table) {
        $table->id();
        $table->string('name');
    });
});

afterEach(function () {
    \Illuminate\Support\Facades\Schema::dropIfExists('test_organizations');
});

describe('PromptTemplate Model', function () {
    it('creates templates with ULID', function () {
        $template = PromptTemplate::create([
            'name' => 'Test Template',
            'content' => 'Hello World',
        ]);

        expect($template->id)->toHaveLength(26);
    });

    it('casts metadata to array', function () {
        $template = PromptTemplate::create([
            'name' => 'Test',
            'content' => 'Content',
            'metadata' => ['key' => 'value'],
        ]);

        expect($template->metadata)->toBeArray();
        expect($template->metadata['key'])->toBe('value');
    });

    it('scopes to active templates', function () {
        PromptTemplate::create(['name' => 'Active', 'content' => 'a', 'is_active' => true]);
        PromptTemplate::create(['name' => 'Inactive', 'content' => 'b', 'is_active' => false]);

        expect(PromptTemplate::active()->count())->toBe(1);
    });

    it('scopes by type', function () {
        PromptTemplate::create(['name' => 'System', 'content' => 'a', 'type' => 'system']);
        PromptTemplate::create(['name' => 'Fragment', 'content' => 'b', 'type' => 'fragment']);

        expect(PromptTemplate::ofType('fragment')->count())->toBe(1);
    });

    it('scopes to fragments', function () {
        PromptTemplate::create(['name' => 'Not Fragment', 'content' => 'a', 'type' => 'system']);
        PromptTemplate::create(['name' => 'Fragment', 'content' => 'b', 'type' => 'fragment']);

        expect(PromptTemplate::fragments()->count())->toBe(1);
    });

    it('finds by slug', function () {
        PromptTemplate::create(['name' => 'Test', 'slug' => 'my-template', 'content' => 'a']);

        expect(PromptTemplate::bySlug('my-template')->first())->not->toBeNull();
    });

    it('identifies fragments', function () {
        $fragment = PromptTemplate::create(['name' => 'F', 'content' => 'a', 'type' => 'fragment']);
        $other = PromptTemplate::create(['name' => 'O', 'content' => 'a', 'type' => 'system']);

        expect($fragment->isFragment())->toBeTrue();
        expect($other->isFragment())->toBeFalse();
    });

    it('gets and sets metadata', function () {
        $template = PromptTemplate::create(['name' => 'T', 'content' => 'c']);

        $template->setMeta('processors.input', ['trim']);
        $template->save();
        $template->refresh();

        expect($template->getMeta('processors.input'))->toBe(['trim']);
        expect($template->getMeta('nonexistent', 'default'))->toBe('default');
    });
});

describe('HasPromptTemplates Trait', function () {
    it('creates templates for model', function () {
        $org = TestOrganization::create(['name' => 'Test Org']);

        $template = $org->createPromptTemplate([
            'name' => 'Test Template',
            'slug' => 'test',
            'content' => 'Hello {{ org_name }}',
        ]);

        expect($template->templateable_type)->toBe(TestOrganization::class);
        expect($template->templateable_id)->toBe((string) $org->id);
    });

    it('creates fragments for model', function () {
        $org = TestOrganization::create(['name' => 'Test Org']);

        $fragment = $org->createFragment('test_frag', 'Fragment content');

        expect($fragment->type)->toBe('fragment');
        expect($fragment->slug)->toBe('test_frag');
    });

    it('finds templates by slug or id', function () {
        $org = TestOrganization::create(['name' => 'Test Org']);
        $template = $org->createPromptTemplate([
            'name' => 'Test',
            'slug' => 'test-slug',
            'content' => 'content',
        ]);

        expect($org->findPromptTemplate('test-slug'))->not->toBeNull();
        expect($org->findPromptTemplate($template->id))->not->toBeNull();
    });

    it('renders templates with model variables', function () {
        $org = TestOrganization::create(['name' => 'Acme Corp']);
        $org->createPromptTemplate([
            'name' => 'Greeting',
            'slug' => 'greeting',
            'content' => 'Welcome to {{ org_name }}!',
        ]);

        $result = $org->renderPromptTemplate('greeting');

        expect($result)->toBe('Welcome to Acme Corp!');
    });

    it('accesses fragments relationship', function () {
        $org = TestOrganization::create(['name' => 'Test Org']);
        $org->createFragment('frag1', 'Content 1');
        $org->createFragment('frag2', 'Content 2');

        expect($org->fragments()->count())->toBe(2);
    });

    it('accesses templates by type', function () {
        $org = TestOrganization::create(['name' => 'Test Org']);
        $org->createPromptTemplate(['name' => 'T1', 'content' => 'c', 'type' => 'system']);
        $org->createPromptTemplate(['name' => 'T2', 'content' => 'c', 'type' => 'user']);
        $org->createPromptTemplate(['name' => 'T3', 'content' => 'c', 'type' => 'system']);

        expect($org->promptTemplatesOfType('system')->count())->toBe(2);
    });
});
