<?php

namespace App\Http\Requests;

use App\Contracts\Filterable;
use App\Support\Filter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class IndexRequest extends FormRequest
{
    /** @var class-string<Filterable> */
    protected string $model;

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'filters' => __('index.filters'),
            'filters.*' => __('index.filters'),
            'page' => __('index.page'),
            'per_page' => __('index.per_page'),
            'search' => __('index.search'),
            'sort_by' => __('index.sort_by'),
            'sort_dir' => __('index.sort_dir'),
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [];
    }

    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing([
            'filters' => [],
            'per_page' => 15,
            'search' => null,
            'sort_dir' => 'asc',
            ...$this->defaults(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...Filter::rules((new $this->model)->filterable()),
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort_dir' => ['string', Rule::in(['asc', 'desc'])],
        ];
    }
}
