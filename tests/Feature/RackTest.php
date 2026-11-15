<?php

namespace Tests\Feature;

use App\Models\Rack;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RackTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_guest_cannot_access_racks(): void
    {
        $this->getJson('/api/racks')->assertUnauthorized();
    }

    public function test_index_returns_racks_with_warehouse(): void
    {
        $this->signIn();
        Rack::factory()->count(3)->create();

        $this->getJson('/api/racks?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonStructure(['data' => [['id', 'warehouse_id', 'code', 'name', 'active', 'warehouse' => ['id', 'code']]], 'links', 'meta']);
    }

    public function test_index_filters_by_search_warehouse_and_active(): void
    {
        $this->signIn();
        $warehouse = Warehouse::factory()->create();
        Rack::factory()->create([
            'warehouse_id' => $warehouse->id,
            'code' => 'A-01',
        ]);
        Rack::factory()->create([
            'warehouse_id' => $warehouse->id,
            'code' => 'B-01',
            'active' => false,
        ]);
        Rack::factory()->create(['code' => 'A-02']);

        $this->getJson('/api/racks?search=A-0')->assertJsonCount(2, 'data');
        $this->getJson("/api/racks?warehouse_id={$warehouse->id}")->assertJsonCount(2, 'data');
        $this->getJson("/api/racks?warehouse_id={$warehouse->id}&active=0")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'B-01');
    }

    public function test_index_rejects_invalid_query(): void
    {
        $this->signIn();

        $this->getJson('/api/racks?per_page=1000&sort_by=id')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'sort_by']);
    }

    public function test_store_creates_rack_with_audit_columns(): void
    {
        $user = $this->signIn();
        $warehouse = Warehouse::factory()->create();

        $this->postJson('/api/racks', [
            'code' => 'A-01',
            'warehouse_id' => $warehouse->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.warehouse.id', $warehouse->id)
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.created_by_id', $user->id);
    }

    public function test_store_validates_input(): void
    {
        $this->signIn();

        $this->postJson('/api/racks', [])->assertJsonValidationErrors(['code', 'warehouse_id']);
        $this->postJson('/api/racks', [
            'code' => 'A-01',
            'warehouse_id' => 9999,
        ])
            ->assertJsonValidationErrors(['warehouse_id']);
    }

    public function test_code_is_unique_per_warehouse_only(): void
    {
        $this->signIn();
        $first = Warehouse::factory()->create();
        $second = Warehouse::factory()->create();
        Rack::factory()->create([
            'code' => 'A-01',
            'warehouse_id' => $first->id,
        ]);

        $this->postJson('/api/racks', [
            'code' => 'A-01',
            'warehouse_id' => $first->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
        $this->postJson('/api/racks', [
            'code' => 'A-01',
            'warehouse_id' => $second->id,
        ])->assertCreated();
    }

    public function test_show_returns_rack_with_warehouse(): void
    {
        $this->signIn();
        $rack = Rack::factory()->create();

        $this->getJson("/api/racks/{$rack->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $rack->id)
            ->assertJsonPath('data.warehouse.id', $rack->warehouse_id);
    }

    public function test_update_allows_own_code_and_rejects_code_of_another_rack_in_same_warehouse(): void
    {
        $user = $this->signIn();
        $warehouse = Warehouse::factory()->create();
        Rack::factory()->create([
            'code' => 'A-02',
            'warehouse_id' => $warehouse->id,
        ]);
        $rack = Rack::factory()->create([
            'code' => 'A-01',
            'warehouse_id' => $warehouse->id,
        ]);

        $this->putJson("/api/racks/{$rack->id}", [
            'code' => 'A-01',
            'name' => 'Baru',
            'warehouse_id' => $warehouse->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Baru')
            ->assertJsonPath('data.updated_by_id', $user->id);

        $this->putJson("/api/racks/{$rack->id}", [
            'code' => 'A-02',
            'warehouse_id' => $warehouse->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_destroy_deletes_rack(): void
    {
        $this->signIn();
        $rack = Rack::factory()->create();

        $this->deleteJson("/api/racks/{$rack->id}")->assertNoContent();

        $this->assertDatabaseMissing('racks', ['id' => $rack->id]);
    }
}
