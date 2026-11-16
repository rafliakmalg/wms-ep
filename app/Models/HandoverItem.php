<?php

namespace App\Models;

use Database\Factories\HandoverItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['created_by_id', 'handover_id', 'product_id', 'quantity', 'rack_from_id', 'rack_to_id', 'updated_by_id'])]
class HandoverItem extends Model
{
    /** @use HasFactory<HandoverItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
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
