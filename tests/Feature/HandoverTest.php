<?php

namespace Tests\Feature;

use App\Models\Handover;
use App\Models\HandoverItem;
use App\Models\Inventory;
use App\Models\Movement;
use App\Models\Product;
use App\Models\Rack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HandoverTest extends TestCase
{
    use RefreshDatabase;

    private function balance(Product $product, Rack $rack, string $column = 'quantity'): string
    {
        return (string) Inventory::where('product_id', $product->id)->where('rack_id', $rack->id)->value($column);
    }

    private function signIn(): User
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        return $user;
    }

    /**
     * @return array{0: Product, 1: Rack, 2: Rack, 3: int}
     */
    private function take(float $stock = 10, float $quantity = 4): array
    {
        $product = Product::factory()->create();
        $from = Rack::factory()->create();
        $to = Rack::factory()->create();

        Inventory::factory()->create([
            'product_id' => $product->id,
            'quantity' => $stock,
            'rack_id' => $from->id,
        ]);

        $id = $this->postJson('/api/handovers', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'rack_from_id' => $from->id,
                    'rack_to_id' => $to->id,
                ],
            ],
            'note' => 'Pindah ke rak B',
        ])->assertCreated()->json('data.id');

        return [$product, $from, $to, $id];
    }

    public function test_cancel_after_completion_is_rejected_when_stock_was_used(): void
    {
        $this->signIn();
        [$product, $from, $to, $id] = $this->take();

        $this->postJson("/api/handovers/{$id}/complete")->assertOk();
        $this->postJson('/api/movements', [
            'product_id' => $product->id,
            'quantity' => 3,
            'rack_from_id' => $to->id,
        ])->assertCreated();

        $this->postJson("/api/handovers/{$id}/cancel")->assertUnprocessable();

        $this->assertSame(Handover::STATUS_COMPLETED, Handover::find($id)->status);
        $this->assertSame('6.000', $this->balance($product, $from));
        $this->assertSame('1.000', $this->balance($product, $to));
    }

    public function test_cancel_after_completion_reverses_stock_and_closes_document(): void
    {
        $user = $this->signIn();
        [$product, $from, $to, $id] = $this->take();

        $this->postJson("/api/handovers/{$id}/complete")->assertOk();
        $this->postJson("/api/handovers/{$id}/cancel", ['note' => 'Salah rak'])
            ->assertOk()
            ->assertJsonPath('message', 'Pemindahan berhasil dibatalkan.')
            ->assertJsonPath('data.status', Handover::STATUS_CANCELLED);

        $this->assertSame('10.000', $this->balance($product, $from));
        $this->assertSame('0.000', $this->balance($product, $to));
        $this->assertSame('0.000', $this->balance($product, $to, 'quantity_incoming'));

        $reversal = Movement::where('handover_id', $id)->latest('id')->first()->load('handover');

        $this->assertSame($to->id, $reversal->rack_from_id);
        $this->assertSame($from->id, $reversal->rack_to_id);
        $this->assertSame(Movement::TYPE_RETURN, $reversal->type);

        $log = Handover::find($id)->logs()->latest('id')->first();

        $this->assertSame('cancel', $log->action);
        $this->assertSame($user->id, $log->custody_from_id);
        $this->assertSame('Salah rak', $log->note);
    }

    public function test_cancel_returns_stock_to_source_rack_and_closes_document(): void
    {
        $user = $this->signIn();
        [$product, $from, $to, $id] = $this->take();

        $this->postJson("/api/handovers/{$id}/cancel", ['note' => 'Salah ambil'])
            ->assertOk()
            ->assertJsonPath('message', 'Pemindahan berhasil dibatalkan.')
            ->assertJsonPath('data.status', Handover::STATUS_CANCELLED);

        $this->assertSame('10.000', $this->balance($product, $from));
        $this->assertSame('0.000', $this->balance($product, $to, 'quantity_incoming'));
        $this->assertSame('0.000', $this->balance($product, $to));

        $return = Movement::where('handover_id', $id)->latest('id')->first()->load('handover');

        $this->assertSame($from->id, $return->rack_to_id);
        $this->assertNull($return->rack_from_id);
        $this->assertSame(Movement::TYPE_RETURN, $return->type);

        $log = Handover::find($id)->logs()->latest('id')->first();

        $this->assertSame('cancel', $log->action);
        $this->assertSame($user->id, $log->custody_from_id);
        $this->assertSame('Salah ambil', $log->note);
    }

    public function test_closed_handover_rejects_further_actions(): void
    {
        $this->signIn();
        [, , , $completed] = $this->take();
        [, , , $cancelled] = $this->take();
        $other = User::factory()->create();

        $this->postJson("/api/handovers/{$completed}/complete")->assertOk();
        $this->postJson("/api/handovers/{$cancelled}/cancel")->assertOk();

        foreach ([$completed, $cancelled] as $id) {
            $this->postJson("/api/handovers/{$id}/complete")->assertConflict()->assertJsonPath('message', 'Pemindahan sudah selesai atau dibatalkan.');
            $this->postJson("/api/handovers/{$id}/transfer", ['custody_to_id' => $other->id])->assertConflict();
        }

        $this->postJson("/api/handovers/{$cancelled}/cancel")->assertConflict()->assertJsonPath('message', 'Pemindahan sudah dibatalkan.');
    }

    public function test_complete_moves_incoming_to_quantity_and_closes_document(): void
    {
        $this->signIn();
        [$product, $from, $to, $id] = $this->take();

        $this->postJson("/api/handovers/{$id}/complete")
            ->assertOk()
            ->assertJsonPath('message', 'Pemindahan berhasil diselesaikan.')
            ->assertJsonPath('data.status', Handover::STATUS_COMPLETED);

        $this->assertSame('6.000', $this->balance($product, $from));
        $this->assertSame('4.000', $this->balance($product, $to));
        $this->assertSame('0.000', $this->balance($product, $to, 'quantity_incoming'));

        $movement = Movement::where('handover_id', $id)->latest('id')->first()->load('handover');

        $this->assertSame($to->id, $movement->rack_to_id);
        $this->assertSame(Movement::TYPE_COMPLETE, $movement->type);
    }

    public function test_guest_cannot_access_handovers(): void
    {
        $this->getJson('/api/handovers')->assertUnauthorized();
        $this->postJson('/api/handovers', [])->assertUnauthorized();
        $this->postJson('/api/handovers/1/complete')->assertUnauthorized();
    }

    public function test_handover_cannot_be_updated_or_deleted(): void
    {
        $this->signIn();
        $handover = Handover::factory()->create();

        $this->putJson("/api/handovers/{$handover->id}", [])->assertMethodNotAllowed();
        $this->deleteJson("/api/handovers/{$handover->id}")->assertMethodNotAllowed();
    }

    public function test_index_filters_searches_and_sorts_newest_first(): void
    {
        $user = $this->signIn();
        $old = Handover::factory()->create([
            'code' => 'ST-000001',
            'created_at' => now()->subDay(),
            'custody_id' => $user->id,
            'note' => 'Kirim ke Surabaya',
        ]);
        $new = Handover::factory()->create([
            'code' => 'ST-000002',
            'status' => Handover::STATUS_COMPLETED,
        ]);

        $this->getJson('/api/handovers')->assertOk()->assertJsonPath('data.0.id', $new->id)->assertJsonStructure(['data' => [['custody']]]);
        $this->getJson('/api/handovers?search=surabaya')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $old->id);
        $this->getJson('/api/handovers?filters[0][field]=status&filters[0][operator]=eq&filters[0][value]=completed')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $new->id);
        $this->getJson("/api/handovers?filters[0][field]=custody_id&filters[0][operator]=eq&filters[0][value]={$user->id}")->assertJsonCount(1, 'data');
        $this->getJson('/api/handovers?per_page=1000&sort_by=note')->assertUnprocessable()->assertJsonValidationErrors(['per_page', 'sort_by']);
    }

    public function test_show_returns_items_and_logs(): void
    {
        $user = $this->signIn();
        [$product, $from, $to, $id] = $this->take();

        $this->getJson("/api/handovers/{$id}")
            ->assertOk()
            ->assertJsonPath('data.custody.id', $user->id)
            ->assertJsonPath('data.items.0.product.id', $product->id)
            ->assertJsonPath('data.items.0.rack_from.id', $from->id)
            ->assertJsonPath('data.items.0.rack_to.id', $to->id)
            ->assertJsonPath('data.logs.0.action', 'take')
            ->assertJsonPath('data.logs.0.created_by.id', $user->id)
            ->assertJsonPath('data.logs.0.custody_to.id', $user->id);
    }

    public function test_store_rejects_invalid_input(): void
    {
        $this->signIn();
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();
        $other = Rack::factory()->create();
        $inactive = Rack::factory()->create(['active' => false]);

        $item = [
            'product_id' => $product->id,
            'quantity' => 1,
            'rack_from_id' => $rack->id,
            'rack_to_id' => $other->id,
        ];

        $this->postJson('/api/handovers', [])->assertJsonValidationErrors(['items']);
        $this->postJson('/api/handovers', ['items' => []])->assertJsonValidationErrors(['items']);
        $this->postJson('/api/handovers', ['items' => [[]]])->assertJsonValidationErrors(['items.0.product_id', 'items.0.quantity', 'items.0.rack_from_id', 'items.0.rack_to_id']);
        $this->postJson('/api/handovers', ['items' => [[...$item, 'rack_to_id' => $rack->id]]])->assertJsonValidationErrors(['items.0.rack_to_id']);
        $this->postJson('/api/handovers', ['items' => [[...$item, 'quantity' => 0]]])->assertJsonValidationErrors(['items.0.quantity']);
        $this->postJson('/api/handovers', ['items' => [[...$item, 'quantity' => 1.2345]]])->assertJsonValidationErrors(['items.0.quantity']);
        $this->postJson('/api/handovers', ['items' => [[...$item, 'rack_to_id' => $inactive->id]]])->assertJsonValidationErrors(['items.0.rack_to_id']);
        $this->postJson('/api/handovers', ['items' => [$item, $item]])->assertJsonValidationErrors(['items.1.rack_to_id']);
    }

    public function test_store_rejects_taking_from_rack_without_inventory_row(): void
    {
        $this->signIn();
        $product = Product::factory()->create();

        $errors = $this->postJson('/api/handovers', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'rack_from_id' => Rack::factory()->create()->id,
                    'rack_to_id' => Rack::factory()->create()->id,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->json('errors');

        $this->assertSame('Stok tidak mencukupi.', $errors['items.0.quantity'][0]);
        $this->assertSame(0, Handover::count());
        $this->assertSame(0, Inventory::count());
    }

    public function test_store_rolls_back_when_stock_is_insufficient(): void
    {
        $this->signIn();
        $product = Product::factory()->create();
        $from = Rack::factory()->create();
        $to = Rack::factory()->create();
        Inventory::factory()->create([
            'product_id' => $product->id,
            'quantity' => 3,
            'rack_id' => $from->id,
        ]);

        $errors = $this->postJson('/api/handovers', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3.001,
                    'rack_from_id' => $from->id,
                    'rack_to_id' => $to->id,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->json('errors');

        $this->assertSame('Stok tidak mencukupi.', $errors['items.0.quantity'][0]);
        $this->assertSame(0, Handover::count());
        $this->assertSame(0, Movement::count());
        $this->assertSame('3.000', $this->balance($product, $from));
        $this->assertSame(1, Inventory::count());
    }

    public function test_store_takes_stock_and_marks_incoming(): void
    {
        $user = $this->signIn();
        [$product, $from, $to, $id] = $this->take();

        $handover = Handover::find($id);

        $this->assertSame(sprintf('ST-%06d', $id), $handover->code);
        $this->assertSame(Handover::STATUS_TAKEN, $handover->status);
        $this->assertSame($user->id, $handover->custody_id);
        $this->assertSame('6.000', $this->balance($product, $from));
        $this->assertSame('0.000', $this->balance($product, $to));
        $this->assertSame('4.000', $this->balance($product, $to, 'quantity_incoming'));
        $this->assertSame('4.000', HandoverItem::where('handover_id', $id)->value('quantity'));

        $movement = Movement::where('handover_id', $id)->first();

        $this->assertSame($from->id, $movement->rack_from_id);
        $this->assertNull($movement->rack_to_id);
        $this->assertSame(Movement::TYPE_TAKE, $movement->load('handover')->type);

        $log = $handover->logs()->first();

        $this->assertSame('take', $log->action);
        $this->assertNull($log->custody_from_id);
        $this->assertSame($user->id, $log->custody_to_id);
    }

    public function test_total_stock_is_preserved_through_the_whole_flow(): void
    {
        $this->signIn();
        $other = User::factory()->create();
        [$product, $from, $to, $id] = $this->take(10, 10);

        $total = fn () => Inventory::where('product_id', $product->id)->get()->sum(fn ($row) => (float) $row->quantity + (float) $row->quantity_incoming);

        $this->assertSame(10.0, $total());

        $this->postJson("/api/handovers/{$id}/transfer", ['custody_to_id' => $other->id])->assertOk();
        $this->assertSame(10.0, $total());

        $this->postJson("/api/handovers/{$id}/complete")->assertOk();
        $this->assertSame(10.0, $total());
        $this->assertSame('0.000', $this->balance($product, $from));
        $this->assertSame('10.000', $this->balance($product, $to));
        $this->assertSame(['take', 'transfer', 'complete'], Handover::find($id)->logs()->orderBy('id')->pluck('action')->all());
    }

    public function test_transfer_changes_custody_without_touching_stock(): void
    {
        $user = $this->signIn();
        $other = User::factory()->create();
        [$product, $from, $to, $id] = $this->take();

        $this->postJson("/api/handovers/{$id}/transfer", [
            'custody_to_id' => $other->id,
            'note' => 'Ganti shift',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Pemindahan berhasil diserahkan.')
            ->assertJsonPath('data.custody_id', $other->id)
            ->assertJsonPath('data.status', Handover::STATUS_TAKEN);

        $this->assertSame('6.000', $this->balance($product, $from));
        $this->assertSame('4.000', $this->balance($product, $to, 'quantity_incoming'));
        $this->assertSame(1, Movement::count());

        $log = Handover::find($id)->logs()->latest('id')->first();

        $this->assertSame('transfer', $log->action);
        $this->assertSame($user->id, $log->custody_from_id);
        $this->assertSame($other->id, $log->custody_to_id);
        $this->assertSame($user->id, $log->created_by_id);
        $this->assertSame('Ganti shift', $log->note);
    }

    public function test_transfer_rejects_same_inactive_or_missing_user(): void
    {
        $user = $this->signIn();
        $inactive = User::factory()->create(['active' => false]);
        [, , , $id] = $this->take();

        $this->postJson("/api/handovers/{$id}/transfer", [])->assertJsonValidationErrors(['custody_to_id']);
        $this->postJson("/api/handovers/{$id}/transfer", ['custody_to_id' => 99999])->assertJsonValidationErrors(['custody_to_id']);
        $this->postJson("/api/handovers/{$id}/transfer", ['custody_to_id' => $inactive->id])->assertJsonValidationErrors(['custody_to_id']);
        $this->postJson("/api/handovers/{$id}/transfer", ['custody_to_id' => $user->id])->assertJsonValidationErrors(['custody_to_id']);
    }
}
