<?php

namespace App\Models;

use Database\Factories\AdjustmentItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['adjustment_id', 'created_by_id', 'product_id', 'quantity', 'rack_id', 'reason', 'updated_by_id'])]
class AdjustmentItem extends Model
{
    /** @use HasFactory<AdjustmentItemFactory> */
    use HasFactory;

    public const REASON_CORRECTION = 'correction';

    public const REASON_DAMAGED = 'damaged';

    public const REASON_FOUND = 'found';

    public const REASON_LOST = 'lost';

    public const REASONS = [self::REASON_CORRECTION, self::REASON_DAMAGED, self::REASON_FOUND, self::REASON_LOST];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(Adjustment::class);
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
