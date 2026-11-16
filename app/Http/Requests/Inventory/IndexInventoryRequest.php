<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\IndexRequest;
use App\Models\Inventory;
use Illuminate\Validation\Rule;

class IndexInventoryRequest extends IndexRequest
{
    protected string $model = Inventory::class;

    protected function defaults(): array
    {
        return [
            'sort_by' => 'created_at',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'sort_by' => ['string', Rule::in(['created_at', 'quantity', 'quantity_incoming'])],
        ];
    }
}
