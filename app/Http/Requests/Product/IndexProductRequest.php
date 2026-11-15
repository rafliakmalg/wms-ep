<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\IndexRequest;
use App\Models\Product;
use Illuminate\Validation\Rule;

class IndexProductRequest extends IndexRequest
{
    protected string $model = Product::class;

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
            'sort_by' => ['string', Rule::in(['created_at', 'name', 'sku'])],
        ];
    }
}
