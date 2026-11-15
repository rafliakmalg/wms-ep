<?php

namespace App\Http\Requests\Branch;

use App\Http\Requests\IndexRequest;
use App\Models\Branch;
use Illuminate\Validation\Rule;

class IndexBranchRequest extends IndexRequest
{
    protected string $model = Branch::class;

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
