<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_rejects_inactive_user(): void
    {
        User::factory()->create([
            'username' => 'a',
            'active' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'username' => 'a',
            'password' => 'password',
        ])
            ->assertUnprocessable();
    }

    public function test_login_rejects_unknown_username(): void
    {
        $this->postJson('/api/auth/login', [
            'username' => 'x',
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->create(['username' => 'a']);

        $this->postJson('/api/auth/login', [
            'username' => 'a',
            'password' => 'salah',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_login_requires_username_and_password(): void
    {
        $this->postJson('/api/auth/login', [])->assertJsonValidationErrors(['username', 'password']);
    }

    public function test_login_returns_token_and_user_with_role(): void
    {
        $user = User::factory()->create(['username' => 'a']);

        $this->postJson('/api/auth/login', [
            'username' => 'a',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.role.id', $user->role_id)
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_logout_deletes_the_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_me_returns_current_user_with_role(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role.id', $user->role_id);
    }

    public function test_seeded_users_can_login_with_the_default_password(): void
    {
        $this->seed();

        foreach (['admin', 'supervisor', 'operator'] as $role) {
            $this->postJson('/api/auth/login', [
                'username' => $role,
                'password' => 'admin123',
            ])
                ->assertOk()
                ->assertJsonPath('data.user.role.name', $role);
        }
    }
}
