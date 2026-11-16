<?php

namespace App\Http\Requests\Adjustment;

use App\Http\Requests\IndexRequest;
use App\Models\Adjustment;
use Illuminate\Validation\Rule;

class IndexAdjustmentRequest extends IndexRequest
{
    protected string $model = Adjustment::class;

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
            'sort_by' => ['string', Rule::in(['code', 'status', 'created_at'])],
        ];
    }
}
