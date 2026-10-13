<?php

namespace App\Http\Resources;

use App\Models\GoldPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $goldPrice = (float) (GoldPrice::query()->value('price') ?? 0);

        return [
            'id' => $this->id,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'weight' => $this->weight,
            'tax' => $this->tax,
            'rating' => $this->rating,
            'price' => round(($goldPrice * (float) $this->weight) + (float) $this->tax, 2),
            'material_id' => $this->material_id,
            'category_id' => $this->category_id,
            'status' => $this->status,
            'newly_arrived' => $this->newly_arrived,
            'featured' => $this->featured,
            'most_sold' => $this->most_sold,
            'images' => $this->whenLoaded('images', fn () => ProductImageResource::collection($this->images)),
            'category' => $this->whenLoaded('category', fn () => new CategoryResource($this->category)),
            'material' => $this->whenLoaded('material', fn () => new MaterialResource($this->material)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
