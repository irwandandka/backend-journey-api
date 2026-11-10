<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        RateLimiter::clear('');
    }

    private function learner(): User
    {
        $user = User::factory()->create([
            'email' => 'learner@example.test',
            'password' => 'secret12345',
        ]);
        $user->assignRole('learner');

        return $user;
    }

    public function test_it_logs_in_with_a_session_cookie(): void
    {
        $this->learner();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'learner@example.test',
            'password' => 'secret12345',
        ])->assertOk()->assertJsonPath('data.roles', ['learner']);

        $this->assertAuthenticated();
        $this->getJson('/api/v1/auth/me')->assertOk()
            ->assertJsonPath('data.email', 'learner@example.test');
    }

    public function test_it_rejects_a_wrong_password(): void
    {
        $this->learner();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'learner@example.test',
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_it_rejects_an_unknown_email_without_leaking_which_part_failed(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.test',
            'password' => 'secret12345',
        ])->assertStatus(422);

        $this->assertSame(
            __('auth.failed'),
            $response->json('errors.email.0'),
        );
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_logout_clears_the_session(): void
    {
        $this->learner();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'learner@example.test',
            'password' => 'secret12345',
        ])->assertOk();

        $this->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertGuest('web');

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_login_is_rate_limited(): void
    {
        $this->learner();

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'learner@example.test',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'learner@example.test',
            'password' => 'secret12345',
        ])->assertStatus(429);
    }
}
