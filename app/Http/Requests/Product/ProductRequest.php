<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'active' => __('index.product.active'),
            'base_unit_id' => __('index.product.base_unit_id'),
            'category_id' => __('index.product.category_id'),
            'description' => __('index.product.description'),
            'min_stock' => __('index.product.min_stock'),
            'name' => __('index.product.name'),
            'sku' => __('index.product.sku'),
            'units' => __('index.product.units'),
            'units.*.factor' => __('index.product.units.factor'),
            'units.*.unit_id' => __('index.product.units.unit_id'),
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
