<?php

namespace App\Support;

class Quantity
{
    public static function format(float|int|string $value): string
    {
        return number_format((float) $value, 3, '.', '');
    }
}
