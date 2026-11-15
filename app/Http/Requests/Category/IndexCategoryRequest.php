<?php

namespace App\Http\Requests\Category;

use App\Http\Requests\IndexRequest;
use App\Models\Category;
use Illuminate\Validation\Rule;

class IndexCategoryRequest extends IndexRequest
{
    protected string $model = Category::class;

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
            'sort_by' => ['string', Rule::in(['created_at', 'name'])],
        ];
    }
}
