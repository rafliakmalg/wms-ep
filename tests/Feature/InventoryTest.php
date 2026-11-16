<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Rack;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_guest_cannot_access_inventories(): void
    {
        $this->getJson('/api/inventories')->assertUnauthorized();
    }

    public function test_index_filters_by_product_sku_and_rack_code(): void
    {
        $this->signIn();
        $product = Product::factory()->create(['sku' => 'SKU-FILTER']);
        $rack = Rack::factory()->create(['code' => 'RACK-FILTER']);
        $bySku = Inventory::factory()->create(['product_id' => $product->id]);
        $byRack = Inventory::factory()->create(['rack_id' => $rack->id]);
        Inventory::factory()->create();

        $this->getJson('/api/inventories?filters[0][field]=product.sku&filters[0][operator]=like&filters[0][value]=SKU-FILTER')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $bySku->id);
        $this->getJson('/api/inventories?filters[0][field]=rack.code&filters[0][operator]=like&filters[0][value]=RACK-FILTER')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $byRack->id);
    }

    public function test_index_filters_by_quantity(): void
    {
        $this->signIn();
        Inventory::factory()->create(['quantity' => 5]);
        Inventory::factory()->create(['quantity' => 50]);

        $this->getJson('/api/inventories?filters[0][field]=quantity&filters[0][operator]=gte&filters[0][value]=10')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.quantity', '50.000');
    }

    public function test_index_filters_by_warehouse_and_branch_through_rack(): void
    {
        $this->signIn();
        $branch = Branch::factory()->create();
        $warehouse = Warehouse::factory()->create(['branch_id' => $branch->id]);
        $rack = Rack::factory()->create(['warehouse_id' => $warehouse->id]);
        Inventory::factory()->create(['rack_id' => $rack->id]);
        Inventory::factory()->count(2)->create();

        $this->getJson("/api/inventories?filters[0][field]=rack.warehouse_id&filters[0][operator]=eq&filters[0][value]={$warehouse->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rack_id', $rack->id);
        $this->getJson("/api/inventories?filters[0][field]=rack.warehouse.branch_id&filters[0][operator]=eq&filters[0][value]={$branch->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rack_id', $rack->id);
    }

    public function test_index_rejects_invalid_query(): void
    {
        $this->signIn();

        $this->getJson('/api/inventories?per_page=1000&sort_by=name')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'sort_by']);
    }

    public function test_index_returns_paginated_inventories_with_product_and_rack(): void
    {
        $this->signIn();
        Inventory::factory()->count(3)->create();

        $this->getJson('/api/inventories?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonStructure([
                'data' => [[
                    'id', 'product_id', 'rack_id', 'quantity', 'quantity_incoming',
                    'product' => ['id', 'sku', 'name', 'base_unit'],
                    'rack' => ['id', 'code', 'warehouse' => ['id', 'branch']],
                ]],
                'links',
                'meta',
            ]);
    }

    public function test_index_searches_by_product(): void
    {
        $this->signIn();
        $pen = Product::factory()->create([
            'name' => 'Pulpen Hitam',
            'sku' => 'PLP-1',
        ]);
        Inventory::factory()->create(['product_id' => $pen->id]);
        Inventory::factory()->create();

        $this->getJson('/api/inventories?search=pulpen')->assertJsonCount(1, 'data')->assertJsonPath('data.0.product_id', $pen->id);
        $this->getJson('/api/inventories?search=plp')->assertJsonCount(1, 'data');
    }
}
