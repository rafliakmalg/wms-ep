<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
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
            'address' => $this->address,
            'code' => $this->code,
            'name' => $this->name,
            'phone' => $this->phone,
            'active' => $this->active,
        ];
    }
}
