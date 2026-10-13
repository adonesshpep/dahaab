<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['price'])]
class GoldPrice extends Model
{
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'price' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
