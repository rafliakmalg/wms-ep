<?php

namespace App\Http\Requests\Movement;

use App\Http\Requests\IndexRequest;
use App\Models\Movement;
use Illuminate\Validation\Rule;

class IndexMovementRequest extends IndexRequest
{
    protected string $model = Movement::class;

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
            'sort_by' => ['string', Rule::in(['created_at', 'quantity'])],
        ];
    }
}
