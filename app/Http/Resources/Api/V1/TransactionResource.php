<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $category = $this->category;
        $self = route('api.v1.transactions.show', $this->resource);
        $links = [
            'self' => ['href' => $self, 'method' => 'GET'],
            'collection' => ['href' => route('api.v1.transactions.index'), 'method' => 'GET'],
            'update' => ['href' => $self, 'method' => 'PATCH'],
            'delete' => ['href' => $self, 'method' => 'DELETE'],
        ];

        if ($category && ! $category->trashed()) {
            $links['category'] = ['href' => route('api.v1.categories.show', $category), 'method' => 'GET'];
        }

        return [
            'id' => $this->getKey(),
            'title' => $this->title,
            'type' => $this->type,
            'amount' => $this->amount,
            'category' => $category ? [
                'id' => $category->getKey(),
                'name' => $category->name,
                'is_active' => $category->is_active,
            ] : null,
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn (Tag $tag): array => [
                'id' => $tag->getKey(),
                'name' => $tag->name,
                'description' => $tag->description,
                'color' => $tag->color,
            ])->all()),
            'notes' => $this->notes,
            'transaction_date' => $this->transaction_date->toDateString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'links' => $links,
        ];
    }
}
