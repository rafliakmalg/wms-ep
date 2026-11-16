<?php

namespace App\Models;

use App\Contracts\Filterable;
use Database\Factories\AdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'created_by_id', 'note', 'status', 'updated_by_id'])]
class Adjustment extends Model implements Filterable
{
    /** @use HasFactory<AdjustmentFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_CANCELLED];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    /**
     * @return array<string, string>
     */
    public function filterable(): array
    {
        return [
            'code' => 'string',
            'status' => 'string',
            'created_at' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(AdjustmentItem::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }
}
