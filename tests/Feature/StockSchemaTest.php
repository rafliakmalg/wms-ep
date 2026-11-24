<?php

namespace Tests\Feature;

use App\Models\Adjustment;
use App\Models\AdjustmentItem;
use App\Models\Handover;
use App\Models\HandoverItem;
use App\Models\HandoverLog;
use App\Models\Inventory;
use App\Models\Movement;
use App\Models\Rack;
use App\Models\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function assertRejected(Closure $callback, string $message): void
    {
        try {
            $callback();
        } catch (QueryException) {
            $this->assertTrue(true);

            return;
        }

        $this->fail($message);
    }

    public function test_adjustment_item_rejects_zero_quantity(): void
    {
        $this->assertRejected(fn () => AdjustmentItem::factory()->create(['quantity' => 0]), 'quantity 0 harus ditolak');

        $item = AdjustmentItem::factory()->create([
            'quantity' => -5,
            'reason' => AdjustmentItem::REASON_LOST,
        ]);

        $this->assertSame('-5.000', $item->fresh()->quantity);
    }

    public function test_factories_create_valid_records(): void
    {
        $records = [
            Adjustment::factory()->create(),
            AdjustmentItem::factory()->create(),
            Handover::factory()->create(),
            HandoverItem::factory()->create(),
            HandoverLog::factory()->create(),
            Inventory::factory()->create(),
            Movement::factory()->create(),
        ];

        foreach ($records as $record) {
            $this->assertModelExists($record);
        }
    }

    public function test_handover_item_is_unique_per_document_product_and_racks(): void
    {
        $item = HandoverItem::factory()->create();

        $this->assertRejected(fn () => HandoverItem::factory()->create([
            'handover_id' => $item->handover_id,
            'product_id' => $item->product_id,
            'rack_from_id' => $item->rack_from_id,
            'rack_to_id' => $item->rack_to_id,
        ]), 'baris yang sama harus ditolak');

        $other = HandoverItem::factory()->create([
            'handover_id' => $item->handover_id,
            'product_id' => $item->product_id,
            'rack_from_id' => $item->rack_from_id,
        ]);

        $this->assertModelExists($other);
    }

    public function test_handover_item_rejects_same_rack_and_non_positive_quantity(): void
    {
        $rack = Rack::factory()->create();

        $this->assertRejected(fn () => HandoverItem::factory()->create([
            'rack_from_id' => $rack->id,
            'rack_to_id' => $rack->id,
        ]), 'rak asal dan tujuan yang sama harus ditolak');

        $this->assertRejected(fn () => HandoverItem::factory()->create(['quantity' => 0]), 'quantity 0 harus ditolak');
    }

    public function test_handover_relations_and_cascade(): void
    {
        $handover = Handover::factory()->create();
        $item = HandoverItem::factory()->create(['handover_id' => $handover->id]);
        $log = HandoverLog::factory()->create(['handover_id' => $handover->id]);

        $this->assertSame($handover->custody_id, $handover->custody->id);
        $this->assertTrue($handover->items->contains($item));
        $this->assertTrue($handover->logs->contains($log));
        $this->assertSame($handover->id, $log->handover->id);
        $this->assertSame($log->custody_to_id, $log->custodyTo->id);
        $this->assertNull($log->custodyFrom);
        $this->assertSame($item->rack_from_id, $item->rackFrom->id);
        $this->assertSame($item->rack_to_id, $item->rackTo->id);

        $handover->delete();

        $this->assertModelMissing($item);
        $this->assertModelMissing($log);
    }

    public function test_handover_with_movements_cannot_be_deleted(): void
    {
        $handover = Handover::factory()->create();
        Movement::factory()->create(['handover_id' => $handover->id]);

        $this->assertRejected(fn () => $handover->delete(), 'dokumen dengan pergerakan tidak boleh dihapus');
    }

    public function test_inventory_defaults_to_zero(): void
    {
        $inventory = Inventory::factory()->create();
        $other = Inventory::create([
            'product_id' => $inventory->product_id,
            'rack_id' => Rack::factory()->create()->id,
        ]);

        $this->assertSame('0.000', $other->fresh()->quantity);
        $this->assertSame('0.000', $other->fresh()->quantity_incoming);
    }

    public function test_inventory_is_unique_per_product_and_rack(): void
    {
        $inventory = Inventory::factory()->create();

        $this->assertRejected(fn () => Inventory::factory()->create([
            'product_id' => $inventory->product_id,
            'rack_id' => $inventory->rack_id,
        ]), 'barang yang sama di rak yang sama harus ditolak');
    }

    public function test_inventory_rejects_negative_quantities(): void
    {
        $this->assertRejected(fn () => Inventory::factory()->create(['quantity' => -1]), 'quantity minus harus ditolak');
        $this->assertRejected(fn () => Inventory::factory()->create(['quantity_incoming' => -1]), 'quantity_incoming minus harus ditolak');
    }

    public function test_models_expose_value_lists(): void
    {
        $this->assertSame(['cancelled', 'completed', 'taken'], Handover::STATUSES);
        $this->assertSame(['cancel', 'complete', 'take', 'transfer'], HandoverLog::ACTIONS);
        $this->assertSame(['correction', 'damaged', 'found', 'lost'], AdjustmentItem::REASONS);
        $this->assertSame(Handover::STATUS_TAKEN, Handover::factory()->create()->fresh()->status);
    }

    public function test_movement_rejects_invalid_combinations(): void
    {
        $rack = Rack::factory()->create();

        $this->assertRejected(fn () => Movement::factory()->create(['rack_to_id' => null]), 'tanpa rak harus ditolak');
        $this->assertRejected(fn () => Movement::factory()->create([
            'rack_from_id' => $rack->id,
            'rack_to_id' => $rack->id,
        ]), 'rak asal dan tujuan yang sama harus ditolak');
        $this->assertRejected(fn () => Movement::factory()->create([
            'adjustment_id' => Adjustment::factory()->create()->id,
            'handover_id' => Handover::factory()->create()->id,
        ]), 'dua dokumen sekaligus harus ditolak');
        $this->assertRejected(fn () => Movement::factory()->create(['quantity' => 0]), 'quantity 0 harus ditolak');
    }

    public function test_movement_relations(): void
    {
        $adjustment = Adjustment::factory()->create();
        $movement = Movement::factory()->create([
            'adjustment_id' => $adjustment->id,
            'rack_from_id' => Rack::factory()->create()->id,
        ]);

        $this->assertSame($adjustment->id, $movement->adjustment->id);
        $this->assertNull($movement->handover);
        $this->assertSame($movement->product_id, $movement->product->id);
        $this->assertSame($movement->rack_from_id, $movement->rackFrom->id);
        $this->assertSame($movement->rack_to_id, $movement->rackTo->id);
        $this->assertTrue($adjustment->movements->contains($movement));
    }

    public function test_rack_lock_and_check_active_rejects_inactive_or_missing_racks(): void
    {
        $active = Rack::factory()->create();
        $inactive = Rack::factory()->create(['active' => false]);

        Rack::lockAndCheckActive(['rack_to_id' => $active->id], 'index.movement.rack_to_id');

        foreach ([$inactive->id, 999999] as $id) {
            try {
                Rack::lockAndCheckActive(['items.0.rack_to_id' => $id], 'index.handover.items.rack_to_id');
            } catch (ValidationException $exception) {
                $this->assertSame('Rak Tujuan yang dipilih tidak valid.', $exception->errors()['items.0.rack_to_id'][0]);

                continue;
            }

            $this->fail('rak nonaktif atau tidak ada harus ditolak');
        }
    }

    public function test_rack_with_inventory_cannot_be_deleted(): void
    {
        $this->actingAsUser(User::factory()->create());
        $inventory = Inventory::factory()->create();

        $this->deleteJson("/api/racks/{$inventory->rack_id}")->assertConflict();

        $this->assertModelExists($inventory->rack);
    }
}
