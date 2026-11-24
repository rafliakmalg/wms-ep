<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use App\Support\Jwt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $claims
     */
    private function token(array $claims = [], ?string $privateKeyPath = null): string
    {
        if ($privateKeyPath) {
            config(['jwt.private_key_path' => $privateKeyPath]);
        }

        $now = time();

        return Jwt::encode([
            'sub' => 7,
            'name' => 'Budi',
            'roles' => ['operator'],
            'permissions' => ['branch.read'],
            'iat' => $now,
            'exp' => $now + 3600,
            ...$claims,
        ]);
    }

    public function test_expired_token_is_rejected(): void
    {
        $this->withToken($this->token(['exp' => time() - 120]))->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_guest_is_rejected(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_me_returns_user_lang_and_permissions_from_the_token(): void
    {
        $response = $this->withToken($this->token())->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', 7)
            ->assertJsonPath('data.name', 'Budi')
            ->assertJsonPath('permissions', ['branch.read']);

        $this->assertSame('Cabang', $response->json('lang.branch'));
    }

    public function test_missing_permission_is_forbidden_but_granted_permission_works(): void
    {
        $token = $this->token(['permissions' => ['branch.read']]);

        $this->withToken($token)->getJson('/api/branches')->assertOk();
        $this->withToken($token)->postJson('/api/branches', [
            'code' => 'JKT',
            'name' => 'Jakarta',
        ])->assertForbidden();

        $this->assertSame(0, Branch::count());
    }

    public function test_token_creates_the_user_on_first_request_and_keeps_the_name_current(): void
    {
        $this->assertDatabaseMissing('users', ['id' => 7]);

        $this->withToken($this->token())->getJson('/api/auth/me')->assertOk();
        $this->assertDatabaseHas('users', ['id' => 7, 'name' => 'Budi']);

        $this->app['auth']->forgetGuards();

        $this->withToken($this->token(['name' => 'Budi Santoso']))->getJson('/api/auth/me')->assertOk();
        $this->assertDatabaseHas('users', ['id' => 7, 'name' => 'Budi Santoso']);
        $this->assertSame(1, User::count());
    }

    public function test_token_signed_with_another_key_is_rejected(): void
    {
        $forged = $this->token(privateKeyPath: $this->keyPath('foreign-private'));

        $this->withToken($forged)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_token_without_signature_is_rejected(): void
    {
        $payload = rtrim(strtr(base64_encode(json_encode([
            'sub' => 7,
            'name' => 'Budi',
            'roles' => [],
            'permissions' => ['branch.read'],
            'exp' => time() + 3600,
        ])), '+/', '-_'), '=');
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'none', 'typ' => 'JWT'])), '+/', '-_'), '=');

        $this->withToken("{$header}.{$payload}.")->getJson('/api/branches')->assertUnauthorized();
    }
}
