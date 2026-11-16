<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HandoverItemResource extends JsonResource
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
            'handover_id' => $this->handover_id,
            'product_id' => $this->product_id,
            'rack_from_id' => $this->rack_from_id,
            'rack_to_id' => $this->rack_to_id,
            'updated_by_id' => $this->updated_by_id,
            'quantity' => $this->quantity,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'rack_from' => RackResource::make($this->whenLoaded('rackFrom')),
            'rack_to' => RackResource::make($this->whenLoaded('rackTo')),
        ];
    }
}
