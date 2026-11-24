<?php

namespace Tests\Feature;

use App\Models\Adjustment;
use App\Models\AdjustmentItem;
use App\Models\Inventory;
use App\Models\Movement;
use App\Models\Product;
use App\Models\Rack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdjustmentTest extends TestCase
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

    public function test_adjustment_cannot_be_updated_or_deleted(): void
    {
        $this->signIn();
        $adjustment = Adjustment::factory()->create();

        $this->putJson("/api/adjustments/{$adjustment->id}", [])->assertMethodNotAllowed();
        $this->deleteJson("/api/adjustments/{$adjustment->id}")->assertMethodNotAllowed();
    }

    public function test_cancel_is_rejected_when_stock_was_used_afterwards(): void
    {
        $this->signIn();
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();

        $id = $this->postJson('/api/adjustments', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                    'rack_id' => $rack->id,
                    'reason' => AdjustmentItem::REASON_FOUND,
                ],
            ],
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/movements', [
            'product_id' => $product->id,
            'quantity' => 3,
            'rack_from_id' => $rack->id,
        ])->assertCreated();

        $this->postJson("/api/adjustments/{$id}/cancel")->assertUnprocessable();

        $this->assertSame(Adjustment::STATUS_ACTIVE, Adjustment::find($id)->status);
        $this->assertSame('2.000', Inventory::where('rack_id', $rack->id)->value('quantity'));
        $this->assertSame(1, Adjustment::find($id)->movements()->count());
    }

    public function test_cancel_reverses_stock_and_cannot_be_repeated(): void
    {
        $this->signIn();
        $product = Product::factory()->create();
        $rackA = Rack::factory()->create();
        $rackB = Rack::factory()->create();
        $this->stock($product, $rackA, 10);

        $id = $this->postJson('/api/adjustments', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => -3,
                    'rack_id' => $rackA->id,
                    'reason' => AdjustmentItem::REASON_DAMAGED,
                ],
                [
                    'product_id' => $product->id,
                    'quantity' => '2.5',
                    'rack_id' => $rackB->id,
                    'reason' => AdjustmentItem::REASON_FOUND,
                ],
            ],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/adjustments/{$id}/cancel")
            ->assertOk()
            ->assertJsonPath('message', 'Penyesuaian berhasil dibatalkan.')
            ->assertJsonPath('data.status', Adjustment::STATUS_CANCELLED);

        $this->assertSame('10.000', Inventory::where('rack_id', $rackA->id)->value('quantity'));
        $this->assertSame('0.000', Inventory::where('rack_id', $rackB->id)->value('quantity'));
        $this->assertSame(4, Adjustment::find($id)->movements()->count());
        $this->postJson("/api/adjustments/{$id}/cancel")
            ->assertConflict()
            ->assertJsonPath('message', 'Penyesuaian sudah dibatalkan.');
    }

    public function test_guest_cannot_access_adjustments(): void
    {
        $this->getJson('/api/adjustments')->assertUnauthorized();
        $this->postJson('/api/adjustments', [])->assertUnauthorized();
        $this->postJson('/api/adjustments/1/cancel')->assertUnauthorized();
    }

    public function test_index_filters_searches_and_sorts_newest_first(): void
    {
        $this->signIn();
        $old = Adjustment::factory()->create([
            'code' => 'PN-000001',
            'created_at' => now()->subDay(),
            'note' => 'Barang rusak di rak',
        ]);
        $new = Adjustment::factory()->create([
            'code' => 'PN-000002',
            'note' => 'Hitung ulang',
        ]);

        $this->getJson('/api/adjustments')->assertOk()->assertJsonPath('data.0.id', $new->id);
        $this->getJson('/api/adjustments?search=rusak')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $old->id);
        $this->getJson('/api/adjustments?filters[0][field]=code&filters[0][operator]=eq&filters[0][value]=PN-000002')->assertJsonCount(1, 'data');
        $this->getJson('/api/adjustments?per_page=1000&sort_by=note')->assertUnprocessable()->assertJsonValidationErrors(['per_page', 'sort_by']);
    }

    public function test_show_returns_adjustment_with_items(): void
    {
        $this->signIn();
        $item = AdjustmentItem::factory()->create();

        $this->getJson("/api/adjustments/{$item->adjustment_id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $item->id)
            ->assertJsonStructure(['data' => ['id', 'code', 'items' => [[
                'product' => ['id'],
                'rack' => ['id', 'warehouse'],
            ]]]]);
    }

    public function test_store_applies_decreases_and_increases_with_movements(): void
    {
        $user = $this->signIn();
        $product = Product::factory()->create();
        $rackA = Rack::factory()->create();
        $rackB = Rack::factory()->create();
        $this->stock($product, $rackA, 10);

        $response = $this->postJson('/api/adjustments', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => -3,
                    'rack_id' => $rackA->id,
                    'reason' => AdjustmentItem::REASON_DAMAGED,
                ],
                [
                    'product_id' => $product->id,
                    'quantity' => '2.5',
                    'rack_id' => $rackB->id,
                    'reason' => AdjustmentItem::REASON_FOUND,
                ],
            ],
            'note' => 'Hasil cek rak',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Penyesuaian berhasil ditambahkan.')
            ->assertJsonPath('data.created_by_id', $user->id)
            ->assertJsonCount(2, 'data.items');

        $adjustment = Adjustment::find($response->json('data.id'));

        $this->assertSame(sprintf('PN-%06d', $adjustment->id), $adjustment->code);
        $this->assertSame('7.000', Inventory::where('rack_id', $rackA->id)->value('quantity'));
        $this->assertSame('2.500', Inventory::where('rack_id', $rackB->id)->value('quantity'));
        $this->assertSame(2, $adjustment->movements()->count());

        $issue = $adjustment->movements()->where('rack_from_id', $rackA->id)->first();

        $this->assertSame('3.000', $issue->quantity);
        $this->assertNull($issue->rack_to_id);
        $this->assertSame(Movement::TYPE_ADJUST, $issue->type);
    }

    public function test_store_rejects_decrease_on_rack_without_inventory_row(): void
    {
        $this->signIn();

        $errors = $this->postJson('/api/adjustments', [
            'items' => [
                [
                    'product_id' => Product::factory()->create()->id,
                    'quantity' => -1,
                    'rack_id' => Rack::factory()->create()->id,
                    'reason' => AdjustmentItem::REASON_LOST,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->json('errors');

        $this->assertSame('Stok tidak mencukupi.', $errors['items.0.quantity'][0]);
        $this->assertSame(0, Adjustment::count());
        $this->assertSame(0, Inventory::count());
    }

    public function test_store_rejects_invalid_input(): void
    {
        $this->signIn();
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();
        $this->postJson('/api/adjustments', [])->assertJsonValidationErrors(['items']);
        $this->postJson('/api/adjustments', ['items' => []])->assertJsonValidationErrors(['items']);

        $item = [
            'product_id' => $product->id,
            'quantity' => -1,
            'rack_id' => $rack->id,
            'reason' => AdjustmentItem::REASON_LOST,
        ];

        $this->postJson('/api/adjustments', ['items' => [[...$item, 'reason' => 'x']]])->assertJsonValidationErrors(['items.0.reason']);
        $this->postJson('/api/adjustments', ['items' => [[...$item, 'quantity' => 1.2345]]])->assertJsonValidationErrors(['items.0.quantity']);
        $this->postJson('/api/adjustments', ['items' => [[...$item, 'quantity' => 0]]])->assertJsonValidationErrors(['items.0.quantity']);
        $this->postJson('/api/adjustments', ['items' => [[...$item, 'quantity' => 2]]])->assertJsonValidationErrors(['items.0.quantity']);
        $this->postJson('/api/adjustments', ['items' => [[
            ...$item,
            'quantity' => -2,
            'reason' => AdjustmentItem::REASON_FOUND,
        ]]])->assertJsonValidationErrors(['items.0.quantity']);
        $this->postJson('/api/adjustments', ['items' => [$item, [...$item, 'quantity' => -2]]])->assertJsonValidationErrors(['items.1.rack_id']);
    }

    public function test_store_rolls_back_when_stock_is_insufficient(): void
    {
        $this->signIn();
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();
        $this->stock($product, $rack, 2);

        $errors = $this->postJson('/api/adjustments', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => -5,
                    'rack_id' => $rack->id,
                    'reason' => AdjustmentItem::REASON_LOST,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->json('errors');

        $this->assertSame('Stok tidak mencukupi.', $errors['items.0.quantity'][0]);
        $this->assertSame(0, Adjustment::count());
        $this->assertSame(0, Movement::count());
        $this->assertSame('2.000', Inventory::where('rack_id', $rack->id)->value('quantity'));
    }
}
