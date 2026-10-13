<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Material;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.image_embeddings.url' => 'http://embedding.test']);
        Http::fake(['http://embedding.test/*' => Http::response([
            'embedding' => [0.1, 0.2, 0.3],
        ])]);
    }

    public function test_deactivating_a_category_deactivates_and_exposes_its_products(): void
    {
        $category = Category::create(['name_en' => 'Food', 'name_ar' => 'طعام']);
        $material = Material::create(['name_en' => 'Glass', 'name_ar' => 'زجاج']);
        $product = Product::create([
            'name_en' => 'Bottle',
            'name_ar' => 'زجاجة',
            'weight' => 1.25,
            'tax' => 15,
            'rating' => 4.5,
            'category_id' => $category->id,
            'material_id' => $material->id,
        ]);

        $this->patchJson("/api/categories/{$category->id}/status", ['status' => false])
            ->assertOk()
            ->assertJsonPath('category.status', false)
            ->assertJsonPath('affected_products.0.id', $product->id);

        $this->assertFalse($product->refresh()->status);
        $this->getJson("/api/categories/{$category->id}/related-products")
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.status', false);
    }

    public function test_bulk_status_only_reactivates_selected_products_after_parent_reactivation(): void
    {
        $category = Category::create(['name_en' => 'Food', 'name_ar' => 'طعام', 'status' => false]);
        $material = Material::create(['name_en' => 'Glass', 'name_ar' => 'زجاج']);
        $product = Product::create([
            'name_en' => 'Bottle',
            'name_ar' => 'زجاجة',
            'weight' => 1,
            'tax' => 0,
            'rating' => 0,
            'category_id' => $category->id,
            'material_id' => $material->id,
            'status' => false,
        ]);

        $this->patchJson("/api/categories/{$category->id}/status", ['status' => true])
            ->assertOk();

        $this->patchJson('/api/products/bulk-status', [
            'product_ids' => [$product->id],
            'status' => true,
        ])->assertOk()->assertJsonPath('products.0.status', true);

        $this->assertTrue($product->refresh()->status);
    }

    public function test_bulk_activation_is_rejected_when_a_parent_is_inactive(): void
    {
        $category = Category::create(['name_en' => 'Food', 'name_ar' => 'طعام']);
        $material = Material::create(['name_en' => 'Glass', 'name_ar' => 'زجاج', 'status' => false]);
        $product = Product::create([
            'name_en' => 'Bottle',
            'name_ar' => 'زجاجة',
            'weight' => 1,
            'tax' => 0,
            'rating' => 0,
            'category_id' => $category->id,
            'material_id' => $material->id,
            'status' => false,
        ]);

        $this->patchJson('/api/products/bulk-status', [
            'product_ids' => [$product->id],
            'status' => true,
        ])->assertStatus(422);

        $this->assertFalse($product->refresh()->status);
    }

    public function test_creation_copies_a_single_language_and_update_only_changes_the_sent_language(): void
    {
        $categoryResponse = $this->postJson('/api/categories', ['name_en' => 'Food']);
        $categoryResponse->assertCreated()
            ->assertJsonPath('data.name_en', 'Food')
            ->assertJsonPath('data.name_ar', 'Food');

        $category = Category::first();
        $this->patchJson("/api/categories/{$category->id}", ['name_ar' => 'طعام'])
            ->assertOk()
            ->assertJsonPath('data.name_en', 'Food')
            ->assertJsonPath('data.name_ar', 'طعام');
    }

    public function test_product_resource_calculates_price_and_returns_uploaded_images(): void
    {
        Storage::fake('public');
        $category = Category::create(['name_en' => 'Food', 'name_ar' => 'طعام']);
        $material = Material::create(['name_en' => 'Glass', 'name_ar' => 'زجاج']);

        $this->putJson('/api/gold-price', ['price' => 100])->assertOk();

        $response = $this->post('/api/products', [
            'name_en' => 'Bottle',
            'weight' => 2,
            'tax' => 5,
            'rating' => 4,
            'category_id' => $category->id,
            'material_id' => $material->id,
            'images' => [UploadedFile::fake()->create('bottle.jpg', 100, 'image/jpeg')],
        ]);

        $response->assertCreated()->assertJsonPath('data.price', 205);
        $this->assertCount(1, Product::first()->images);
        $this->assertSame([0.1, 0.2, 0.3], Product::first()->images->first()->embedding);
    }

    public function test_product_images_can_be_added_deleted_and_reordered(): void
    {
        Storage::fake('public');
        $category = Category::create(['name_en' => 'Food', 'name_ar' => 'طعام']);
        $material = Material::create(['name_en' => 'Glass', 'name_ar' => 'زجاج']);
        $product = Product::create([
            'name_en' => 'Bottle',
            'name_ar' => 'زجاجة',
            'weight' => 1,
            'tax' => 0,
            'rating' => 0,
            'category_id' => $category->id,
            'material_id' => $material->id,
        ]);

        $first = $this->post("/api/products/{$product->id}/images", [
            'image' => UploadedFile::fake()->create('first.jpg', 100, 'image/jpeg'),
        ])->assertCreated()->json('data.id');
        $second = $this->post("/api/products/{$product->id}/images", [
            'image' => UploadedFile::fake()->create('second.jpg', 100, 'image/jpeg'),
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/products/{$product->id}/images/reorder", [
            'image_ids' => [$second, $first],
        ])->assertOk()->assertJsonPath('data.images.0.id', $second);

        $this->deleteJson("/api/products/{$product->id}/images/{$first}")
            ->assertNoContent();
        $this->assertDatabaseMissing('product_images', ['id' => $first]);
    }

    public function test_homepage_flags_and_featured_category_images_are_managed_independently(): void
    {
        Storage::fake('public');
        $category = Category::create(['name_en' => 'Food', 'name_ar' => 'طعام']);
        $material = Material::create(['name_en' => 'Glass', 'name_ar' => 'زجاج']);
        $product = Product::create([
            'name_en' => 'Bottle',
            'name_ar' => 'زجاجة',
            'weight' => 1,
            'tax' => 0,
            'rating' => 0,
            'category_id' => $category->id,
            'material_id' => $material->id,
        ]);

        $this->patchJson("/api/products/{$product->id}/most-sold", ['value' => true])
            ->assertOk()->assertJsonPath('data.most_sold', true);
        $this->patchJson("/api/products/{$product->id}/featured", ['value' => true])
            ->assertOk()->assertJsonPath('data.featured', true);
        $this->patchJson("/api/products/{$product->id}/newly-arrived", ['value' => true])
            ->assertOk()->assertJsonPath('data.newly_arrived', true);

        $featuredCategory = $this->postJson('/api/featured-categories', [
            'category_id' => $category->id,
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/featured-categories/{$featuredCategory}")
            ->assertOk()
            ->assertJsonPath('data.category_id', $category->id)
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.category.name_en', 'Food')
            ->assertJsonPath('data.category.name_ar', 'طعام');

        $this->post("/api/featured-categories/{$featuredCategory}/image", [
            'image' => UploadedFile::fake()->create('featured.jpg', 100, 'image/jpeg'),
        ])->assertOk()->assertJsonPath('data.image.original_name', 'featured.jpg');

        $this->post("/api/featured-categories/{$featuredCategory}/image", [
            'image' => UploadedFile::fake()->create('second.jpg', 100, 'image/jpeg'),
        ])->assertStatus(422);

        $this->deleteJson("/api/featured-categories/{$featuredCategory}/image")
            ->assertOk()->assertJsonPath('data.image', null);
        $this->deleteJson("/api/featured-categories/{$featuredCategory}")
            ->assertNoContent();
        $this->assertDatabaseMissing('featured_categories', ['id' => $featuredCategory]);
    }

    public function test_product_filters_return_only_active_products_as_resources(): void
    {
        $category = Category::create(['name_en' => 'Food', 'name_ar' => 'طعام']);
        $material = Material::create(['name_en' => 'Glass', 'name_ar' => 'زجاج']);
        $activeProduct = Product::create([
            'name_en' => 'Active',
            'name_ar' => 'نشط',
            'weight' => 1,
            'tax' => 0,
            'rating' => 4,
            'category_id' => $category->id,
            'material_id' => $material->id,
            'featured' => true,
            'most_sold' => true,
            'newly_arrived' => true,
        ]);
        Product::create([
            'name_en' => 'Inactive',
            'name_ar' => 'غير نشط',
            'weight' => 1,
            'tax' => 0,
            'rating' => 4,
            'category_id' => $category->id,
            'material_id' => $material->id,
            'status' => false,
            'featured' => true,
            'most_sold' => true,
            'newly_arrived' => true,
        ]);

        foreach (['featured', 'most-sold', 'newly-arrived'] as $filter) {
            $this->getJson("/api/products/{$filter}")
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $activeProduct->id);
        }

        $this->getJson("/api/products/by-category-material?category_id={$category->id}&material_id={$material->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeProduct->id)
            ->assertJsonPath('data.0.category.name_en', 'Food')
            ->assertJsonPath('data.0.material.name_en', 'Glass');
    }
}
