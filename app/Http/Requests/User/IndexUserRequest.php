<?php

namespace App\Http\Requests\User;

use App\Http\Requests\IndexRequest;
use Illuminate\Validation\Rule;

class IndexUserRequest extends IndexRequest
{
    protected function defaults(): array
    {
        return [
            'active' => null,
            'role_id' => null,
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
            'role_id' => ['nullable', 'integer'],
            'sort_by' => ['string', Rule::in(['created_at', 'email', 'name'])],
        ];
    }
}
