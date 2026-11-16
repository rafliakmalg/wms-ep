<?php

namespace App\Http\Requests\Handover;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class HandoverRequest extends FormRequest
{
    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $seen = [];

                foreach ($this->input('items') as $index => $item) {
                    $key = $item['product_id'].'-'.$item['rack_from_id'].'-'.$item['rack_to_id'];

                    if (isset($seen[$key])) {
                        $validator->errors()->add("items.{$index}.rack_to_id", __('validation.distinct', ['attribute' => __('index.handover.items.rack_to_id')]));
                    }

                    $seen[$key] = true;
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'items' => __('index.handover.items'),
            'items.*.product_id' => __('index.handover.items.product_id'),
            'items.*.quantity' => __('index.handover.items.quantity'),
            'items.*.rack_from_id' => __('index.handover.items.rack_from_id'),
            'items.*.rack_to_id' => __('index.handover.items.rack_to_id'),
            'note' => __('index.handover.note'),
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999', 'decimal:0,3'],
            'items.*.rack_from_id' => ['required', 'integer', 'exists:racks,id'],
            'items.*.rack_to_id' => ['required', 'integer', $activeRack, 'different:items.*.rack_from_id'],
            'note' => ['nullable', 'string'],
        ];
    }
}
