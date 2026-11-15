<?php

namespace App\Models;

use App\Contracts\Filterable;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['created_by_id', 'name', 'updated_by_id'])]
class Category extends Model implements Filterable
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function filterable(): array
    {
        return [
            'name' => 'string',
            'created_at' => 'date',
        ];
    }
}
