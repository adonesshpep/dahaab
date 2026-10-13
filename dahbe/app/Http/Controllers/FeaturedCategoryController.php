<?php

namespace App\Http\Controllers;

use App\Http\Resources\FeaturedCategoryResource;
use App\Models\FeaturedCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FeaturedCategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return FeaturedCategoryResource::collection(
            FeaturedCategory::with('category')->latest()->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request, true);
        $image = $data['image'] ?? null;
        unset($data['image']);

        $featuredCategory = FeaturedCategory::create($data);
        if ($image !== null) {
            $this->storeImage($featuredCategory, $image);
        }

        return (new FeaturedCategoryResource(
            $featuredCategory->load('category')
        ))->response()->setStatusCode(201);
    }

    public function show(FeaturedCategory $featuredCategory): FeaturedCategoryResource
    {
        return new FeaturedCategoryResource($featuredCategory->load('category'));
    }

    public function update(Request $request, FeaturedCategory $featuredCategory): FeaturedCategoryResource
    {
        $featuredCategory->update($request->validate([
            'category_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('categories', 'id'),
                Rule::unique('featured_categories', 'category_id')->ignore($featuredCategory->id),
            ],
            'image' => ['prohibited'],
        ]));

        return new FeaturedCategoryResource($featuredCategory->refresh()->load('category'));
    }

    public function destroy(FeaturedCategory $featuredCategory): JsonResponse
    {
        if ($featuredCategory->image_path !== null) {
            Storage::disk('public')->delete($featuredCategory->image_path);
        }
        $featuredCategory->delete();

        return response()->json(null, 204);
    }

    public function addImage(Request $request, FeaturedCategory $featuredCategory): FeaturedCategoryResource
    {
        if ($featuredCategory->image_path !== null) {
            abort(422, 'This featured category already has an image. Remove it before adding another.');
        }

        $image = $request->validate([
            'image' => ['required', 'image', 'max:10240'],
        ])['image'];
        $this->storeImage($featuredCategory, $image);

        return new FeaturedCategoryResource($featuredCategory->refresh()->load('category'));
    }

    public function removeImage(FeaturedCategory $featuredCategory): FeaturedCategoryResource
    {
        if ($featuredCategory->image_path !== null) {
            Storage::disk('public')->delete($featuredCategory->image_path);
            $featuredCategory->update(['image_path' => null, 'original_name' => null]);
        }

        return new FeaturedCategoryResource($featuredCategory->refresh()->load('category'));
    }

    private function validatedData(Request $request, bool $creation): array
    {
        $data = $request->validate([
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id'),
                Rule::unique('featured_categories', 'category_id'),
            ],
            'image' => $creation ? ['sometimes', 'image', 'max:10240'] : ['prohibited'],
        ]);

        return $data;
    }

    private function storeImage(FeaturedCategory $featuredCategory, mixed $image): void
    {
        $featuredCategory->update([
            'image_path' => $image->store('featured-categories', 'public'),
            'original_name' => $image->getClientOriginalName(),
        ]);
    }
}
