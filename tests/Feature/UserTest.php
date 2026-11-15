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

    public function test_guest_cannot_access_users(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $role = Role::factory()->create();

        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/users', [
            'email' => 'a@example.com',
            'name' => 'A',
            'password' => 'password',
            'role_id' => $role->id,
        ])->assertForbidden();
    }

    public function test_index_returns_users_with_role_and_never_exposes_password(): void
    {
        $this->signInAsAdmin();

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'role_id', 'active', 'email', 'name', 'role' => ['id', 'name']]], 'links', 'meta'])
            ->assertJsonMissingPath('data.0.password');
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
        $this->getJson("/api/users?role_id={$role->id}")->assertJsonCount(2, 'data');
        $this->getJson("/api/users?role_id={$role->id}&active=0")
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

    public function test_store_creates_user_with_hashed_password_and_audit_columns(): void
    {
        $admin = $this->signInAsAdmin();
        $role = Role::factory()->create();

        $this->postJson('/api/users', [
            'email' => 'baru@example.com',
            'name' => 'Baru',
            'password' => 'rahasia123',
            'role_id' => $role->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'baru@example.com')
            ->assertJsonPath('data.role.id', $role->id)
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.created_by_id', $admin->id)
            ->assertJsonMissingPath('data.password');

        $user = User::where('email', 'baru@example.com')->first();
        $this->assertNotSame('rahasia123', $user->password);
        $this->assertTrue(password_verify('rahasia123', $user->password));
        $this->assertTrue($user->active);
    }

    public function test_store_validates_input(): void
    {
        $this->signInAsAdmin();
        User::factory()->create(['email' => 'ada@example.com']);

        $this->postJson('/api/users', [])
            ->assertJsonValidationErrors(['email', 'name', 'password', 'role_id']);

        $this->postJson('/api/users', [
            'email' => 'ada@example.com',
            'name' => 'X',
            'password' => 'pendek',
            'role_id' => 9999,
        ])->assertJsonValidationErrors(['email', 'password', 'role_id']);
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

    public function test_update_without_password_keeps_the_old_password(): void
    {
        $admin = $this->signInAsAdmin();
        $user = User::factory()->create(['email' => 'lama@example.com']);
        $oldHash = $user->password;

        $this->putJson("/api/users/{$user->id}", [
            'email' => 'lama@example.com',
            'name' => 'Nama Baru',
            'role_id' => $user->role_id,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nama Baru')
            ->assertJsonPath('data.updated_by_id', $admin->id);

        $this->assertSame($oldHash, $user->fresh()->password);
    }

    public function test_update_with_password_changes_it(): void
    {
        $this->signInAsAdmin();
        $user = User::factory()->create();

        $this->putJson("/api/users/{$user->id}", [
            'email' => $user->email,
            'name' => $user->name,
            'password' => 'passwordbaru',
            'role_id' => $user->role_id,
        ])->assertOk();

        $this->assertTrue(password_verify('passwordbaru', $user->fresh()->password));
    }

    public function test_update_allows_own_email_but_rejects_email_of_another_user(): void
    {
        $this->signInAsAdmin();
        User::factory()->create(['email' => 'milik-lain@example.com']);
        $user = User::factory()->create(['email' => 'awal@example.com']);
        $payload = [
            'name' => 'N',
            'role_id' => $user->role_id,
        ];

        $this->putJson("/api/users/{$user->id}", [...$payload, 'email' => 'awal@example.com'])->assertOk();
        $this->putJson("/api/users/{$user->id}", [...$payload, 'email' => 'milik-lain@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_update_can_deactivate_user(): void
    {
        $this->signInAsAdmin();
        $user = User::factory()->create();

        $this->putJson("/api/users/{$user->id}", [
            'active' => false,
            'email' => $user->email,
            'name' => $user->name,
            'role_id' => $user->role_id,
        ])
            ->assertOk()
            ->assertJsonPath('data.active', false);
    }

    public function test_destroy_deletes_user(): void
    {
        $this->signInAsAdmin();
        $user = User::factory()->create();

        $this->deleteJson("/api/users/{$user->id}")->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_destroy_returns_conflict_when_user_created_other_records(): void
    {
        $admin = $this->signInAsAdmin();
        $this->postJson('/api/categories', ['name' => 'Elektronik'])->assertCreated();

        $this->deleteJson("/api/users/{$admin->id}")->assertConflict();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
