<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeaturedCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'image' => $this->image_path === null ? null : [
                'url' => url('/storage/'.$this->image_path),
                'original_name' => $this->original_name,
            ],
            'category' => $this->whenLoaded('category', fn () => new CategoryResource($this->category)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
