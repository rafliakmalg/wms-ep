<?php

namespace App\Http\Requests\Handover;

use App\Http\Requests\IndexRequest;
use App\Models\Handover;
use Illuminate\Validation\Rule;

class IndexHandoverRequest extends IndexRequest
{
    protected string $model = Handover::class;

    protected function defaults(): array
    {
        return [
            'sort_by' => 'created_at',
            'sort_dir' => 'desc',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'sort_by' => ['string', Rule::in(['code', 'created_at', 'status'])],
        ];
    }
}
