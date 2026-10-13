<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FeaturedCategoryController;
use App\Http\Controllers\GoldPriceController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::patch('categories/{category}/status', [CategoryController::class, 'changeStatus']);
Route::get('categories/{category}/related-products', [CategoryController::class, 'relatedProducts']);
Route::patch('materials/{material}/status', [MaterialController::class, 'changeStatus']);
Route::get('materials/{material}/related-products', [MaterialController::class, 'relatedProducts']);
Route::patch('products/bulk-status', [ProductController::class, 'bulkStatus']);
Route::patch('products/{product}/status', [ProductController::class, 'changeStatus']);
Route::patch('products/{product}/most-sold', [ProductController::class, 'changeMostSold']);
Route::patch('products/{product}/featured', [ProductController::class, 'changeFeatured']);
Route::patch('products/{product}/newly-arrived', [ProductController::class, 'changeNewlyArrived']);
Route::get('products/featured', [ProductController::class, 'featured']);
Route::get('products/most-sold', [ProductController::class, 'mostSold']);
Route::get('products/newly-arrived', [ProductController::class, 'newlyArrived']);
Route::get('products/by-category-material', [ProductController::class, 'byCategoryAndMaterial']);
Route::post('products/{product}/images', [ProductController::class, 'addImage']);
Route::delete('products/{product}/images/{image}', [ProductController::class, 'deleteImage']);
Route::put('products/{product}/images/reorder', [ProductController::class, 'reorderImages']);
Route::get('gold-price', [GoldPriceController::class, 'show']);
Route::put('gold-price', [GoldPriceController::class, 'update']);

Route::post('featured-categories/{featuredCategory}/image', [FeaturedCategoryController::class, 'addImage']);
Route::delete('featured-categories/{featuredCategory}/image', [FeaturedCategoryController::class, 'removeImage']);

Route::apiResource('featured-categories', FeaturedCategoryController::class)
    ->parameters(['featured-categories' => 'featuredCategory']);

Route::apiResources([
    'categories' => CategoryController::class,
    'materials' => MaterialController::class,
    'products' => ProductController::class,
]);
