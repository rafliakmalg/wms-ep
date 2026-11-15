<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FilterTest extends TestCase
{
    use RefreshDatabase;

    private Product $usb;

    private Product $hdmi;

    private Product $mouse;

    /**
     * @param  array<int, array{0: string, 1: string, 2: string}>  $conditions
     */
    private function assertRejected(array $conditions): void
    {
        $this->getJson('/api/products?'.$this->queryString($conditions))->assertUnprocessable()->assertJsonValidationErrorFor('filters.0');
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string}>  $conditions
     */
    private function queryString(array $conditions, bool $sorted = true): string
    {
        $filters = array_map(fn (array $condition) => [
            'field' => $condition[0],
            'operator' => $condition[1],
            'value' => $condition[2],
        ], $conditions);

        return http_build_query($sorted ? [
            'filters' => $filters,
            'sort_by' => 'sku',
        ] : ['filters' => $filters]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
        $cables = Category::factory()->create();

        $this->usb = Product::factory()->create([
            'category_id' => $cables->id,
            'min_stock' => 5,
            'name' => 'Kabel USB',
            'sku' => 'AAA-1',
            'created_at' => '2026-01-05 08:00:00',
        ]);
        $this->hdmi = Product::factory()->create([
            'active' => false,
            'category_id' => $cables->id,
            'min_stock' => 15,
            'name' => 'Kabel HDMI',
            'sku' => 'BBB-2',
            'created_at' => '2026-01-10 10:00:00',
        ]);
        $this->mouse = Product::factory()->create([
            'min_stock' => 25,
            'name' => 'Mouse 100%',
            'sku' => 'CCC-3',
            'created_at' => '2026-01-20 23:30:00',
        ]);
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string}>  $conditions
     * @return array<int, string>
     */
    private function skus(array $conditions): array
    {
        $response = $this->getJson('/api/products?'.$this->queryString($conditions))->assertOk();

        return array_column($response->json('data'), 'sku');
    }

    public function test_between_on_numbers(): void
    {
        $this->assertSame(['AAA-1', 'BBB-2'], $this->skus([['min_stock', 'between', '5,15']]));
    }

    public function test_boolean_equality(): void
    {
        $this->assertSame(['BBB-2'], $this->skus([['active', 'eq', '0']]));
        $this->assertSame(['AAA-1', 'CCC-3'], $this->skus([['active', 'eq', '1']]));
    }

    public function test_comparison_operators_on_decimals(): void
    {
        $this->assertSame(['CCC-3'], $this->skus([['min_stock', 'gt', '15']]));
        $this->assertSame(['BBB-2', 'CCC-3'], $this->skus([['min_stock', 'gte', '15']]));
        $this->assertSame(['AAA-1'], $this->skus([['min_stock', 'lt', '15']]));
        $this->assertSame(['AAA-1', 'BBB-2'], $this->skus([['min_stock', 'lte', '15']]));
        $this->assertSame(['BBB-2'], $this->skus([['min_stock', 'eq', '15']]));
    }

    public function test_date_filters_compare_by_day(): void
    {
        $this->assertSame(['BBB-2'], $this->skus([['created_at', 'eq', '2026-01-10']]));
        $this->assertSame(['BBB-2', 'CCC-3'], $this->skus([['created_at', 'gte', '2026-01-10']]));
        $this->assertSame(['AAA-1'], $this->skus([['created_at', 'lt', '2026-01-10']]));
        $this->assertSame(['BBB-2', 'CCC-3'], $this->skus([['created_at', 'between', '2026-01-10,2026-01-20']]));
    }

    public function test_eq_and_ne_on_text(): void
    {
        $this->assertSame(['BBB-2'], $this->skus([['sku', 'eq', 'BBB-2']]));
        $this->assertSame(['AAA-1', 'CCC-3'], $this->skus([['sku', 'ne', 'BBB-2']]));
    }

    public function test_filters_combine_with_and(): void
    {
        $this->assertSame(['AAA-1'], $this->skus([['name', 'like', 'Kabel'], ['active', 'eq', '1']]));
        $this->assertSame(['BBB-2'], $this->skus([['min_stock', 'gte', '10'], ['min_stock', 'lte', '20']]));
        $this->assertSame([], $this->skus([['name', 'like', 'Mouse'], ['active', 'eq', '0']]));
    }

    public function test_filters_work_together_with_search_and_sort(): void
    {
        $query = $this->queryString([['active', 'eq', '0']]);

        $this->getJson("/api/products?search=Kabel&sort_dir=desc&{$query}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'BBB-2');
    }

    public function test_in_on_integers_and_text(): void
    {
        $this->assertSame(['AAA-1', 'BBB-2'], $this->skus([['category_id', 'in', (string) $this->usb->category_id]]));
        $this->assertSame(['AAA-1', 'CCC-3'], $this->skus([['sku', 'in', 'AAA-1,CCC-3']]));
    }

    public function test_injection_attempts_are_harmless(): void
    {
        $this->assertRejected([['name; DROP TABLE products', 'eq', 'x']]);
        $this->assertSame([], $this->skus([['sku', 'in', '1);DROP TABLE products;--']]));
        $this->assertSame([], $this->skus([['name', 'eq', "' OR '1'='1"]]));

        $this->assertSame(3, Product::count());
    }

    public function test_like_matches_contained_text_and_escapes_wildcards(): void
    {
        $this->assertSame(['AAA-1', 'BBB-2'], $this->skus([['name', 'like', 'Kabel']]));
        $this->assertSame(['CCC-3'], $this->skus([['name', 'like', '100%']]));
        $this->assertSame(['CCC-3'], $this->skus([['name', 'like', '%']]));
    }

    public function test_null_operator(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        User::factory()->create([
            'email' => null,
            'username' => 'tanpa_email',
        ]);

        $withoutEmail = $this->getJson('/api/users?'.$this->queryString([['email', 'null', '1']], false))->assertOk()->json('data');
        $withEmail = $this->getJson('/api/users?'.$this->queryString([['email', 'null', '0']], false))->assertOk()->json('data');

        $this->assertSame(['tanpa_email'], array_column($withoutEmail, 'username'));
        $this->assertNotContains('tanpa_email', array_column($withEmail, 'username'));
        $this->assertCount(2, $withEmail);
    }

    public function test_rejects_invalid_values(): void
    {
        $this->assertRejected([['category_id', 'eq', 'abc']]);
        $this->assertRejected([['min_stock', 'gt', 'abc']]);
        $this->assertRejected([['active', 'eq', 'maybe']]);
        $this->assertRejected([['created_at', 'eq', '15-11-2026']]);
        $this->assertRejected([['category_id', 'in', '1,abc']]);
        $this->assertRejected([['min_stock', 'between', '1']]);
        $this->assertRejected([['min_stock', 'between', '1,2,3']]);
    }

    public function test_rejects_malformed_conditions(): void
    {
        $this->getJson('/api/products?filters[0][field]=name&filters[0][operator]=eq')->assertJsonValidationErrorFor('filters.0');
        $this->getJson('/api/products?filters[0][field]=name&filters[0][operator]=eq&filters[0][value]=x&filters[0][extra]=y')->assertJsonValidationErrorFor('filters.0');
        $this->getJson('/api/products?filters[0][field]=name&filters[0][operator]=eq&filters[0][value][]=x')->assertJsonValidationErrorFor('filters.0');
        $this->getJson('/api/products?filters=name')->assertJsonValidationErrorFor('filters');
    }

    public function test_rejects_unknown_fields_and_operators(): void
    {
        $this->assertRejected([['description', 'eq', 'x']]);
        $this->assertRejected([['name', 'gte', 'x']]);
        $this->assertRejected([['active', 'like', '1']]);
        $this->assertRejected([['sku', 'drop', '1']]);
    }
}
