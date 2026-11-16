<?php

namespace App\Models;

use App\Contracts\Filterable;
use Database\Factories\MovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['adjustment_id', 'created_by_id', 'handover_id', 'note', 'product_id', 'quantity', 'rack_from_id', 'rack_to_id', 'updated_by_id'])]
class Movement extends Model implements Filterable
{
    /** @use HasFactory<MovementFactory> */
    use HasFactory;

    public const TYPE_ADJUST = 'adjust';

    public const TYPE_COMPLETE = 'complete';

    public const TYPE_ISSUE = 'issue';

    public const TYPE_RECEIVE = 'receive';

    public const TYPE_RETURN = 'return';

    public const TYPE_TAKE = 'take';

    public const TYPE_TRANSFER = 'transfer';

    public const TYPES = [
        self::TYPE_ADJUST,
        self::TYPE_COMPLETE,
        self::TYPE_ISSUE,
        self::TYPE_RECEIVE,
        self::TYPE_RETURN,
        self::TYPE_TAKE,
        self::TYPE_TRANSFER,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function filterable(): array
    {
        return [
            'adjustment.code' => 'string',
            'handover.code' => 'string',
            'product_id' => 'integer',
            'product.sku' => 'string',
            'rack_from.code' => 'string',
            'rack_from.warehouse_id' => 'integer',
            'rack_to.code' => 'string',
            'rack_to.warehouse_id' => 'integer',
            'quantity' => 'decimal',
            'created_at' => 'date',
        ];
    }

    protected function type(): Attribute
    {
        return Attribute::get(fn () => match (true) {
            $this->adjustment_id !== null => self::TYPE_ADJUST,
            $this->handover_id !== null && $this->rack_to_id === null => self::TYPE_TAKE,
            $this->handover_id !== null => $this->handover->status === Handover::STATUS_CANCELLED ? self::TYPE_RETURN : self::TYPE_COMPLETE,
            $this->rack_from_id !== null && $this->rack_to_id !== null => self::TYPE_TRANSFER,
            $this->rack_from_id === null => self::TYPE_RECEIVE,
            default => self::TYPE_ISSUE,
        });
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(Adjustment::class);
    }

    public function handover(): BelongsTo
    {
        return $this->belongsTo(Handover::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function rackFrom(): BelongsTo
    {
        return $this->belongsTo(Rack::class, 'rack_from_id');
    }

    public function rackTo(): BelongsTo
    {
        return $this->belongsTo(Rack::class, 'rack_to_id');
    }
}
