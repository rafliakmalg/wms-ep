<?php

namespace App\Http\Requests\Branch;

use App\Http\Requests\IndexRequest;
use Illuminate\Validation\Rule;

class IndexBranchRequest extends IndexRequest
{
    protected function defaults(): array
    {
        return [
            'active' => null,
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
            'sort_by' => ['string', Rule::in(['code', 'created_at', 'name'])],
        ];
    }
}
