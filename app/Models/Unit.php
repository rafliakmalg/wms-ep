<?php

namespace App\Models;

use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'created_by_id', 'name', 'updated_by_id'])]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;
}
