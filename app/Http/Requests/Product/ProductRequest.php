<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
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
            'base_unit_id' => ['required', 'integer', 'exists:units,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string'],
            'min_stock' => ['numeric', 'min:0', 'max:999999999999', 'decimal:0,3'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($this->route('product'))],
            'units' => ['present', 'array'],
            'units.*.factor' => ['required', 'numeric', 'gt:0', 'max:999999999', 'decimal:0,6'],
            'units.*.unit_id' => [
                'required',
                'integer',
                'distinct',
                'exists:units,id',
                Rule::notIn([$this->input('base_unit_id')]),
            ],
        ];
    }
}
