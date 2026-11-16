<?php

namespace App\Http\Requests\Adjustment;

use App\Models\AdjustmentItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdjustmentRequest extends FormRequest
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
                $decreaseOnly = [AdjustmentItem::REASON_DAMAGED, AdjustmentItem::REASON_LOST];

                foreach ($this->input('items') as $index => $item) {
                    $quantity = (float) $item['quantity'];
                    $attribute = __('index.adjustment.items.quantity');
                    $key = $item['product_id'].'-'.$item['rack_id'];

                    if ($quantity == 0) {
                        $validator->errors()->add("items.{$index}.quantity", __('validation.not_in', ['attribute' => $attribute]));
                    } elseif (
                        $quantity > 0
                        && in_array($item['reason'], $decreaseOnly, true)
                    ) {
                        $validator->errors()->add("items.{$index}.quantity", __('validation.negative', ['attribute' => $attribute]));
                    } elseif (
                        $quantity < 0
                        && $item['reason'] === AdjustmentItem::REASON_FOUND
                    ) {
                        $validator->errors()->add("items.{$index}.quantity", __('validation.positive', ['attribute' => $attribute]));
                    }

                    if (isset($seen[$key])) {
                        $validator->errors()->add("items.{$index}.rack_id", __('validation.distinct', ['attribute' => __('index.adjustment.items.rack_id')]));
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
            'items' => __('index.adjustment.items'),
            'items.*.product_id' => __('index.adjustment.items.product_id'),
            'items.*.quantity' => __('index.adjustment.items.quantity'),
            'items.*.rack_id' => __('index.adjustment.items.rack_id'),
            'items.*.reason' => __('index.adjustment.items.reason'),
            'note' => __('index.adjustment.note'),
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:-999999999999', 'max:999999999999', 'decimal:0,3'],
            'items.*.rack_id' => ['required', 'integer', 'exists:racks,id'],
            'items.*.reason' => ['required', Rule::in(AdjustmentItem::REASONS)],
            'note' => ['nullable', 'string'],
        ];
    }
}
