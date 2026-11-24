<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Rack;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): User
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        return $user;
    }

    public function test_destroy_deletes_unused_warehouse(): void
    {
        $this->signIn();
        $warehouse = Warehouse::factory()->create();

        $this->deleteJson("/api/warehouses/{$warehouse->id}")->assertOk()->assertJsonPath('message', 'Gudang berhasil dihapus.');

        $this->assertDatabaseMissing('warehouses', ['id' => $warehouse->id]);
    }

    public function test_destroy_returns_conflict_when_warehouse_has_racks(): void
    {
        $this->signIn();
        $rack = Rack::factory()->create();

        $this->deleteJson("/api/warehouses/{$rack->warehouse_id}")->assertConflict();

        $this->assertDatabaseHas('warehouses', ['id' => $rack->warehouse_id]);
    }

    public function test_guest_cannot_access_warehouses(): void
    {
        $this->getJson('/api/warehouses')->assertUnauthorized();
    }

    public function test_index_filters_by_search_branch_and_active(): void
    {
        $this->signIn();
        $branch = Branch::factory()->create();
        Warehouse::factory()->create([
            'branch_id' => $branch->id,
            'code' => 'WH-A',
            'name' => 'Gudang Utama',
        ]);
        Warehouse::factory()->create([
            'branch_id' => $branch->id,
            'code' => 'WH-B',
            'active' => false,
        ]);
        Warehouse::factory()->create();

        $this->getJson('/api/warehouses?search=utama')->assertJsonCount(1, 'data');
        $this->getJson("/api/warehouses?filters[0][field]=branch_id&filters[0][operator]=eq&filters[0][value]={$branch->id}")->assertJsonCount(2, 'data');
        $this->getJson("/api/warehouses?filters[0][field]=branch_id&filters[0][operator]=eq&filters[0][value]={$branch->id}&filters[1][field]=active&filters[1][operator]=eq&filters[1][value]=0")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'WH-B');
    }

    public function test_index_rejects_invalid_query(): void
    {
        $this->signIn();

        $this->getJson('/api/warehouses?per_page=0&sort_dir=up')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'sort_dir']);
    }

    public function test_index_returns_warehouses_with_branch(): void
    {
        $this->signIn();
        Warehouse::factory()->count(3)->create();

        $this->getJson('/api/warehouses?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonStructure(['data' => [['id', 'branch_id', 'code', 'name', 'active', 'branch' => ['id', 'code']]], 'links', 'meta']);
    }

    public function test_show_returns_warehouse_with_branch(): void
    {
        $this->signIn();
        $warehouse = Warehouse::factory()->create();

        $this->getJson("/api/warehouses/{$warehouse->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $warehouse->id)
            ->assertJsonPath('data.branch.id', $warehouse->branch_id);
    }

    public function test_store_creates_warehouse_with_audit_columns(): void
    {
        $user = $this->signIn();
        $branch = Branch::factory()->create();

        $this->postJson('/api/warehouses', [
            'branch_id' => $branch->id,
            'code' => 'WH-1',
            'name' => 'Gudang 1',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Gudang berhasil ditambahkan.')
            ->assertJsonPath('data.branch.id', $branch->id)
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.created_by_id', $user->id);
    }

    public function test_store_validates_input(): void
    {
        $this->signIn();
        Warehouse::factory()->create(['code' => 'WH-1']);

        $this->postJson('/api/warehouses', [])->assertJsonValidationErrors(['branch_id', 'code', 'name']);
        $this->postJson('/api/warehouses', [
            'branch_id' => 9999,
            'code' => 'WH-1',
            'name' => 'X',
        ])
            ->assertJsonValidationErrors(['branch_id', 'code']);
    }

    public function test_update_allows_own_code_and_rejects_code_of_another_warehouse(): void
    {
        $user = $this->signIn();
        Warehouse::factory()->create(['code' => 'WH-2']);
        $warehouse = Warehouse::factory()->create(['code' => 'WH-1']);
        $payload = [
            'branch_id' => $warehouse->branch_id,
            'name' => 'Baru',
        ];

        $this->putJson("/api/warehouses/{$warehouse->id}", [...$payload, 'code' => 'WH-1'])
            ->assertOk()
            ->assertJsonPath('message', 'Gudang berhasil diperbarui.')
            ->assertJsonPath('data.name', 'Baru')
            ->assertJsonPath('data.updated_by_id', $user->id);

        $this->putJson("/api/warehouses/{$warehouse->id}", [...$payload, 'code' => 'WH-2'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }
}
