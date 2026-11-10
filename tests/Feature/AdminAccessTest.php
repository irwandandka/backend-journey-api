<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_a_guest_cannot_reach_the_admin_surface(): void
    {
        $this->getJson('/api/v1/admin/ping')->assertUnauthorized();
    }

    public function test_a_learner_cannot_reach_the_admin_surface(): void
    {
        $user = User::factory()->create();
        $user->assignRole('learner');

        $this->actingAs($user)->getJson('/api/v1/admin/ping')->assertForbidden();
    }

    public function test_an_admin_can_reach_the_admin_surface(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)->getJson('/api/v1/admin/ping')
            ->assertOk()
            ->assertJsonPath('data.ok', true);
    }

    public function test_the_admin_role_carries_the_content_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->assertTrue($user->can('manage-content'));
        $this->assertTrue($user->can('view-analytics'));
    }
}
