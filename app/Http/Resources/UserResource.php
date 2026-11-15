<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'role_id' => $this->role_id,
            'updated_by_id' => $this->updated_by_id,
            'email' => $this->email,
            'name' => $this->name,
            'username' => $this->username,
            'active' => $this->active,
            'role' => RoleResource::make($this->whenLoaded('role')),
        ];
    }
}
