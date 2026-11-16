<?php

namespace App\Models;

use App\Contracts\Filterable;
use Database\Factories\RackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['active', 'code', 'created_by_id', 'name', 'updated_by_id', 'warehouse_id'])]
class Rack extends Model implements Filterable
{
    /** @use HasFactory<RackFactory> */
    use HasFactory;

    protected $attributes = [
        'active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function filterable(): array
    {
        return [
            'code' => 'string',
            'name' => 'string',
            'warehouse_id' => 'integer',
            'active' => 'boolean',
            'created_at' => 'date',
        ];
    }

    /**
     * @param  array<string, int>  $rackIdsByPath  path => rack_id
     */
    public static function lockAndCheckActive(array $rackIdsByPath, string $attribute): void
    {
        $activeByRackId = self::query()
            ->whereIn('id', array_values($rackIdsByPath))
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('active', 'id');

        foreach ($rackIdsByPath as $path => $rackId) {
            $rackIsActive = $activeByRackId[$rackId] ?? false;

            if (! $rackIsActive) {
                throw ValidationException::withMessages([
                    $path => __('validation.exists', ['attribute' => __($attribute)]),
                ]);
            }
        }
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
