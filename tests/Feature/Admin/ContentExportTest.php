<?php

namespace Tests\Feature\Admin;

use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\User;
use App\Services\ContentExporter;
use Database\Seeders\ContentSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        app(ContentSeeder::class)->run();
    }

    public function test_a_learner_cannot_export_the_content(): void
    {
        $learner = User::factory()->create();
        $learner->assignRole('learner');

        $this->getJson('/api/v1/admin/export')->assertUnauthorized();
        $this->actingAs($learner)->getJson('/api/v1/admin/export')->assertForbidden();
    }

    public function test_the_export_is_offered_as_a_file(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/admin/export')
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="backend-journey-content.json"')
            ->assertJsonPath('roadmap.slug', 'backend-engineering')
            ->assertJsonPath('tracks.0.slug', 'http-api-design');
    }

    public function test_the_export_carries_everything_the_seeder_reads(): void
    {
        $content = app(ContentExporter::class)->export();

        $this->assertSame(
            ['roadmap', 'tags', 'tracks', 'questions', 'achievements'],
            array_keys($content),
        );

        $this->assertCount(5, $content['tracks']);
        $this->assertCount(10, $content['questions']);
        $this->assertCount(3, $content['achievements']);

        $track = collect($content['tracks'])->firstWhere('slug', 'indexing-query-plans');
        $journey = collect($track['journeys'])->firstWhere('slug', 'b-tree-internals');

        $this->assertSame(['en', 'id'], array_keys($track['translations']));
        $this->assertSame('b-tree-internals-core', $journey['quizzes'][0]['slug']);
        $this->assertSame(
            ['q-btree-random-uuid-key', 'q-btree-low-selectivity-seq-scan'],
            $journey['quizzes'][0]['questions'],
            'quiz question order must survive the export',
        );

        $question = collect($content['questions'])->firstWhere('key', 'q-btree-random-uuid-key');

        $this->assertSame('indexing-query-plans', $question['track']);
        $this->assertSame(['indexing', 'postgres'], $question['tags']);
        $this->assertCount(4, $question['options']);
        $this->assertCount(1, collect($question['options'])->where('is_correct', true));
        $this->assertArrayHasKey('id', $question['translations']);
    }

    public function test_reimporting_the_export_changes_nothing(): void
    {
        $first = app(ContentExporter::class)->export();
        $versions = QuestionVersion::count();
        $questions = Question::count();

        app(ContentSeeder::class)->sync($first);

        $this->assertSame($questions, Question::count());
        $this->assertSame(
            $versions,
            QuestionVersion::count(),
            're-importing an export must not bump a single question version',
        );

        $second = app(ContentExporter::class)->export();

        $this->assertSame(
            json_encode($first, JSON_THROW_ON_ERROR),
            json_encode($second, JSON_THROW_ON_ERROR),
            'export, import, export must be byte-identical',
        );
    }

    public function test_an_admin_edit_shows_up_in_the_next_export(): void
    {
        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/questions/q-btree-random-uuid-key', [
                'key' => 'q-btree-random-uuid-key',
                'trackSlug' => 'indexing-query-plans',
                'type' => 'single_choice',
                'difficulty' => 'hard',
                'isActive' => true,
                'codeLanguage' => null,
                'tags' => ['postgres'],
                'translations' => ['en' => ['stemMd' => 'A rewritten stem for the export test.']],
                'options' => [
                    ['isCorrect' => true, 'translations' => ['en' => ['labelMd' => 'The right one']]],
                    ['isCorrect' => false, 'translations' => ['en' => ['labelMd' => 'The wrong one']]],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('meta.newVersion', true);

        $question = collect(app(ContentExporter::class)->export()['questions'])
            ->firstWhere('key', 'q-btree-random-uuid-key');

        $this->assertSame('hard', $question['difficulty']);
        $this->assertSame(['postgres'], $question['tags']);
        $this->assertSame(['en'], array_keys($question['translations']));
        $this->assertCount(2, $question['options']);
        $this->assertSame('A rewritten stem for the export test.', $question['translations']['en']['stem_md']);
    }
}
