<?php

namespace App\Http\Requests\Handover;

use Illuminate\Foundation\Http\FormRequest;

class HandoverActionRequest extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
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
            'note' => ['nullable', 'string'],
        ];
    }
}
