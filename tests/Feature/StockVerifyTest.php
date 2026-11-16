<?php

namespace Tests\Feature;

use App\Models\AdjustmentItem;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Rack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockVerifyTest extends TestCase
{
    use RefreshDatabase;

    private function runFlow(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create();
        $a = Rack::factory()->create();
        $b = Rack::factory()->create();

        $this->postJson('/api/movements', [
            'product_id' => $product->id,
            'quantity' => 50,
            'rack_to_id' => $a->id,
        ])->assertCreated();
        $this->postJson('/api/adjustments', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => -2.5,
                    'rack_id' => $a->id,
                    'reason' => AdjustmentItem::REASON_DAMAGED,
                ],
            ],
        ])->assertCreated();

        $done = $this->postJson('/api/handovers', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'rack_from_id' => $a->id,
                    'rack_to_id' => $b->id,
                ],
            ],
        ])->json('data.id');
        $this->postJson("/api/handovers/{$done}/complete")->assertOk();

        $this->postJson('/api/handovers', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                    'rack_from_id' => $a->id,
                    'rack_to_id' => $b->id,
                ],
            ],
        ])->assertCreated();
    }

    public function test_it_fails_and_lists_tampered_balances(): void
    {
        $this->runFlow();

        Inventory::query()->where('rack_id', Inventory::query()->value('rack_id'))->update(['quantity' => 999]);

        $this->artisan('stock:verify')->assertFailed();
    }

    public function test_it_fails_when_incoming_does_not_match_open_handovers(): void
    {
        $this->runFlow();

        Inventory::query()->where('quantity_incoming', '>', 0)->update(['quantity_incoming' => 1]);

        $this->artisan('stock:verify')->assertFailed();
    }

    public function test_it_passes_after_a_consistent_flow(): void
    {
        $this->runFlow();

        $this->artisan('stock:verify')->expectsOutput('Semua saldo stok sesuai.')->assertSuccessful();
    }

    public function test_seeded_stock_is_consistent(): void
    {
        $this->seed();

        $this->assertGreaterThan(0, Inventory::count());
        $this->artisan('stock:verify')->assertSuccessful();
    }
}
