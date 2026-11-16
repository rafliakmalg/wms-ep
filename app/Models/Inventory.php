<?php

namespace App\Models;

use App\Contracts\Filterable;
use Database\Factories\InventoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

#[Fillable(['created_by_id', 'product_id', 'quantity', 'quantity_incoming', 'rack_id', 'updated_by_id'])]
class Inventory extends Model implements Filterable
{
    /** @use HasFactory<InventoryFactory> */
    use HasFactory;

    protected $attributes = [
        'quantity' => 0,
        'quantity_incoming' => 0,
    ];

    /**
     * @param  array<int, array{path: string, product_id: int, quantity?: string, quantity_incoming?: string, rack_id: int}>  $changes
     */
    public static function applyChanges(array $changes, int $userId): void
    {
        usort($changes, fn (array $a, array $b) => [$a['rack_id'], $a['product_id']] <=> [$b['rack_id'], $b['product_id']]);

        foreach ($changes as $change) {
            $quantity = $change['quantity'] ?? '0';
            $incoming = $change['quantity_incoming'] ?? '0';
            $decreasing = str_starts_with($quantity, '-') || str_starts_with($incoming, '-');

            if ($decreasing) {
                self::decrease($change, $quantity, $incoming, $userId);
            } else {
                self::increase($change, $quantity, $incoming, $userId);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'quantity_incoming' => 'decimal:3',
        ];
    }

    /**
     * @param  array{path: string, product_id: int, rack_id: int}  $change
     */
    private static function decrease(array $change, string $quantity, string $incoming, int $userId): void
    {
        $deltas = [
            'quantity' => $quantity,
            'quantity_incoming' => $incoming,
        ];

        $query = self::query()->where([
            'product_id' => $change['product_id'],
            'rack_id' => $change['rack_id'],
        ]);

        foreach ($deltas as $column => $delta) {
            if (str_starts_with($delta, '-')) {
                $query->where($column, '>=', ltrim($delta, '-'));
            }
        }

        $affected = $query->incrementEach($deltas, [
            'updated_at' => now(),
            'updated_by_id' => $userId,
        ]);

        if ($affected === 0) {
            throw ValidationException::withMessages([
                $change['path'] => __('validation.stock_insufficient', ['attribute' => __('index.inventory')]),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function filterable(): array
    {
        return [
            'product_id' => 'integer',
            'product.sku' => 'string',
            'rack_id' => 'integer',
            'quantity' => 'decimal',
            'quantity_incoming' => 'decimal',
            'rack.code' => 'string',
            'rack.warehouse_id' => 'integer',
            'rack.warehouse.branch_id' => 'integer',
            'created_at' => 'date',
        ];
    }

    /**
     * @param  array{path: string, product_id: int, rack_id: int}  $change
     */
    private static function increase(array $change, string $quantity, string $incoming, int $userId): void
    {
        $row = [
            'created_by_id' => $userId,
            'product_id' => $change['product_id'],
            'quantity' => $quantity,
            'quantity_incoming' => $incoming,
            'rack_id' => $change['rack_id'],
            'updated_by_id' => $userId,
        ];

        $update = [
            'quantity' => DB::raw("quantity + {$quantity}"),
            'quantity_incoming' => DB::raw("quantity_incoming + {$incoming}"),
            'updated_by_id' => $userId,
        ];

        self::upsert([$row], ['product_id', 'rack_id'], $update);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }
}
