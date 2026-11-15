<?php

namespace App\Http\Requests\Warehouse;

use App\Http\Requests\IndexRequest;
use App\Models\Warehouse;
use Illuminate\Validation\Rule;

class IndexWarehouseRequest extends IndexRequest
{
    protected string $model = Warehouse::class;

    protected function defaults(): array
    {
        return [
            'sort_by' => 'name',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'sort_by' => ['string', Rule::in(['code', 'created_at', 'name'])],
        ];
    }
}
