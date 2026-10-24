<?php

namespace App\Http\Resources;

use App\Models\Advisor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Advisor
 */
class AdvisorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'display_name' => $this->display_name,
            'role' => $this->role->value,
            'is_admin' => $this->isAdmin(),
        ];
    }
}
