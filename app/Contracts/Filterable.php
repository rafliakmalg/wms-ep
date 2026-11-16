<?php

namespace App\Contracts;

interface Filterable
{
    /**
     * Relation paths are written in snake_case like every other API field (rack_from.code), never as the PHP method name (rackFrom.code).
     *
     * @return array<string, string> column => type
     */
    public function filterable(): array;
}
