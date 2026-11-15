<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\IndexRequest;
use Illuminate\Validation\Rule;

class IndexProductRequest extends IndexRequest
{
    protected function defaults(): array
    {
        return [
            'active' => null,
            'category_id' => null,
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
            'category_id' => ['nullable', 'integer'],
            'sort_by' => ['string', Rule::in(['created_at', 'name', 'sku'])],
        ];
    }
}
