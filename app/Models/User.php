<?php

namespace App\Models;

use App\Contracts\Filterable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['active', 'id', 'name'])]
class User extends Authenticatable implements Filterable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /** @var array<int, string> */
    public array $permissions = [];

    /** @var array<int, string> */
    public array $roles = [];

    protected $attributes = [
        'active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
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
            'name' => 'string',
            'active' => 'boolean',
            'created_at' => 'date',
        ];
    }
}
