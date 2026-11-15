<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductUnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'unit_id' => $this->unit_id,
            'factor' => $this->factor,
            'unit' => UnitResource::make($this->whenLoaded('unit')),
        ];
    }
}
