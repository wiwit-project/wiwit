<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $self = route('api.v1.categories.show', $this->resource);

        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'is_active' => $this->is_active,
            'transactions_count' => (int) $this->whenCounted('transactions', default: 0),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'links' => [
                'self' => ['href' => $self, 'method' => 'GET'],
                'collection' => ['href' => route('api.v1.categories.index'), 'method' => 'GET'],
                'update' => ['href' => $self, 'method' => 'PATCH'],
                'delete' => ['href' => $self, 'method' => 'DELETE'],
            ],
        ];
    }
}
