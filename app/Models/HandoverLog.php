<?php

namespace App\Models;

use Database\Factories\HandoverLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['action', 'created_by_id', 'custody_from_id', 'custody_to_id', 'handover_id', 'note', 'updated_by_id'])]
class HandoverLog extends Model
{
    /** @use HasFactory<HandoverLogFactory> */
    use HasFactory;

    public const ACTION_CANCEL = 'cancel';

    public const ACTION_COMPLETE = 'complete';

    public const ACTION_TAKE = 'take';

    public const ACTION_TRANSFER = 'transfer';

    public const ACTIONS = [self::ACTION_CANCEL, self::ACTION_COMPLETE, self::ACTION_TAKE, self::ACTION_TRANSFER];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function custodyFrom(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custody_from_id');
    }

    public function custodyTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custody_to_id');
    }

    public function handover(): BelongsTo
    {
        return $this->belongsTo(Handover::class);
    }
}
