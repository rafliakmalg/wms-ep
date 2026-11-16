<?php

namespace App\Support;

use App\Contracts\Filterable;
use App\Rules\FilterCondition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Filter
{
    private const OPERATORS = [
        'string' => ['eq', 'ne', 'like', 'in', 'null'],
        'integer' => ['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'in', 'between', 'null'],
        'decimal' => ['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'in', 'between', 'null'],
        'boolean' => ['eq'],
        'date' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between', 'null'],
    ];

    private const VALUE_RULES = [
        'string' => ['string', 'max:255'],
        'integer' => ['integer'],
        'decimal' => ['numeric'],
        'boolean' => ['boolean'],
        'date' => ['date_format:Y-m-d'],
    ];

    private const COMPARISONS = [
        'eq' => '=',
        'ne' => '<>',
        'gt' => '>',
        'gte' => '>=',
        'lt' => '<',
        'lte' => '<=',
    ];

    /**
     * @param  array<int, array{field: string, operator: string, value: string}>  $conditions
     */
    public static function apply(Builder $query, array $conditions): Builder
    {
        $model = $query->getModel();

        assert($model instanceof Filterable);

        $types = $model->filterable();

        foreach ($conditions as $condition) {
            $field = $condition['field'];
            $path = explode('.', $field);
            $column = array_pop($path);

            if ($path === []) {
                self::condition($query, $column, $types[$field], $condition['operator'], $condition['value']);

                continue;
            }

            $relationPath = implode('.', array_map(Str::camel(...), $path));

            $query->whereHas($relationPath, function (Builder $relation) use ($column, $types, $field, $condition) {
                self::condition($relation, $column, $types[$field], $condition['operator'], $condition['value']);
            });
        }

        return $query;
    }

    private static function condition(Builder $query, string $field, string $type, string $operator, string $value): void
    {
        $byDate = $type === 'date';

        if ($type === 'boolean' || $operator === 'null') {
            $value = (string) (int) filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        switch ($operator) {
            case 'null':
                $value ? $query->whereNull($field) : $query->whereNotNull($field);
                break;
            case 'like':
                $query->where($field, 'like', '%'.addcslashes($value, '%_\\').'%');
                break;
            case 'in':
                $query->whereIn($field, explode(',', $value));
                break;
            case 'between':
                [$from, $to] = explode(',', $value);
                $byDate
                    ? $query->whereDate($field, '>=', $from)->whereDate($field, '<=', $to)
                    : $query->whereBetween($field, [$from, $to]);
                break;
            default:
                $byDate
                    ? $query->whereDate($field, self::COMPARISONS[$operator], $value)
                    : $query->where($field, self::COMPARISONS[$operator], $value);
        }
    }

    /**
     * @return array<int, string>
     */
    public static function operators(string $type): array
    {
        return self::OPERATORS[$type];
    }

    /**
     * @param  array<string, string>  $filters  field => type
     * @return array<string, mixed>
     */
    public static function rules(array $filters): array
    {
        return [
            'filters' => ['array'],
            'filters.*' => [new FilterCondition($filters)],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function valueRules(string $type): array
    {
        return self::VALUE_RULES[$type];
    }
}
