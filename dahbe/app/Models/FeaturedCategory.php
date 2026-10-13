<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['category_id', 'image_path', 'original_name'])]
class FeaturedCategory extends Model
{
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'category_id' => 'integer',
            'image_path' => 'string',
            'original_name' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
