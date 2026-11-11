<?php

namespace Tests\Feature\Admin;

use App\Models\Question;
use App\Models\Tag;
use App\Models\Track;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Learning\LearningFixture;
use Tests\TestCase;

class QuestionAuthoringTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Track $track;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->track = LearningFixture::track(LearningFixture::roadmap(), 'indexing');
        Tag::create(['slug' => 'postgres', 'name' => 'PostgreSQL']);
        Tag::create(['slug' => 'indexing', 'name' => 'Indexing']);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'key' => 'q-new-question',
            'trackSlug' => $this->track->slug,
            'type' => 'single_choice',
            'difficulty' => 'medium',
            'isActive' => true,
            'codeLanguage' => 'sql',
            'tags' => ['postgres'],
            'translations' => [
                'en' => ['stemMd' => 'Which index can serve this filter?', 'explanationMd' => 'Leftmost prefix.'],
                'id' => ['stemMd' => 'Index mana yang bisa melayani filter ini?', 'explanationMd' => 'Prefix paling kiri.'],
            ],
            'options' => [
                ['isCorrect' => true, 'translations' => ['en' => ['labelMd' => 'The composite one'], 'id' => ['labelMd' => 'Yang komposit']]],
                ['isCorrect' => false, 'translations' => ['en' => ['labelMd' => 'The partial one'], 'id' => ['labelMd' => 'Yang partial']]],
            ],
        ], $overrides);
    }

    public function test_a_learner_cannot_author_questions(): void
    {
        $learner = User::factory()->create();
        $learner->assignRole('learner');

        $this->actingAs($learner)->postJson('/api/v1/admin/questions', $this->payload())->assertForbidden();
        $this->actingAs($learner)->getJson('/api/v1/admin/questions')->assertForbidden();
    }

    public function test_a_guest_cannot_author_questions(): void
    {
        $this->postJson('/api/v1/admin/questions', $this->payload())->assertUnauthorized();
    }

    public function test_it_creates_a_question_with_its_first_version(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/questions', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.key', 'q-new-question')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.trackSlug', $this->track->slug)
            ->assertJsonPath('data.codeLanguage', 'sql')
            ->assertJsonPath('data.tags', ['postgres'])
            ->assertJsonPath('data.translations.id.stemMd', 'Index mana yang bisa melayani filter ini?')
            ->assertJsonPath('data.options.0.isCorrect', true)
            ->assertJsonPath('data.options.1.translations.en.labelMd', 'The partial one')
            ->assertJsonPath('data.history.0.version', 1)
            ->assertJsonPath('data.history.0.isCurrent', true);

        $question = Question::where('key', 'q-new-question')->sole();

        $this->assertNotNull($question->current_version_id);
        $this->assertSame(1, $question->versions()->count());
    }

    public function test_a_duplicate_key_is_refused(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/admin/questions', $this->payload())->assertCreated();

        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/questions', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('key');
    }

    public function test_editing_metadata_only_does_not_create_a_version(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/admin/questions', $this->payload())->assertCreated();

        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/questions/q-new-question', $this->payload([
                'difficulty' => 'hard',
                'tags' => ['postgres', 'indexing'],
            ]))
            ->assertOk()
            ->assertJsonPath('meta.newVersion', false)
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.difficulty', 'hard')
            ->assertJsonPath('data.tags', ['indexing', 'postgres']);

        $this->assertSame(1, Question::where('key', 'q-new-question')->sole()->versions()->count());
    }

    public function test_editing_the_stem_creates_a_new_version_and_keeps_the_old_one(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/admin/questions', $this->payload())->assertCreated();

        $payload = $this->payload();
        $payload['translations']['en']['stemMd'] = 'Which index serves this filter and the sort?';

        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/questions/q-new-question', $payload)
            ->assertOk()
            ->assertJsonPath('meta.newVersion', true)
            ->assertJsonPath('data.version', 2)
            ->assertJsonCount(2, 'data.history');

        $question = Question::where('key', 'q-new-question')->sole();

        $this->assertSame(2, $question->versions()->count());
        $this->assertSame('Which index serves this filter and the sort?', $question->currentVersion->t('stem_md', 'en'));
        $this->assertSame(
            'Which index can serve this filter?',
            $question->versions()->where('version', 1)->sole()->t('stem_md', 'en'),
            'the old version must still read as it was served',
        );
    }

    public function test_reordering_the_options_is_a_content_change(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/admin/questions', $this->payload())->assertCreated();

        $payload = $this->payload();
        $payload['options'] = array_reverse($payload['options']);

        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/questions/q-new-question', $payload)
            ->assertOk()
            ->assertJsonPath('meta.newVersion', true)
            ->assertJsonPath('data.options.0.isCorrect', false);
    }

    public function test_the_key_cannot_be_renamed(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/admin/questions', $this->payload())->assertCreated();

        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/questions/q-new-question', $this->payload(['key' => 'q-renamed']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('key');
    }

    public function test_a_single_choice_question_needs_exactly_one_correct_option(): void
    {
        $payload = $this->payload();
        $payload['options'][1]['isCorrect'] = true;

        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/questions', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('options');

        $payload['options'][0]['isCorrect'] = false;
        $payload['options'][1]['isCorrect'] = false;

        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/questions', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('options');
    }

    public function test_a_multi_choice_question_may_have_two_correct_options(): void
    {
        $payload = $this->payload(['type' => 'multi_choice']);
        $payload['options'][1]['isCorrect'] = true;

        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/questions', $payload)
            ->assertCreated()
            ->assertJsonPath('data.type', 'multi_choice');
    }

    public function test_the_fallback_locale_is_required_everywhere(): void
    {
        $payload = $this->payload();
        unset($payload['translations']['en']);

        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/questions', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('translations');

        $payload = $this->payload();
        unset($payload['options'][0]['translations']['en']);

        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/questions', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('options.0.translations');
    }

    public function test_an_unsupported_locale_is_refused(): void
    {
        $payload = $this->payload();
        $payload['translations']['fr'] = ['stemMd' => 'Quel index sert ce filtre ?'];

        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/questions', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('translations');
    }

    public function test_an_unknown_track_or_tag_is_refused_before_anything_is_written(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/questions', $this->payload(['trackSlug' => 'no-such-track']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('trackSlug');

        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/questions', $this->payload(['tags' => ['no-such-tag']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('tags.0');

        $this->assertSame(0, Question::count());
    }

    public function test_fewer_than_two_options_is_refused(): void
    {
        $payload = $this->payload();
        $payload['options'] = [$payload['options'][0]];

        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/questions', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('options');
    }

    public function test_the_list_reports_what_is_untranslated(): void
    {
        $english = $this->payload(['key' => 'q-english-only']);
        unset($english['translations']['id'], $english['options'][0]['translations']['id'], $english['options'][1]['translations']['id']);

        $this->actingAs($this->admin)->postJson('/api/v1/admin/questions', $this->payload())->assertCreated();
        $this->actingAs($this->admin)->postJson('/api/v1/admin/questions', $english)->assertCreated();

        $this->actingAs($this->admin)->getJson('/api/v1/admin/questions')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.key', 'q-english-only')
            ->assertJsonPath('data.0.missingLocales', ['id'])
            ->assertJsonPath('data.1.key', 'q-new-question')
            ->assertJsonPath('data.1.missingLocales', [])
            ->assertJsonPath('data.1.optionCount', 2)
            ->assertJsonPath('data.1.quizCount', 0);

        $this->actingAs($this->admin)->getJson('/api/v1/admin/questions?missingLocale=id')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'q-english-only');
    }

    public function test_the_list_filters_by_track_tag_difficulty_and_search(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/admin/questions', $this->payload())->assertCreated();
        $this->actingAs($this->admin)->postJson('/api/v1/admin/questions', $this->payload([
            'key' => 'q-second',
            'difficulty' => 'hard',
            'tags' => ['indexing'],
            'translations' => ['en' => ['stemMd' => 'What does a bitmap heap scan actually do?']],
        ]))->assertCreated();

        $this->actingAs($this->admin)->getJson('/api/v1/admin/questions?difficulty=hard')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.key', 'q-second');

        $this->actingAs($this->admin)->getJson('/api/v1/admin/questions?tag=indexing')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.key', 'q-second');

        $this->actingAs($this->admin)->getJson('/api/v1/admin/questions?q=bitmap')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.key', 'q-second');

        $this->actingAs($this->admin)->getJson('/api/v1/admin/questions?q=q-new')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.key', 'q-new-question');

        $this->actingAs($this->admin)->getJson('/api/v1/admin/questions?track='.$this->track->slug)
            ->assertJsonCount(2, 'data');
    }

    public function test_the_list_does_not_grow_its_query_count_with_more_questions(): void
    {
        foreach (range(1, 3) as $i) {
            $this->actingAs($this->admin)->postJson('/api/v1/admin/questions', $this->payload(['key' => "q-batch-a-{$i}"]))->assertCreated();
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->getJson('/api/v1/admin/questions')->assertJsonCount(3, 'data');
        $withThree = count(DB::getQueryLog());

        foreach (range(4, 12) as $i) {
            $this->actingAs($this->admin)->postJson('/api/v1/admin/questions', $this->payload(['key' => "q-batch-b-{$i}"]))->assertCreated();
        }

        DB::flushQueryLog();
        $this->actingAs($this->admin)->getJson('/api/v1/admin/questions')->assertJsonCount(12, 'data');
        $withTwelve = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($withThree, $withTwelve, "query count grew from {$withThree} to {$withTwelve}");
    }

    public function test_an_unknown_key_is_a_404(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/admin/questions/q-nope')->assertNotFound();
        $this->actingAs($this->admin)->putJson('/api/v1/admin/questions/q-nope', $this->payload())->assertNotFound();
    }
}
