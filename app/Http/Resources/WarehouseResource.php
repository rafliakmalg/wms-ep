<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
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
            'branch_id' => $this->branch_id,
            'created_by_id' => $this->created_by_id,
            'updated_by_id' => $this->updated_by_id,
            'address' => $this->address,
            'code' => $this->code,
            'name' => $this->name,
            'active' => $this->active,
            'branch' => BranchResource::make($this->whenLoaded('branch')),
        ];
    }
}
