<?php

namespace App\Support;

class Lang
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        $validation = [];

        foreach (trans('validation') as $key => $message) {
            $validation['validation.'.$key] = $message;
        }

        return [...trans('index'), ...$validation];
    }
}
