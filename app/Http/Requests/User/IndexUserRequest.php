<?php

namespace App\Http\Requests\User;

use App\Http\Requests\IndexRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

class IndexUserRequest extends IndexRequest
{
    protected string $model = User::class;

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
            'sort_by' => ['string', Rule::in(['created_at', 'email', 'name', 'username'])],
        ];
    }
}
