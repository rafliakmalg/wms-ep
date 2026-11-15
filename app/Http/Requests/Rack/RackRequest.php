<?php

namespace App\Http\Requests\Rack;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'active' => ['boolean'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('racks', 'code')
                    ->where('warehouse_id', $this->input('warehouse_id'))
                    ->ignore($this->route('rack')),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ];
    }
}
