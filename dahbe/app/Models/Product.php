<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name_en', 'name_ar', 'weight', 'tax', 'rating', 'material_id', 'category_id',
    'status', 'newly_arrived', 'featured', 'most_sold',
])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'name_en' => 'string',
            'name_ar' => 'string',
            'weight' => 'decimal:3',
            'tax' => 'decimal:2',
            'rating' => 'decimal:2',
            'material_id' => 'integer',
            'category_id' => 'integer',
            'status' => 'boolean',
            'newly_arrived' => 'boolean',
            'featured' => 'boolean',
            'most_sold' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }
}
