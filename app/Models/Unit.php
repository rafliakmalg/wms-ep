<?php

namespace App\Models;

use App\Contracts\Filterable;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'created_by_id', 'name', 'updated_by_id'])]
class Unit extends Model implements Filterable
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function filterable(): array
    {
        return [
            'code' => 'string',
            'name' => 'string',
            'created_at' => 'date',
        ];
    }
}
