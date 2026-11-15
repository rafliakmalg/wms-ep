<?php

namespace App\Models;

use App\Contracts\Filterable;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['active', 'address', 'code', 'created_by_id', 'name', 'phone', 'updated_by_id'])]
class Branch extends Model implements Filterable
{
    /** @use HasFactory<BranchFactory> */
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
            'active' => 'boolean',
            'created_at' => 'date',
        ];
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }
}
