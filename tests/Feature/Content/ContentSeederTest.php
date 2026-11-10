<?php

namespace Tests\Feature\Content;

use App\Models\Journey;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionVersion;
use App\Models\Quiz;
use App\Models\Track;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContentSeederTest extends TestCase
{
    use RefreshDatabase;

    private function sync(array $content): void
    {
        app(ContentSeeder::class)->sync($content);
    }

    public function test_it_imports_the_whole_tree(): void
    {
        $this->sync(ContentFixture::minimal());

        $this->assertSame(1, Track::count());
        $this->assertSame(1, Journey::count());
        $this->assertSame(1, Quiz::count());
        $this->assertSame(1, Question::count());
        $this->assertSame(1, QuestionVersion::count());
        $this->assertSame(2, QuestionOption::count());
        $this->assertSame(1, DB::table('quiz_questions')->count());

        $question = Question::where('key', 'q-stampede')->sole();
        $this->assertNotNull($question->current_version_id);
        $this->assertSame(['redis'], $question->tags->pluck('slug')->all());
    }

    public function test_it_is_idempotent(): void
    {
        $this->sync(ContentFixture::minimal());
        $this->sync(ContentFixture::minimal());
        $this->sync(ContentFixture::minimal());

        $this->assertSame(1, Track::count());
        $this->assertSame(1, Journey::count());
        $this->assertSame(1, Question::count());
        $this->assertSame(1, QuestionVersion::count(), 'unchanged content must not create a new version');
        $this->assertSame(2, QuestionOption::count());
        $this->assertSame(1, DB::table('quiz_questions')->count());
        $this->assertSame(1, DB::table('question_tag')->count());
    }

    public function test_changed_question_content_creates_a_new_version_and_leaves_the_old_one_intact(): void
    {
        $this->sync(ContentFixture::minimal('Use SET NX EX'));

        $first = Question::where('key', 'q-stampede')->sole()->currentVersion;

        $this->sync(ContentFixture::minimal('Hold a short rebuild lock'));

        $question = Question::where('key', 'q-stampede')->sole()->fresh();
        $second = $question->currentVersion;

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertSame(2, QuestionVersion::count());
        $this->assertSame(4, QuestionOption::count(), 'the old version keeps its own options');

        $this->assertSame(
            'Use SET NX EX',
            $first->options()->where('is_correct', true)->sole()->t('label_md'),
        );
        $this->assertSame(
            'Hold a short rebuild lock',
            $second->options()->where('is_correct', true)->sole()->t('label_md'),
        );
    }

    public function test_it_falls_back_to_english_when_a_translation_is_missing(): void
    {
        $this->sync(ContentFixture::minimal());

        app()->setLocale('id');

        $track = Track::withTranslations()->where('slug', 'caching')->sole();
        $journey = Journey::withTranslations()->where('slug', 'stampede')->sole();

        $this->assertSame('Caching & Invalidasi', $track->t('name'));
        $this->assertSame('Cache Stampede', $journey->t('title'), 'no id row exists, so en must be served');
    }

    public function test_it_rejects_a_quiz_that_points_at_an_unknown_question(): void
    {
        $content = ContentFixture::minimal();
        $content['tracks'][0]['journeys'][0]['quiz']['questions'] = ['q-does-not-exist'];

        $this->expectException(\RuntimeException::class);
        $this->sync($content);
    }

    public function test_a_failed_import_leaves_nothing_behind(): void
    {
        $content = ContentFixture::minimal();
        $content['questions'][0]['track'] = 'no-such-track';

        try {
            $this->sync($content);
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame(0, Track::count());
        $this->assertSame(0, Question::count());
    }
}
