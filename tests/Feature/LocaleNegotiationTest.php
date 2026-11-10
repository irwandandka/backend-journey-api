<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleNegotiationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['app.supported_locales' => 'en,id', 'app.fallback_locale' => 'en']);
    }

    public function test_a_guest_falls_back_when_nothing_is_requested(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertHeader('Content-Language', 'en');
    }

    public function test_the_query_parameter_wins(): void
    {
        $this->getJson('/api/v1/auth/me?locale=id')
            ->assertHeader('Content-Language', 'id');
    }

    public function test_an_unsupported_locale_falls_back_instead_of_erroring(): void
    {
        $this->getJson('/api/v1/auth/me?locale=fr')
            ->assertHeader('Content-Language', 'en');
    }

    public function test_the_accept_language_header_is_honoured(): void
    {
        $this->withHeader('Accept-Language', 'id-ID,id;q=0.9,en;q=0.5')
            ->getJson('/api/v1/auth/me')
            ->assertHeader('Content-Language', 'id');
    }

    public function test_the_stored_user_locale_beats_the_accept_language_header(): void
    {
        $user = User::factory()->create(['locale' => 'id']);
        $user->assignRole('learner');

        $this->actingAs($user)
            ->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertHeader('Content-Language', 'id');
    }

    public function test_the_query_parameter_beats_the_stored_user_locale(): void
    {
        $user = User::factory()->create(['locale' => 'id']);
        $user->assignRole('learner');

        $this->actingAs($user)
            ->getJson('/api/v1/auth/me?locale=en')
            ->assertOk()
            ->assertHeader('Content-Language', 'en');
    }
}
