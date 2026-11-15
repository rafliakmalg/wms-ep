<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    private function signInAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_destroy_deletes_user(): void
    {
        $this->signInAsAdmin();
        $user = User::factory()->create();

        $this->deleteJson("/api/users/{$user->id}")->assertOk()->assertJsonPath('message', 'Pengguna berhasil dihapus.');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_destroy_returns_conflict_when_user_created_other_records(): void
    {
        $admin = $this->signInAsAdmin();
        $this->postJson('/api/categories', ['name' => 'Elektronik'])->assertCreated();

        $this->deleteJson("/api/users/{$admin->id}")->assertConflict();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_guest_cannot_access_users(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
    }

    public function test_index_filters_by_search_role_and_active(): void
    {
        $this->signInAsAdmin();
        $role = Role::factory()->create();
        User::factory()->create([
            'name' => 'Budi Santoso',
            'role_id' => $role->id,
        ]);
        User::factory()->create([
            'name' => 'Budi Nonaktif',
            'role_id' => $role->id,
            'active' => false,
        ]);
        User::factory()->create(['name' => 'Citra']);

        $this->getJson('/api/users?search=budi')->assertJsonCount(2, 'data');
        $this->getJson("/api/users?filters[0][field]=role_id&filters[0][operator]=eq&filters[0][value]={$role->id}")->assertJsonCount(2, 'data');
        $this->getJson("/api/users?filters[0][field]=role_id&filters[0][operator]=eq&filters[0][value]={$role->id}&filters[1][field]=active&filters[1][operator]=eq&filters[1][value]=0")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Budi Nonaktif');
    }

    public function test_index_rejects_invalid_query(): void
    {
        $this->signInAsAdmin();

        $this->getJson('/api/users?per_page=1000&sort_by=password')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'sort_by']);
    }

    public function test_index_returns_users_with_role_and_never_exposes_password(): void
    {
        $this->signInAsAdmin();

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'role_id', 'active', 'username', 'name', 'role' => ['id', 'name']]], 'links', 'meta'])
            ->assertJsonMissingPath('data.0.password');
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $role = Role::factory()->create();

        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/users', [
            'username' => 'a',
            'name' => 'A',
            'password' => 'password',
            'role_id' => $role->id,
        ])->assertForbidden();
    }

    public function test_show_returns_user_with_role(): void
    {
        $this->signInAsAdmin();
        $user = User::factory()->create();

        $this->getJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role.id', $user->role_id);
    }

    public function test_store_allows_missing_email_and_validates_it_when_given(): void
    {
        $this->signInAsAdmin();
        $role = Role::factory()->create();
        User::factory()->create(['email' => 'ada@example.com']);
        $payload = [
            'name' => 'X',
            'password' => 'password123',
            'role_id' => $role->id,
        ];

        $this->postJson('/api/users', [...$payload, 'username' => 'tanpa_email'])
            ->assertCreated()
            ->assertJsonPath('data.email', null);
        $this->postJson('/api/users', [...$payload, 'username' => 'tanpa_email_2'])->assertCreated();
        $this->postJson('/api/users', [
            ...$payload,
            'username' => 'email_salah',
            'email' => 'bukan-email',
        ])->assertJsonValidationErrors(['email']);
        $this->postJson('/api/users', [
            ...$payload,
            'username' => 'email_dobel',
            'email' => 'ada@example.com',
        ])->assertJsonValidationErrors(['email']);
    }

    public function test_store_creates_user_with_hashed_password_and_audit_columns(): void
    {
        $admin = $this->signInAsAdmin();
        $role = Role::factory()->create();

        $this->postJson('/api/users', [
            'username' => 'baru',
            'name' => 'Baru',
            'password' => 'rahasia123',
            'role_id' => $role->id,
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Pengguna berhasil ditambahkan.')
            ->assertJsonPath('data.username', 'baru')
            ->assertJsonPath('data.role.id', $role->id)
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.created_by_id', $admin->id)
            ->assertJsonMissingPath('data.password');

        $user = User::where('username', 'baru')->first();
        $this->assertNotSame('rahasia123', $user->password);
        $this->assertTrue(password_verify('rahasia123', $user->password));
        $this->assertTrue($user->active);
    }

    public function test_store_rejects_username_with_invalid_characters(): void
    {
        $this->signInAsAdmin();
        $role = Role::factory()->create();

        foreach (['ada spasi', 'user@mail', 'nama!'] as $username) {
            $this->postJson('/api/users', [
                'name' => 'X',
                'password' => 'password123',
                'role_id' => $role->id,
                'username' => $username,
            ])->assertJsonValidationErrors(['username']);
        }
    }

    public function test_store_validates_input(): void
    {
        $this->signInAsAdmin();
        User::factory()->create(['username' => 'ada']);

        $this->postJson('/api/users', [])
            ->assertJsonValidationErrors(['username', 'name', 'password', 'role_id']);

        $this->postJson('/api/users', [
            'username' => 'ada',
            'name' => 'X',
            'password' => 'pendek',
            'role_id' => 9999,
        ])->assertJsonValidationErrors(['username', 'password', 'role_id']);
    }

    public function test_update_allows_own_username_but_rejects_username_of_another_user(): void
    {
        $this->signInAsAdmin();
        User::factory()->create(['username' => 'milik-lain']);
        $user = User::factory()->create(['username' => 'awal']);
        $payload = [
            'name' => 'N',
            'role_id' => $user->role_id,
        ];

        $this->putJson("/api/users/{$user->id}", [...$payload, 'username' => 'awal'])->assertOk();
        $this->putJson("/api/users/{$user->id}", [...$payload, 'username' => 'milik-lain'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_update_can_deactivate_user(): void
    {
        $this->signInAsAdmin();
        $user = User::factory()->create();

        $this->putJson("/api/users/{$user->id}", [
            'active' => false,
            'username' => $user->username,
            'name' => $user->name,
            'role_id' => $user->role_id,
        ])
            ->assertOk()
            ->assertJsonPath('data.active', false);
    }

    public function test_update_with_password_changes_it(): void
    {
        $this->signInAsAdmin();
        $user = User::factory()->create();

        $this->putJson("/api/users/{$user->id}", [
            'username' => $user->username,
            'name' => $user->name,
            'password' => 'passwordbaru',
            'role_id' => $user->role_id,
        ])->assertOk();

        $this->assertTrue(password_verify('passwordbaru', $user->fresh()->password));
    }

    public function test_update_without_password_keeps_the_old_password(): void
    {
        $admin = $this->signInAsAdmin();
        $user = User::factory()->create(['username' => 'lama']);
        $oldHash = $user->password;

        $this->putJson("/api/users/{$user->id}", [
            'username' => 'lama',
            'name' => 'Nama Baru',
            'role_id' => $user->role_id,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Pengguna berhasil diperbarui.')
            ->assertJsonPath('data.name', 'Nama Baru')
            ->assertJsonPath('data.updated_by_id', $admin->id);

        $this->assertSame($oldHash, $user->fresh()->password);
    }
}
