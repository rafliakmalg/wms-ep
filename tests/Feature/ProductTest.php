<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'base_unit_id' => Unit::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'name' => 'Kabel USB-C',
            'sku' => 'SKU-0001',
            'units' => [],
            ...$overrides,
        ];
    }

    private function signIn(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_destroy_deletes_product_and_its_units(): void
    {
        $this->signIn();
        $productUnit = ProductUnit::factory()->create();

        $this->deleteJson("/api/products/{$productUnit->product_id}")->assertOk()->assertJsonPath('message', 'Barang berhasil dihapus.');

        $this->assertDatabaseMissing('products', ['id' => $productUnit->product_id]);
        $this->assertDatabaseMissing('product_units', ['id' => $productUnit->id]);
    }

    public function test_guest_cannot_access_products(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
    }

    public function test_index_filters_by_search_category_and_active(): void
    {
        $this->signIn();
        $category = Category::factory()->create();
        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Kabel USB',
            'sku' => 'KBL-1',
        ]);
        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Mouse',
            'sku' => 'MSE-1',
            'active' => false,
        ]);
        Product::factory()->create([
            'name' => 'Kabel HDMI',
            'sku' => 'KBL-2',
        ]);

        $this->getJson('/api/products?search=kabel')->assertJsonCount(2, 'data');
        $this->getJson('/api/products?search=MSE')->assertJsonCount(1, 'data');
        $this->getJson("/api/products?filters[0][field]=category_id&filters[0][operator]=eq&filters[0][value]={$category->id}")->assertJsonCount(2, 'data');
        $this->getJson("/api/products?filters[0][field]=category_id&filters[0][operator]=eq&filters[0][value]={$category->id}&filters[1][field]=active&filters[1][operator]=eq&filters[1][value]=0")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'MSE-1');
    }

    public function test_index_rejects_invalid_query(): void
    {
        $this->signIn();

        $this->getJson('/api/products?per_page=1000&sort_by=min_stock')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'sort_by']);
    }

    public function test_index_returns_products_with_relations(): void
    {
        $this->signIn();
        ProductUnit::factory()->create();
        Product::factory()->count(2)->create();

        $this->getJson('/api/products?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonStructure(['data' => [[
                'id',
                'base_unit_id',
                'category_id',
                'min_stock',
                'name',
                'sku',
                'active',
                'base_unit' => ['id', 'code'],
                'category' => ['id', 'name'],
                'units',
            ]], 'links', 'meta']);
    }

    public function test_show_returns_product_with_relations(): void
    {
        $this->signIn();
        $productUnit = ProductUnit::factory()->create();

        $this->getJson("/api/products/{$productUnit->product_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $productUnit->product_id)
            ->assertJsonCount(1, 'data.units')
            ->assertJsonPath('data.units.0.unit.id', $productUnit->unit_id);
    }

    public function test_store_creates_product_with_defaults_audit_and_string_decimals(): void
    {
        $user = $this->signIn();
        $payload = $this->payload();

        $this->postJson('/api/products', $payload)
            ->assertCreated()
            ->assertJsonPath('message', 'Barang berhasil ditambahkan.')
            ->assertJsonPath('data.sku', 'SKU-0001')
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.min_stock', '0.000')
            ->assertJsonPath('data.created_by_id', $user->id)
            ->assertJsonPath('data.base_unit.id', $payload['base_unit_id'])
            ->assertJsonPath('data.category.id', $payload['category_id'])
            ->assertJsonCount(0, 'data.units');
    }

    public function test_store_rejects_base_unit_and_duplicate_units_in_unit_list(): void
    {
        $this->signIn();
        $base = Unit::factory()->create();
        $box = Unit::factory()->create();

        $this->postJson('/api/products', $this->payload([
            'base_unit_id' => $base->id,
            'units' => [[
                'unit_id' => $base->id,
                'factor' => 1,
            ]],
        ]))
            ->assertJsonValidationErrors(['units.0.unit_id']);
        $this->postJson('/api/products', $this->payload(['units' => [[
            'unit_id' => $box->id,
            'factor' => 2,
        ], [
            'unit_id' => $box->id,
            'factor' => 3,
        ]]]))
            ->assertJsonValidationErrors(['units.0.unit_id', 'units.1.unit_id']);
    }

    public function test_store_rolls_back_the_product_when_creating_units_fails(): void
    {
        $this->signIn();
        $unit = Unit::factory()->create();
        ProductUnit::creating(fn () => throw new RuntimeException('gagal'));

        $this->postJson('/api/products', $this->payload(['units' => [[
            'unit_id' => $unit->id,
            'factor' => 2,
        ]]]))
            ->assertServerError();

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_units', 0);
    }

    public function test_store_saves_product_units_with_string_factor_and_audit(): void
    {
        $user = $this->signIn();
        $box = Unit::factory()->create();
        $carton = Unit::factory()->create();

        $this->postJson('/api/products', $this->payload([
            'min_stock' => 12.5,
            'units' => [[
                'unit_id' => $box->id,
                'factor' => 10,
            ], [
                'unit_id' => $carton->id,
                'factor' => 100.5,
            ]],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.min_stock', '12.500')
            ->assertJsonCount(2, 'data.units')
            ->assertJsonPath('data.units.0.factor', '10.000000')
            ->assertJsonPath('data.units.1.factor', '100.500000')
            ->assertJsonPath('data.units.0.unit.id', $box->id);

        $this->assertDatabaseHas('product_units', [
            'unit_id' => $box->id,
            'created_by_id' => $user->id,
            'updated_by_id' => $user->id,
        ]);
    }

    public function test_store_validates_numbers(): void
    {
        $this->signIn();
        $unit = Unit::factory()->create();

        $this->postJson('/api/products', $this->payload(['min_stock' => -1]))->assertJsonValidationErrors(['min_stock']);
        $this->postJson('/api/products', $this->payload(['min_stock' => 1.2345]))->assertJsonValidationErrors(['min_stock']);
        $this->postJson('/api/products', $this->payload(['units' => [[
            'unit_id' => $unit->id,
            'factor' => 0,
        ]]]))
            ->assertJsonValidationErrors(['units.0.factor']);
        $this->postJson('/api/products', $this->payload(['units' => [[
            'unit_id' => $unit->id,
            'factor' => 'abc',
        ]]]))
            ->assertJsonValidationErrors(['units.0.factor']);
    }

    public function test_store_validates_required_fields_and_foreign_keys(): void
    {
        $this->signIn();
        Product::factory()->create(['sku' => 'SKU-0001']);

        $this->postJson('/api/products', [])->assertJsonValidationErrors(['base_unit_id', 'category_id', 'name', 'sku', 'units']);
        $this->postJson('/api/products', $this->payload([
            'base_unit_id' => 9999,
            'category_id' => 9999,
            'sku' => 'SKU-0001',
        ]))
            ->assertJsonValidationErrors(['base_unit_id', 'category_id', 'sku']);
    }

    public function test_store_with_invalid_unit_creates_no_product(): void
    {
        $this->signIn();

        $this->postJson('/api/products', $this->payload(['units' => [[
            'unit_id' => 9999,
            'factor' => 2,
        ]]]))->assertUnprocessable();

        $this->assertDatabaseCount('products', 0);
    }

    public function test_update_changes_fields_and_allows_own_sku(): void
    {
        $user = $this->signIn();
        $product = Product::factory()->create(['sku' => 'SKU-0001']);

        $this->putJson("/api/products/{$product->id}", [
            'base_unit_id' => $product->base_unit_id,
            'category_id' => $product->category_id,
            'min_stock' => 5,
            'name' => 'Nama Baru',
            'sku' => 'SKU-0001',
            'units' => [],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Barang berhasil diperbarui.')
            ->assertJsonPath('data.name', 'Nama Baru')
            ->assertJsonPath('data.min_stock', '5.000')
            ->assertJsonPath('data.updated_by_id', $user->id);
    }

    public function test_update_rejects_sku_of_another_product(): void
    {
        $this->signIn();
        Product::factory()->create(['sku' => 'SKU-0002']);
        $product = Product::factory()->create(['sku' => 'SKU-0001']);

        $this->putJson("/api/products/{$product->id}", $this->payload(['sku' => 'SKU-0002']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku']);
    }

    public function test_update_replaces_the_whole_unit_list(): void
    {
        $editor = $this->signIn();
        $product = Product::factory()->create();
        $kept = ProductUnit::factory()->create([
            'product_id' => $product->id,
            'factor' => 10,
        ]);
        $removed = ProductUnit::factory()->create(['product_id' => $product->id]);
        $added = Unit::factory()->create();

        $this->putJson("/api/products/{$product->id}", $this->payload([
            'base_unit_id' => $product->base_unit_id,
            'category_id' => $product->category_id,
            'sku' => $product->sku,
            'units' => [[
                'unit_id' => $kept->unit_id,
                'factor' => 12,
            ], [
                'unit_id' => $added->id,
                'factor' => 5,
            ]],
        ]))
            ->assertOk()
            ->assertJsonCount(2, 'data.units');

        $this->assertDatabaseCount('product_units', 2);
        $this->assertDatabaseMissing('product_units', ['unit_id' => $removed->unit_id]);
        $this->assertDatabaseHas('product_units', [
            'product_id' => $product->id,
            'unit_id' => $kept->unit_id,
            'factor' => 12,
            'created_by_id' => $editor->id,
        ]);
        $this->assertDatabaseHas('product_units', [
            'product_id' => $product->id,
            'unit_id' => $added->id,
            'factor' => 5,
            'updated_by_id' => $editor->id,
        ]);
    }

    public function test_update_requires_units_and_an_empty_list_clears_them(): void
    {
        $this->signIn();
        $product = Product::factory()->create();
        ProductUnit::factory()->create(['product_id' => $product->id]);
        $payload = $this->payload([
            'base_unit_id' => $product->base_unit_id,
            'category_id' => $product->category_id,
            'sku' => $product->sku,
        ]);

        $this->putJson("/api/products/{$product->id}", Arr::except($payload, 'units'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['units']);
        $this->assertDatabaseCount('product_units', 1);

        $this->putJson("/api/products/{$product->id}", $payload)->assertOk()->assertJsonCount(0, 'data.units');
        $this->assertDatabaseCount('product_units', 0);
    }

    public function test_update_rolls_back_everything_when_creating_units_fails(): void
    {
        $this->signIn();
        $product = Product::factory()->create(['name' => 'Nama Lama']);
        $oldUnit = ProductUnit::factory()->create(['product_id' => $product->id]);
        $newUnit = Unit::factory()->create();
        ProductUnit::creating(fn () => throw new RuntimeException('gagal'));

        $this->putJson("/api/products/{$product->id}", $this->payload([
            'base_unit_id' => $product->base_unit_id,
            'category_id' => $product->category_id,
            'name' => 'Nama Baru',
            'sku' => $product->sku,
            'units' => [[
                'unit_id' => $newUnit->id,
                'factor' => 2,
            ]],
        ]))->assertServerError();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Nama Lama',
        ]);
        $this->assertDatabaseHas('product_units', ['id' => $oldUnit->id]);
        $this->assertDatabaseCount('product_units', 1);
    }
}
