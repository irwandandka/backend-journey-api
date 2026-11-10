<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_it_registers_a_learner_and_logs_them_in(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Irwanda',
            'email' => 'new@example.test',
            'password' => 'secret12345',
            'locale' => 'id',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'new@example.test')
            ->assertJsonPath('data.locale', 'id')
            ->assertJsonPath('data.roles', ['learner'])
            ->assertJsonPath('data.xpTotal', 0);

        $this->assertAuthenticated();
        $this->assertTrue(User::whereEmail('new@example.test')->sole()->hasRole('learner'));
    }

    public function test_it_never_returns_the_password_hash(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Irwanda',
            'email' => 'nohash@example.test',
            'password' => 'secret12345',
        ]);

        $response->assertCreated();
        $this->assertStringNotContainsString('password', $response->getContent());
    }

    public function test_it_defaults_the_locale_to_the_fallback(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Irwanda',
            'email' => 'nolocale@example.test',
            'password' => 'secret12345',
        ])->assertCreated()->assertJsonPath('data.locale', config('app.fallback_locale'));
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidPayloads(): array
    {
        return [
            'duplicate email' => [['email' => 'taken@example.test'], 'email'],
            'bad email' => [['email' => 'not-an-email'], 'email'],
            'short password' => [['password' => 'abc1'], 'password'],
            'letters only password' => [['password' => 'abcdefghij'], 'password'],
            'unsupported locale' => [['locale' => 'fr'], 'locale'],
            'blank name' => [['name' => ''], 'name'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_it_rejects_invalid_payloads(array $override, string $field): void
    {
        User::factory()->create(['email' => 'taken@example.test']);

        $this->postJson('/api/v1/auth/register', array_merge([
            'name' => 'Irwanda',
            'email' => 'fresh@example.test',
            'password' => 'secret12345',
        ], $override))->assertStatus(422)->assertJsonValidationErrors($field);
    }
}
