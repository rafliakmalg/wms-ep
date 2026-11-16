<?php

namespace App\Http\Requests\Movement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MovementRequest extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'note' => __('index.movement.note'),
            'product_id' => __('index.movement.product_id'),
            'quantity' => __('index.movement.quantity'),
            'rack_from_id' => __('index.movement.rack_from_id'),
            'rack_to_id' => __('index.movement.rack_to_id'),
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
        $activeRack = Rule::exists('racks', 'id')->where('active', true);

        return [
            'note' => ['nullable', 'string'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999', 'decimal:0,3'],
            'rack_from_id' => ['nullable', 'integer', 'exists:racks,id', 'required_without:rack_to_id', 'different:rack_to_id'],
            'rack_to_id' => ['nullable', 'integer', $activeRack, 'required_without:rack_from_id'],
        ];
    }
}
