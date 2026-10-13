<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar');
            $table->decimal('weight', 10, 3);
            $table->decimal('tax', 5, 2)->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->boolean('status')->default(true);
            $table->boolean('newly_arrived')->default(false);
            $table->boolean('featured')->default(false);
            $table->boolean('most_sold')->default(false);
            $table->timestamps();

            $table->index(['category_id', 'status']);
            $table->index(['material_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('categories');
    }
};
