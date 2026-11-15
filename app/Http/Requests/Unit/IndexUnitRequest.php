<?php

namespace App\Http\Requests\Unit;

use App\Http\Requests\IndexRequest;
use Illuminate\Validation\Rule;

class IndexUnitRequest extends IndexRequest
{
    protected function defaults(): array
    {
        return [
            'sort_by' => 'code',
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
