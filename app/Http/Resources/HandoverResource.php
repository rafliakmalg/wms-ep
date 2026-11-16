<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HandoverResource extends JsonResource
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
            'custody_id' => $this->custody_id,
            'updated_by_id' => $this->updated_by_id,
            'code' => $this->code,
            'note' => $this->note,
            'status' => $this->status,
            'custody' => UserResource::make($this->whenLoaded('custody')),
            'items' => HandoverItemResource::collection($this->whenLoaded('items')),
            'logs' => HandoverLogResource::collection($this->whenLoaded('logs')),
        ];
    }
}
