<?php

namespace App\Http\Requests\Rack;

use App\Http\Requests\IndexRequest;
use Illuminate\Validation\Rule;

class IndexRackRequest extends IndexRequest
{
    protected function defaults(): array
    {
        return [
            'active' => null,
            'sort_by' => 'code',
            'warehouse_id' => null,
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
            'sort_by' => ['string', Rule::in(['code', 'created_at', 'name'])],
            'warehouse_id' => ['nullable', 'integer'],
        ];
    }
}
