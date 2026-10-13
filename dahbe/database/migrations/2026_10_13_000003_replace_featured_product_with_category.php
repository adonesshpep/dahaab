<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('featured_categories', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
        });

        DB::table('featured_categories')->orderBy('id')->each(function (object $featuredCategory): void {
            $categoryId = DB::table('products')
                ->where('id', $featuredCategory->product_id)
                ->value('category_id');

            if ($categoryId !== null) {
                DB::table('featured_categories')
                    ->where('id', $featuredCategory->id)
                    ->update(['category_id' => $categoryId]);
            }
        });

        Schema::table('featured_categories', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropUnique(['product_id']);
            $table->dropColumn('product_id');
            $table->unique('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('featured_categories', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
        });

        Schema::table('featured_categories', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropUnique(['category_id']);
            $table->dropColumn('category_id');
            $table->unique('product_id');
        });
    }
};
