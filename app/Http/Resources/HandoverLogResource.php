<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HandoverLogResource extends JsonResource
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
            'custody_from_id' => $this->custody_from_id,
            'custody_to_id' => $this->custody_to_id,
            'handover_id' => $this->handover_id,
            'updated_by_id' => $this->updated_by_id,
            'action' => $this->action,
            'note' => $this->note,
            'created_by' => UserResource::make($this->whenLoaded('createdBy')),
            'custody_from' => UserResource::make($this->whenLoaded('custodyFrom')),
            'custody_to' => UserResource::make($this->whenLoaded('custodyTo')),
        ];
    }
}
