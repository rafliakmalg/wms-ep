<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
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
            'created_by_id' => $this->created_by_id,
            'product_id' => $this->product_id,
            'rack_id' => $this->rack_id,
            'updated_by_id' => $this->updated_by_id,
            'quantity' => $this->quantity,
            'quantity_incoming' => $this->quantity_incoming,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'rack' => RackResource::make($this->whenLoaded('rack')),
        ];
    }
}
