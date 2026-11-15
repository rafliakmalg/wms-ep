<?php

namespace App\Http\Requests\Rack;

use App\Http\Requests\IndexRequest;
use App\Models\Rack;
use Illuminate\Validation\Rule;

class IndexRackRequest extends IndexRequest
{
    protected string $model = Rack::class;

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
