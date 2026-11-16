<?php

namespace App\Models;

use App\Contracts\Filterable;
use Database\Factories\HandoverFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'created_by_id', 'custody_id', 'note', 'status', 'updated_by_id'])]
class Handover extends Model implements Filterable
{
    /** @use HasFactory<HandoverFactory> */
    use HasFactory;

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_TAKEN = 'taken';

    public const STATUSES = [self::STATUS_CANCELLED, self::STATUS_COMPLETED, self::STATUS_TAKEN];

    protected $attributes = [
        'status' => self::STATUS_TAKEN,
    ];

    /**
     * @return array<string, string>
     */
    public function filterable(): array
    {
        return [
            'code' => 'string',
            'custody_id' => 'integer',
            'status' => 'string',
            'created_at' => 'date',
        ];
    }

    public function custody(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custody_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(HandoverItem::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(HandoverLog::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }
}
