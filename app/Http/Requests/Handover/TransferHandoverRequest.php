<?php

namespace App\Http\Requests\Handover;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TransferHandoverRequest extends FormRequest
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

                if ((int) $this->input('custody_to_id') === $this->route('handover')->custody_id) {
                    $validator->errors()->add('custody_to_id', __('validation.different', [
                        'attribute' => __('index.handover.custody_to_id'),
                        'other' => __('index.handover.custody_id'),
                    ]));
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
            'custody_to_id' => __('index.handover.custody_to_id'),
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
        return [
            'custody_to_id' => ['required', 'integer', Rule::exists('users', 'id')->where('active', true)],
            'note' => ['nullable', 'string'],
        ];
    }
}
