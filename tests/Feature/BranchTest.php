<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): User
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        return $user;
    }

    public function test_destroy_deletes_unused_branch(): void
    {
        $this->signIn();
        $branch = Branch::factory()->create();

        $this->deleteJson("/api/branches/{$branch->id}")->assertOk()->assertJsonPath('message', 'Cabang berhasil dihapus.');

        $this->assertDatabaseMissing('branches', ['id' => $branch->id]);
    }

    public function test_destroy_returns_conflict_when_branch_has_warehouses(): void
    {
        $this->signIn();
        $warehouse = Warehouse::factory()->create();

        $this->deleteJson("/api/branches/{$warehouse->branch_id}")->assertConflict();

        $this->assertDatabaseHas('branches', ['id' => $warehouse->branch_id]);
    }

    public function test_guest_cannot_access_branches(): void
    {
        $this->getJson('/api/branches')->assertUnauthorized();
    }

    public function test_index_filters_by_search_and_active(): void
    {
        $this->signIn();
        Branch::factory()->create([
            'code' => 'JKT',
            'name' => 'Jakarta',
        ]);
        Branch::factory()->create([
            'code' => 'SBY',
            'name' => 'Surabaya',
            'active' => false,
        ]);

        $this->getJson('/api/branches?search=jak')->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'JKT');
        $this->getJson('/api/branches?filters[0][field]=active&filters[0][operator]=eq&filters[0][value]=0')->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'SBY');
    }

    public function test_index_rejects_invalid_query(): void
    {
        $this->signIn();

        $this->getJson('/api/branches?per_page=1000&sort_by=phone')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'sort_by']);
    }

    public function test_index_returns_paginated_branches(): void
    {
        $this->signIn();
        Branch::factory()->count(3)->create();

        $this->getJson('/api/branches?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonStructure(['data' => [['id', 'created_at', 'updated_at', 'created_by_id', 'updated_by_id', 'address', 'code', 'name', 'phone', 'active']], 'links', 'meta']);
    }

    public function test_show_returns_branch(): void
    {
        $this->signIn();
        $branch = Branch::factory()->create();

        $this->getJson("/api/branches/{$branch->id}")->assertOk()->assertJsonPath('data.id', $branch->id);
    }

    public function test_store_creates_active_branch_with_audit_columns(): void
    {
        $user = $this->signIn();

        $this->postJson('/api/branches', [
            'code' => 'JKT',
            'name' => 'Jakarta',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Cabang berhasil ditambahkan.')
            ->assertJsonPath('data.code', 'JKT')
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.created_by_id', $user->id)
            ->assertJsonPath('data.updated_by_id', $user->id);
    }

    public function test_store_rejects_missing_and_duplicate_code(): void
    {
        $this->signIn();
        Branch::factory()->create(['code' => 'JKT']);

        $this->postJson('/api/branches', [])->assertJsonValidationErrors(['code', 'name']);
        $this->postJson('/api/branches', [
            'code' => 'JKT',
            'name' => 'Lain',
        ])->assertJsonValidationErrors(['code']);
    }

    public function test_update_allows_own_code_and_rejects_code_of_another_branch(): void
    {
        $user = $this->signIn();
        Branch::factory()->create(['code' => 'SBY']);
        $branch = Branch::factory()->create(['code' => 'JKT']);

        $this->putJson("/api/branches/{$branch->id}", [
            'code' => 'JKT',
            'name' => 'Jakarta Baru',
            'active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Cabang berhasil diperbarui.')
            ->assertJsonPath('data.name', 'Jakarta Baru')
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.updated_by_id', $user->id);

        $this->putJson("/api/branches/{$branch->id}", [
            'code' => 'SBY',
            'name' => 'X',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }
}
