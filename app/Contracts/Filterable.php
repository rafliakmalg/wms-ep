<?php

namespace App\Contracts;

interface Filterable
{
    /**
     * @return array<string, string> column => type
     */
    public function filterable(): array;
}
