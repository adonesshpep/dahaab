<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductImageResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ImageEmbeddingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class ProductController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ProductResource::collection(Product::with(['category', 'material', 'images'])->latest()->get());
    }

    public function featured(): AnonymousResourceCollection
    {
        return $this->activeFlaggedProducts('featured');
    }

    public function mostSold(): AnonymousResourceCollection
    {
        return $this->activeFlaggedProducts('most_sold');
    }

    public function newlyArrived(): AnonymousResourceCollection
    {
        return $this->activeFlaggedProducts('newly_arrived');
    }

    public function byCategoryAndMaterial(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'material_id' => ['required', 'integer', 'exists:materials,id'],
        ]);

        return ProductResource::collection($this->activeProductsQuery()
            ->where('category_id', $data['category_id'])
            ->where('material_id', $data['material_id'])
            ->latest()
            ->get());
    }

    public function store(Request $request): JsonResponse
    {
        $product = DB::transaction(function () use ($request): Product {
            $product = Product::create($this->validatedData($request, true));
            $this->storeImages($request, $product);

            return $product;
        });

        return (new ProductResource($product->load(['category', 'material', 'images'])))->response()->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(['category', 'material', 'images']));
    }

    public function update(Request $request, Product $product): ProductResource
    {
        $product->update($this->validatedData($request, false));

        return new ProductResource($product->refresh()->load(['category', 'material', 'images']));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(null, 204);
    }

    public function bulkStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'status' => ['required', 'boolean'],
        ]);

        $products = Product::with(['category', 'material'])
            ->whereIn('id', $data['product_ids'])
            ->get();

        if ($data['status'] && $products->contains(
            fn (Product $product): bool => ! $product->category->status || ! $product->material->status
        )) {
            return response()->json([
                'message' => 'Products can only be activated when their category and material are active.',
            ], 422);
        }

        Product::whereKey($data['product_ids'])->update(['status' => $data['status']]);

        return response()->json([
            'products' => ProductResource::collection(Product::with(['category', 'material', 'images'])
                ->whereIn('id', $data['product_ids'])
                ->get()),
        ]);
    }

    public function changeStatus(Request $request, Product $product): ProductResource
    {
        $status = $request->validate(['status' => ['required', 'boolean']])['status'];

        if ($status && (! $product->category->status || ! $product->material->status)) {
            abort(422, 'Products can only be activated when their category and material are active.');
        }

        $product->update(['status' => $status]);

        return new ProductResource($product->refresh()->load(['category', 'material', 'images']));
    }

    public function changeMostSold(Request $request, Product $product): ProductResource
    {
        return $this->changeFlag($request, $product, 'most_sold');
    }

    public function changeFeatured(Request $request, Product $product): ProductResource
    {
        return $this->changeFlag($request, $product, 'featured');
    }

    public function changeNewlyArrived(Request $request, Product $product): ProductResource
    {
        return $this->changeFlag($request, $product, 'newly_arrived');
    }

    public function addImage(Request $request, Product $product): JsonResponse
    {
        $image = $request->validate([
            'image' => ['required', 'image', 'max:10240'],
        ])['image'];

        $productImage = $this->storeImage(
            $product,
            $image,
            ((int) $product->images()->max('sort_order')) + 1,
        );

        return (new ProductImageResource($productImage))->response()->setStatusCode(201);
    }

    public function deleteImage(Product $product, ProductImage $image): JsonResponse
    {
        $this->ensureImageBelongsToProduct($product, $image);
        Storage::disk('public')->delete($image->path);
        $image->delete();
        $this->normalizeImageOrder($product);

        return response()->json(null, 204);
    }

    public function reorderImages(Request $request, Product $product): ProductResource
    {
        $imageIds = $request->validate([
            'image_ids' => ['required', 'array', 'min:1'],
            'image_ids.*' => ['integer', 'distinct'],
        ])['image_ids'];
        $productImageIds = $product->images()->pluck('id')->all();

        if (count($imageIds) !== count($productImageIds)
            || array_diff($imageIds, $productImageIds)
            || array_diff($productImageIds, $imageIds)) {
            abort(422, 'image_ids must contain every image belonging to this product exactly once.');
        }

        DB::transaction(function () use ($product, $imageIds): void {
            foreach ($imageIds as $sortOrder => $imageId) {
                $product->images()->whereKey($imageId)->update(['sort_order' => $sortOrder]);
            }
        });

        return new ProductResource($product->refresh()->load(['category', 'material', 'images']));
    }

    private function validatedData(Request $request, bool $creation): array
    {
        $data = $request->validate([
            'name_en' => [$creation ? 'required_without:name_ar' : 'sometimes', 'string', 'max:255'],
            'name_ar' => [$creation ? 'required_without:name_en' : 'sometimes', 'string', 'max:255'],
            'weight' => ['sometimes', 'required', 'numeric', 'min:0'],
            'tax' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'rating' => ['sometimes', 'required', 'numeric', 'min:0', 'max:5'],
            'material_id' => ['sometimes', 'required', 'integer', Rule::exists('materials', 'id')->where('status', true)],
            'category_id' => ['sometimes', 'required', 'integer', Rule::exists('categories', 'id')->where('status', true)],
            'status' => $creation ? ['sometimes', 'boolean'] : ['prohibited'],
            'newly_arrived' => ['sometimes', 'boolean'],
            'featured' => ['sometimes', 'boolean'],
            'most_sold' => ['sometimes', 'boolean'],
            'images' => $creation ? ['sometimes', 'array'] : ['prohibited'],
            'images.*' => $creation ? ['image', 'max:10240'] : ['prohibited'],
        ]);

        if ($creation && count(array_intersect(array_keys($data), ['name_en', 'name_ar'])) === 1) {
            $data['name_en'] ??= $data['name_ar'];
            $data['name_ar'] ??= $data['name_en'];
        }

        unset($data['images']);

        return $data;
    }

    private function changeFlag(Request $request, Product $product, string $flag): ProductResource
    {
        $value = $request->validate(['value' => ['required', 'boolean']])['value'];
        $product->update([$flag => $value]);

        return new ProductResource($product->refresh()->load(['category', 'material', 'images']));
    }

    private function activeFlaggedProducts(string $flag): AnonymousResourceCollection
    {
        return ProductResource::collection($this->activeProductsQuery()
            ->where($flag, true)
            ->latest()
            ->get());
    }

    private function activeProductsQuery(): Builder
    {
        return Product::query()
            ->with(['category', 'material', 'images'])
            ->where('status', true);
    }

    private function ensureImageBelongsToProduct(Product $product, ProductImage $image): void
    {
        if ($image->product_id !== $product->id) {
            abort(404);
        }
    }

    private function normalizeImageOrder(Product $product): void
    {
        foreach ($product->images()->get() as $sortOrder => $remainingImage) {
            if ($remainingImage->sort_order !== $sortOrder) {
                $remainingImage->update(['sort_order' => $sortOrder]);
            }
        }
    }

    private function storeImages(Request $request, Product $product): void
    {
        $storedPaths = [];

        try {
            foreach ($request->file('images', []) as $sortOrder => $image) {
                $productImage = $this->storeImage($product, $image, $sortOrder);
                $storedPaths[] = $productImage->path;
            }
        } catch (Throwable $exception) {
            foreach ($storedPaths as $storedPath) {
                Storage::disk('public')->delete($storedPath);
            }

            throw $exception;
        }
    }

    private function storeImage(Product $product, UploadedFile $image, int $sortOrder): ProductImage
    {
        $path = $image->store('products', 'public');

        try {
            $embedding = app(ImageEmbeddingService::class)->generate(
                Storage::disk('public')->path($path)
            );

            return $product->images()->create([
                'path' => $path,
                'embedding' => $embedding,
                'original_name' => $image->getClientOriginalName(),
                'sort_order' => $sortOrder,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }
    }
}
