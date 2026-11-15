<?php

namespace App\Http\Requests\Warehouse;

use App\Http\Requests\IndexRequest;
use Illuminate\Validation\Rule;

class IndexWarehouseRequest extends IndexRequest
{
    protected function defaults(): array
    {
        return [
            'active' => null,
            'branch_id' => null,
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
            'active' => ['nullable', 'boolean'],
            'branch_id' => ['nullable', 'integer'],
            'sort_by' => ['string', Rule::in(['code', 'created_at', 'name'])],
        ];
    }
}
