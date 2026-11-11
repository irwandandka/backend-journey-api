<?php

namespace Tests\Feature\Learning;

use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ThrottleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['locale' => 'en']);
        $track = LearningFixture::track(LearningFixture::roadmap(), 'indexing');
        $this->quiz = LearningFixture::quiz(LearningFixture::journey($track, 'composite-index', 1), questionCount: 2);
    }

    /** @return array<string, string> */
    private function limiters(): array
    {
        $limits = [];

        foreach (Route::getRoutes() as $route) {
            $throttle = collect($route->gatherMiddleware())
                ->first(fn (mixed $middleware) => is_string($middleware) && str_starts_with($middleware, 'throttle:'));

            if ($throttle !== null) {
                $limits[$route->uri()] = $throttle;
            }
        }

        return $limits;
    }

    public function test_every_write_endpoint_carries_a_rate_limit(): void
    {
        $limits = $this->limiters();

        $expected = [
            'api/v1/auth/register' => 'throttle:10,1',
            'api/v1/auth/login' => 'throttle:6,1',
            'api/v1/quizzes/{quizSlug}/attempts' => 'throttle:30,1',
            'api/v1/attempts/{attemptId}/submit' => 'throttle:30,1',
            'api/v1/attempts/{attemptId}/answers' => 'throttle:120,1',
            'api/v1/review/attempts' => 'throttle:30,1',
        ];

        foreach ($expected as $uri => $limit) {
            $this->assertArrayHasKey($uri, $limits, "{$uri} has no rate limit");
            $this->assertSame($limit, $limits[$uri], "{$uri} has the wrong rate limit");
        }
    }

    public function test_starting_attempts_is_refused_past_the_limit(): void
    {
        $this->actingAs($this->user);

        for ($i = 0; $i < 30; $i++) {
            $this->postJson("/api/v1/quizzes/{$this->quiz->slug}/attempts")->assertSuccessful();
        }

        $this->postJson("/api/v1/quizzes/{$this->quiz->slug}/attempts")
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_the_limit_does_not_apply_to_reading_the_served_questions(): void
    {
        $this->actingAs($this->user);

        $attemptId = $this->postJson("/api/v1/quizzes/{$this->quiz->slug}/attempts")->json('data.id');

        for ($i = 0; $i < 40; $i++) {
            $this->getJson("/api/v1/attempts/{$attemptId}/questions")->assertOk();
        }
    }
}
