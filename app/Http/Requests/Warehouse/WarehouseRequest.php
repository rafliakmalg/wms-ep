<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WarehouseRequest extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'active' => __('index.warehouse.active'),
            'address' => __('index.warehouse.address'),
            'branch_id' => __('index.warehouse.branch_id'),
            'code' => __('index.warehouse.code'),
            'name' => __('index.warehouse.name'),
        ];
    }

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
            'address' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'code' => ['required', 'string', 'max:50', Rule::unique('warehouses', 'code')->ignore($this->route('warehouse'))],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
