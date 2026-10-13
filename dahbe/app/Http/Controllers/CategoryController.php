<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::query()->latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $category = Category::create($this->validatedNames($request, true) + $request->validate([
            'status' => ['sometimes', 'boolean'],
        ]));

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category);
    }

    public function update(Request $request, Category $category): CategoryResource
    {
        $category->update($this->validatedNames($request, false) + $request->validate([
            'status' => ['prohibited'],
        ]));

        return new CategoryResource($category->refresh());
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json(null, 204);
    }

    public function changeStatus(Request $request, Category $category): JsonResponse
    {
        $status = $request->validate(['status' => ['required', 'boolean']])['status'];
        $affectedProducts = collect();

        DB::transaction(function () use ($category, $status, &$affectedProducts): void {
            $category->update(['status' => $status]);

            if (! $status) {
                $affectedProducts = $category->products()->where('status', true)->get();
                $category->products()->update(['status' => false]);
            }
        });

        return response()->json([
            'category' => new CategoryResource($category->refresh()),
            'affected_products' => ProductResource::collection($affectedProducts),
        ]);
    }

    public function relatedProducts(Category $category): AnonymousResourceCollection
    {
        return ProductResource::collection($category->products()
            ->with(['category', 'material', 'images'])
            ->latest()
            ->get());
    }

    private function validatedNames(Request $request, bool $creation): array
    {
        $rules = $creation
            ? ['name_en' => ['required_without:name_ar', 'string', 'max:255'], 'name_ar' => ['required_without:name_en', 'string', 'max:255']]
            : ['name_en' => ['sometimes', 'string', 'max:255'], 'name_ar' => ['sometimes', 'string', 'max:255']];
        $names = $request->validate($rules);

        if ($creation && count($names) === 1) {
            $names['name_en'] ??= $names['name_ar'];
            $names['name_ar'] ??= $names['name_en'];
        }

        return $names;
    }
}
