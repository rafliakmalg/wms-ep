<?php

namespace Tests\Feature;

use App\Models\Adjustment;
use App\Models\Handover;
use App\Models\Inventory;
use App\Models\Movement;
use App\Models\Product;
use App\Models\Rack;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovementTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): User
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        return $user;
    }

    private function stock(Product $product, Rack $rack, float $quantity): void
    {
        Inventory::factory()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'rack_id' => $rack->id,
        ]);
    }

    public function test_guest_cannot_access_movements(): void
    {
        $this->getJson('/api/movements')->assertUnauthorized();
        $this->postJson('/api/movements', [])->assertUnauthorized();
    }

    public function test_index_derives_type_from_foreign_keys(): void
    {
        $this->signIn();
        $rackA = Rack::factory()->create();
        $rackB = Rack::factory()->create();
        $handover = Handover::factory()->create();
        $cancelled = Handover::factory()->create(['status' => Handover::STATUS_CANCELLED]);

        $movements = [
            'receive' => Movement::factory()->create(['rack_to_id' => $rackA->id]),
            'issue' => Movement::factory()->create([
                'rack_from_id' => $rackA->id,
                'rack_to_id' => null,
            ]),
            'transfer' => Movement::factory()->create([
                'rack_from_id' => $rackA->id,
                'rack_to_id' => $rackB->id,
            ]),
            'adjust' => Movement::factory()->create(['adjustment_id' => Adjustment::factory()->create()->id]),
            'take' => Movement::factory()->create([
                'handover_id' => $handover->id,
                'rack_from_id' => $rackA->id,
                'rack_to_id' => null,
            ]),
            'complete' => Movement::factory()->create([
                'handover_id' => $handover->id,
                'rack_to_id' => $rackB->id,
            ]),
            'return' => Movement::factory()->create([
                'handover_id' => $cancelled->id,
                'rack_to_id' => $rackA->id,
            ]),
        ];

        $types = collect($this->getJson('/api/movements?per_page=100')->assertOk()->json('data'))->pluck('type', 'id');

        foreach ($movements as $type => $movement) {
            $this->assertSame($type, $types[$movement->id]);
        }
    }

    public function test_index_filters_and_sorts_newest_first(): void
    {
        $this->signIn();
        $product = Product::factory()->create(['name' => 'Kabel USB']);
        $old = Movement::factory()->create([
            'created_at' => now()->subDay(),
            'product_id' => $product->id,
        ]);
        $new = Movement::factory()->create(['product_id' => $product->id]);
        Movement::factory()->create();

        $this->getJson('/api/movements?search=kabel')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $new->id)
            ->assertJsonPath('data.1.id', $old->id);
        $this->getJson("/api/movements?filters[0][field]=product_id&filters[0][operator]=eq&filters[0][value]={$product->id}&sort_dir=asc")
            ->assertJsonPath('data.0.id', $old->id);
    }

    public function test_index_filters_by_product_sku_racks_and_document_codes(): void
    {
        $this->signIn();
        $product = Product::factory()->create(['sku' => 'SKU-FILTER']);
        $warehouse = Warehouse::factory()->create();
        $rackFrom = Rack::factory()->create([
            'code' => 'FROM-01',
            'warehouse_id' => $warehouse->id,
        ]);
        $rackTo = Rack::factory()->create([
            'code' => 'TO-01',
            'warehouse_id' => $warehouse->id,
        ]);
        $adjustment = Adjustment::factory()->create(['code' => 'PN-FILTER']);
        $handover = Handover::factory()->create(['code' => 'PM-FILTER']);
        $bySku = Movement::factory()->create(['product_id' => $product->id]);
        $byRackFrom = Movement::factory()->create(['rack_from_id' => $rackFrom->id]);
        $byRackTo = Movement::factory()->create(['rack_to_id' => $rackTo->id]);
        $byAdjustment = Movement::factory()->create(['adjustment_id' => $adjustment->id]);
        $byHandover = Movement::factory()->create(['handover_id' => $handover->id]);
        Movement::factory()->create();

        $filters = [
            ['product.sku', 'like', 'FILTER', $bySku],
            ['rack_from.code', 'like', 'FROM', $byRackFrom],
            ['rack_from.warehouse_id', 'eq', $warehouse->id, $byRackFrom],
            ['rack_to.code', 'like', 'TO-', $byRackTo],
            ['rack_to.warehouse_id', 'eq', $warehouse->id, $byRackTo],
            ['adjustment.code', 'like', 'PN-', $byAdjustment],
            ['handover.code', 'like', 'PM-', $byHandover],
        ];

        foreach ($filters as [$field, $operator, $value, $expected]) {
            $this->getJson("/api/movements?filters[0][field]={$field}&filters[0][operator]={$operator}&filters[0][value]={$value}")
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $expected->id);
        }
    }

    public function test_index_rejects_invalid_query(): void
    {
        $this->signIn();

        $this->getJson('/api/movements?per_page=1000&sort_by=note')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'sort_by']);
    }

    public function test_store_issue_decreases_inventory_and_rejects_insufficient_stock(): void
    {
        $this->signIn();
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();
        $this->stock($product, $rack, 10);

        $this->postJson('/api/movements', [
            'product_id' => $product->id,
            'quantity' => 4,
            'rack_from_id' => $rack->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'issue');

        $this->assertSame('6.000', Inventory::where('rack_id', $rack->id)->value('quantity'));

        $errors = $this->postJson('/api/movements', [
            'product_id' => $product->id,
            'quantity' => 6.001,
            'rack_from_id' => $rack->id,
        ])
            ->assertUnprocessable()
            ->json('errors');

        $this->assertSame('Stok tidak mencukupi.', $errors['quantity'][0]);
        $this->assertSame('6.000', Inventory::where('rack_id', $rack->id)->value('quantity'));
        $this->assertSame(1, Movement::count());
    }

    public function test_store_receive_creates_inventory_with_audit_columns(): void
    {
        $user = $this->signIn();
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();

        $this->postJson('/api/movements', [
            'note' => 'Dari pemasok',
            'product_id' => $product->id,
            'quantity' => '12.5',
            'rack_to_id' => $rack->id,
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Pergerakan berhasil ditambahkan.')
            ->assertJsonPath('data.type', 'receive')
            ->assertJsonPath('data.quantity', '12.500')
            ->assertJsonPath('data.created_by_id', $user->id)
            ->assertJsonPath('data.rack_to.id', $rack->id);

        $inventory = Inventory::where('rack_id', $rack->id)->first();

        $this->assertSame('12.500', $inventory->quantity);
        $this->assertSame('0.000', $inventory->quantity_incoming);
        $this->assertSame($user->id, $inventory->created_by_id);
    }

    public function test_store_rejects_invalid_input(): void
    {
        $this->signIn();
        $rack = Rack::factory()->create();
        $inactive = Rack::factory()->create(['active' => false]);
        $product = Product::factory()->create();

        $errors = $this->postJson('/api/movements', [])->assertUnprocessable()->json('errors');

        $this->assertSame('Barang wajib diisi.', $errors['product_id'][0]);
        $this->assertSame('Jumlah wajib diisi.', $errors['quantity'][0]);
        $this->assertArrayHasKey('rack_from_id', $errors);
        $this->assertArrayHasKey('rack_to_id', $errors);

        $this->postJson('/api/movements', [
            'product_id' => $product->id,
            'quantity' => 1,
            'rack_from_id' => $rack->id,
            'rack_to_id' => $rack->id,
        ])->assertJsonValidationErrors(['rack_from_id']);
        $this->postJson('/api/movements', [
            'product_id' => $product->id,
            'quantity' => 0,
            'rack_to_id' => $rack->id,
        ])->assertJsonValidationErrors(['quantity']);
        $this->postJson('/api/movements', [
            'product_id' => $product->id,
            'quantity' => 1.2345,
            'rack_to_id' => $rack->id,
        ])->assertJsonValidationErrors(['quantity']);
        $this->postJson('/api/movements', [
            'product_id' => $product->id,
            'quantity' => 1,
            'rack_to_id' => $inactive->id,
        ])->assertJsonValidationErrors(['rack_to_id']);

        Inventory::factory()->create([
            'product_id' => $product->id,
            'quantity' => 5,
            'rack_id' => $inactive->id,
        ]);

        $this->postJson('/api/movements', [
            'product_id' => $product->id,
            'quantity' => 2,
            'rack_from_id' => $inactive->id,
            'rack_to_id' => $rack->id,
        ])->assertCreated();
    }

    public function test_store_rejects_issue_from_rack_without_inventory_row(): void
    {
        $this->signIn();
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();

        $errors = $this->postJson('/api/movements', [
            'product_id' => $product->id,
            'quantity' => 1,
            'rack_from_id' => $rack->id,
        ])
            ->assertUnprocessable()
            ->json('errors');

        $this->assertSame('Stok tidak mencukupi.', $errors['quantity'][0]);
        $this->assertSame(0, Inventory::count());
        $this->assertSame(0, Movement::count());
    }

    public function test_store_transfer_moves_stock_between_racks(): void
    {
        $this->signIn();
        $product = Product::factory()->create();
        $from = Rack::factory()->create();
        $to = Rack::factory()->create();
        $this->stock($product, $from, 10);

        $this->postJson('/api/movements', [
            'product_id' => $product->id,
            'quantity' => 3,
            'rack_from_id' => $from->id,
            'rack_to_id' => $to->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'transfer');

        $this->assertSame('7.000', Inventory::where('rack_id', $from->id)->value('quantity'));
        $this->assertSame('3.000', Inventory::where('rack_id', $to->id)->value('quantity'));
    }
}
