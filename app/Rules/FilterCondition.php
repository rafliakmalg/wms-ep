<?php

namespace App\Rules;

use App\Support\Filter;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;

class FilterCondition implements ValidationRule
{
    /**
     * @param  array<string, string>  $filters  field => type
     */
    public function __construct(private array $filters) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $keys = is_array($value) ? array_keys($value) : [];
        sort($keys);

        if ($keys !== ['field', 'operator', 'value']) {
            $fail(__('validation.filter_shape'));

            return;
        }

        if (
            ! is_string($value['field'])
            || ! isset($this->filters[$value['field']])
        ) {
            $fail(__('validation.filter_field'));

            return;
        }

        $type = $this->filters[$value['field']];

        if (
            ! is_string($value['operator'])
            || ! in_array($value['operator'], Filter::operators($type), true)
        ) {
            $fail(__('validation.filter_operator', ['field' => $value['field']]));

            return;
        }

        if (! $this->validValue($type, $value['operator'], $value['value'])) {
            $fail(__('validation.filter_value'));
        }
    }

    private function validValue(string $type, string $operator, mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $rules = match ($operator) {
            'null' => ['boolean'],
            'like' => ['string', 'max:255'],
            default => Filter::valueRules($type),
        };

        $parts = match ($operator) {
            'in' => explode(',', $value),
            'between' => explode(',', $value),
            default => [$value],
        };

        if ($operator === 'between' && count($parts) !== 2) {
            return false;
        }

        foreach ($parts as $part) {
            if (Validator::make(['value' => $part], ['value' => $rules])->fails()) {
                return false;
            }
        }

        return true;
    }
}
