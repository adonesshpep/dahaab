<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name_en', 'name_ar', 'status'])]
class Category extends Model
{
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'name_en' => 'string',
            'name_ar' => 'string',
            'status' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
