<?php

namespace App\Http\Requests\Role;

use App\Http\Requests\IndexRequest;
use App\Models\Role;
use Illuminate\Validation\Rule;

class IndexRoleRequest extends IndexRequest
{
    protected string $model = Role::class;

    protected function defaults(): array
    {
        return [
            'sort_by' => 'label',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'sort_by' => ['string', Rule::in(['created_at', 'label', 'name'])],
        ];
    }
}
