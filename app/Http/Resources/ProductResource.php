<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'base_unit_id' => $this->base_unit_id,
            'category_id' => $this->category_id,
            'created_by_id' => $this->created_by_id,
            'updated_by_id' => $this->updated_by_id,
            'description' => $this->description,
            'min_stock' => $this->min_stock,
            'name' => $this->name,
            'sku' => $this->sku,
            'active' => $this->active,
            'base_unit' => UnitResource::make($this->whenLoaded('baseUnit')),
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'units' => ProductUnitResource::collection($this->whenLoaded('productUnits')),
        ];
    }
}
