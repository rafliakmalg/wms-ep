<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdjustmentResource extends JsonResource
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
            'updated_by_id' => $this->updated_by_id,
            'code' => $this->code,
            'note' => $this->note,
            'status' => $this->status,
            'items' => AdjustmentItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
