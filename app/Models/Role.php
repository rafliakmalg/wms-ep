<?php

namespace App\Models;

use App\Contracts\Filterable;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['created_by_id', 'label', 'name', 'updated_by_id'])]
class Role extends Model implements Filterable
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function filterable(): array
    {
        return [
            'label' => 'string',
            'name' => 'string',
            'created_at' => 'date',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
