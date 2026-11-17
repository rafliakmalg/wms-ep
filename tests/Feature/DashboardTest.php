<?php

namespace Tests\Feature;

use App\Models\Adjustment;
use App\Models\Category;
use App\Models\Handover;
use App\Models\Inventory;
use App\Models\Movement;
use App\Models\Product;
use App\Models\Rack;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(?string $roleName = null): User
    {
        $user = User::factory()->create();

        if ($roleName !== null) {
            $user->update(['role_id' => Role::factory()->create(['name' => $roleName])->id]);
        }

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_low_stock_lists_only_active_products_below_minimum(): void
    {
        $this->signIn();
        $low = Product::factory()->create(['min_stock' => 10]);
        $enough = Product::factory()->create(['min_stock' => 5]);
        $inactive = Product::factory()->create([
            'active' => false,
            'min_stock' => 10,
        ]);
        $empty = Product::factory()->create(['min_stock' => 3]);
        Inventory::factory()->create([
            'product_id' => $low->id,
            'quantity' => 4,
        ]);
        Inventory::factory()->create([
            'product_id' => $enough->id,
            'quantity' => 5,
        ]);
        Inventory::factory()->create([
            'product_id' => $inactive->id,
            'quantity' => 1,
        ]);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonCount(2, 'data.low_stock')
            ->assertJsonPath('data.low_stock.0.id', $low->id)
            ->assertJsonPath('data.low_stock.0.total_quantity', '4.000')
            ->assertJsonPath('data.low_stock.1.id', $empty->id)
            ->assertJsonPath('data.low_stock.1.total_quantity', '0.000');
    }

    public function test_low_stock_total_counts_beyond_the_list_limit(): void
    {
        $this->signIn();
        Product::factory()->count(10)->create(['min_stock' => 5]);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonCount(8, 'data.low_stock')
            ->assertJsonPath('data.low_stock_total', 10);
    }

    public function test_movements_per_day_counts_each_local_day(): void
    {
        $this->signIn();
        $today = now('Asia/Jakarta')->startOfDay();
        Movement::factory()->count(2)->create(['created_at' => $today->clone()->addHours(2)->utc()]);
        Movement::factory()->create(['created_at' => $today->clone()->subDay()->addHours(20)->utc()]);
        Movement::factory()->create(['created_at' => $today->clone()->subDays(8)->addHours(5)->utc()]);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonCount(7, 'data.movements_per_day')
            ->assertJsonPath('data.movements_per_day.0.total', 0)
            ->assertJsonPath('data.movements_per_day.5.date', $today->clone()->subDay()->toDateString())
            ->assertJsonPath('data.movements_per_day.5.total', 1)
            ->assertJsonPath('data.movements_per_day.6.date', $today->toDateString())
            ->assertJsonPath('data.movements_per_day.6.total', 2);
    }

    public function test_show_returns_totals_and_recent_documents(): void
    {
        $this->signIn();
        $product = Product::factory()->create();
        Product::factory()->create(['active' => false]);
        Inventory::factory()->create([
            'product_id' => $product->id,
            'quantity' => 7,
        ]);
        Inventory::factory()->create(['quantity' => 3.5]);
        Adjustment::factory()->create();
        Adjustment::factory()->create(['created_at' => now()->subDays(40)]);
        Adjustment::factory()->create(['status' => Adjustment::STATUS_CANCELLED]);
        Handover::factory()->create(['status' => Handover::STATUS_TAKEN]);
        Handover::factory()->create(['status' => Handover::STATUS_COMPLETED]);
        $newest = Movement::factory()->create();

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_products', Product::query()->where('active', true)->count())
            ->assertJsonPath('data.total_quantity', '10.500')
            ->assertJsonPath('data.recent_adjustments', 1)
            ->assertJsonPath('data.taken_handovers_total', 1)
            ->assertJsonCount(1, 'data.taken_handovers')
            ->assertJsonPath('data.recent_movements.0.id', $newest->id);
    }

    public function test_stock_by_category_sums_quantity_through_products(): void
    {
        $this->signIn();
        $big = Category::factory()->create();
        $small = Category::factory()->create();
        $bigProduct = Product::factory()->create(['category_id' => $big->id]);
        $smallProduct = Product::factory()->create(['category_id' => $small->id]);
        Inventory::factory()->create([
            'product_id' => $bigProduct->id,
            'quantity' => 8,
        ]);
        Inventory::factory()->create([
            'product_id' => $bigProduct->id,
            'quantity' => 4,
        ]);
        Inventory::factory()->create([
            'product_id' => $smallProduct->id,
            'quantity' => 1,
        ]);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stock_by_category.0.id', $big->id)
            ->assertJsonPath('data.stock_by_category.0.total_quantity', '12.000')
            ->assertJsonPath('data.stock_by_category.1.id', $small->id)
            ->assertJsonPath('data.stock_by_category.1.total_quantity', '1.000');
    }

    public function test_stock_by_category_groups_the_smallest_into_others(): void
    {
        $this->signIn();

        foreach (range(1, 7) as $quantity) {
            Inventory::factory()->create([
                'product_id' => Product::factory()->create(['category_id' => Category::factory()->create()->id])->id,
                'quantity' => $quantity,
            ]);
        }

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonCount(6, 'data.stock_by_category')
            ->assertJsonPath('data.stock_by_category.0.total_quantity', '7.000')
            ->assertJsonPath('data.stock_by_category.4.total_quantity', '3.000')
            ->assertJsonPath('data.stock_by_category.5.id', null)
            ->assertJsonPath('data.stock_by_category.5.name', 'Lainnya')
            ->assertJsonPath('data.stock_by_category.5.total_quantity', '3.000');
    }

    public function test_stock_by_warehouse_sums_quantity_through_racks(): void
    {
        $this->signIn();
        $big = Warehouse::factory()->create();
        $small = Warehouse::factory()->create();
        $empty = Warehouse::factory()->create();
        $bigRack = Rack::factory()->create(['warehouse_id' => $big->id]);
        $smallRack = Rack::factory()->create(['warehouse_id' => $small->id]);
        Inventory::factory()->create([
            'quantity' => 5,
            'rack_id' => $bigRack->id,
        ]);
        Inventory::factory()->create([
            'quantity' => 6,
            'rack_id' => $bigRack->id,
        ]);
        Inventory::factory()->create([
            'quantity' => 2,
            'rack_id' => $smallRack->id,
        ]);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonCount(3, 'data.stock_by_warehouse')
            ->assertJsonPath('data.stock_by_warehouse.0.id', $big->id)
            ->assertJsonPath('data.stock_by_warehouse.0.total_quantity', '11.000')
            ->assertJsonPath('data.stock_by_warehouse.1.id', $small->id)
            ->assertJsonPath('data.stock_by_warehouse.2.id', $empty->id)
            ->assertJsonPath('data.stock_by_warehouse.2.total_quantity', '0.000');
    }

    public function test_taken_handovers_for_operator_only_include_own_custody(): void
    {
        $operator = $this->signIn('operator');
        $mine = Handover::factory()->create([
            'custody_id' => $operator->id,
            'status' => Handover::STATUS_TAKEN,
        ]);
        Handover::factory()->create(['status' => Handover::STATUS_TAKEN]);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.taken_handovers_total', 1)
            ->assertJsonCount(1, 'data.taken_handovers')
            ->assertJsonPath('data.taken_handovers.0.id', $mine->id);
    }
}
