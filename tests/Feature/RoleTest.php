<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    private function signInAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_guest_cannot_access_roles(): void
    {
        $this->getJson('/api/roles')->assertUnauthorized();
    }

    public function test_non_admin_cannot_access_roles(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/roles')->assertForbidden();
        $this->postJson('/api/roles', [
            'label' => 'X',
            'name' => 'x',
        ])->assertForbidden();
    }

    public function test_index_returns_paginated_roles_and_filters_by_search(): void
    {
        $this->signInAsAdmin();
        Role::factory()->create([
            'label' => 'Gudang',
            'name' => 'gudang',
        ]);

        $this->getJson('/api/roles?search=gud')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'gudang')
            ->assertJsonStructure(['data' => [['id', 'created_at', 'updated_at', 'created_by_id', 'updated_by_id', 'label', 'name']], 'links', 'meta']);
    }

    public function test_index_rejects_invalid_query(): void
    {
        $this->signInAsAdmin();

        $this->getJson('/api/roles?per_page=1000&sort_by=id')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'sort_by']);
    }

    public function test_store_creates_role_with_audit_columns(): void
    {
        $user = $this->signInAsAdmin();

        $this->postJson('/api/roles', [
            'label' => 'Supervisor',
            'name' => 'supervisor',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'supervisor')
            ->assertJsonPath('data.created_by_id', $user->id)
            ->assertJsonPath('data.updated_by_id', $user->id);
    }

    public function test_store_rejects_missing_and_duplicate_name(): void
    {
        $this->signInAsAdmin();
        Role::factory()->create(['name' => 'gudang']);

        $this->postJson('/api/roles', [])->assertJsonValidationErrors(['label', 'name']);
        $this->postJson('/api/roles', [
            'label' => 'Gudang',
            'name' => 'gudang',
        ])->assertJsonValidationErrors(['name']);
    }

    public function test_show_returns_role(): void
    {
        $this->signInAsAdmin();
        $role = Role::factory()->create();

        $this->getJson("/api/roles/{$role->id}")->assertOk()->assertJsonPath('data.id', $role->id);
    }

    public function test_update_changes_role_and_allows_keeping_the_same_name(): void
    {
        $user = $this->signInAsAdmin();
        $role = Role::factory()->create(['name' => 'lama']);

        $this->putJson("/api/roles/{$role->id}", [
            'label' => 'Baru',
            'name' => 'lama',
        ])
            ->assertOk()
            ->assertJsonPath('data.label', 'Baru')
            ->assertJsonPath('data.updated_by_id', $user->id);
    }

    public function test_update_rejects_name_used_by_another_role(): void
    {
        $this->signInAsAdmin();
        Role::factory()->create(['name' => 'milik-lain']);
        $role = Role::factory()->create(['name' => 'awal']);

        $this->putJson("/api/roles/{$role->id}", [
            'label' => 'Awal',
            'name' => 'milik-lain',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_destroy_deletes_unused_role(): void
    {
        $this->signInAsAdmin();
        $role = Role::factory()->create();

        $this->deleteJson("/api/roles/{$role->id}")->assertNoContent();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_destroy_returns_conflict_when_role_has_users(): void
    {
        $user = $this->signInAsAdmin();

        $this->deleteJson("/api/roles/{$user->role_id}")->assertConflict();

        $this->assertDatabaseHas('roles', ['id' => $user->role_id]);
    }
}
