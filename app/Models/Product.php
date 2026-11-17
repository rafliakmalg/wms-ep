<?php

namespace App\Models;

use App\Contracts\Filterable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['active', 'base_unit_id', 'category_id', 'created_by_id', 'description', 'min_stock', 'name', 'sku', 'updated_by_id'])]
class Product extends Model implements Filterable
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $attributes = [
        'active' => true,
        'min_stock' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'min_stock' => 'decimal:3',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function filterable(): array
    {
        return [
            'sku' => 'string',
            'name' => 'string',
            'category_id' => 'integer',
            'base_unit_id' => 'integer',
            'min_stock' => 'decimal',
            'active' => 'boolean',
            'created_at' => 'date',
        ];
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }
}
