<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('featured_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->restrictOnDelete();
            $table->string('image_path')->nullable();
            $table->string('original_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('featured_categories');
    }
};
