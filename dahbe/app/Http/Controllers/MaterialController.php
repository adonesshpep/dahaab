<?php

namespace App\Http\Controllers;

use App\Http\Resources\MaterialResource;
use App\Http\Resources\ProductResource;
use App\Models\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class MaterialController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return MaterialResource::collection(Material::query()->latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $material = Material::create($this->validatedNames($request, true) + $request->validate([
            'status' => ['sometimes', 'boolean'],
        ]));

        return (new MaterialResource($material))->response()->setStatusCode(201);
    }

    public function show(Material $material): MaterialResource
    {
        return new MaterialResource($material);
    }

    public function update(Request $request, Material $material): MaterialResource
    {
        $material->update($this->validatedNames($request, false) + $request->validate([
            'status' => ['prohibited'],
        ]));

        return new MaterialResource($material->refresh());
    }

    public function destroy(Material $material): JsonResponse
    {
        $material->delete();

        return response()->json(null, 204);
    }

    public function changeStatus(Request $request, Material $material): JsonResponse
    {
        $status = $request->validate(['status' => ['required', 'boolean']])['status'];
        $affectedProducts = collect();

        DB::transaction(function () use ($material, $status, &$affectedProducts): void {
            $material->update(['status' => $status]);

            if (! $status) {
                $affectedProducts = $material->products()->where('status', true)->get();
                $material->products()->update(['status' => false]);
            }
        });

        return response()->json([
            'material' => new MaterialResource($material->refresh()),
            'affected_products' => ProductResource::collection($affectedProducts),
        ]);
    }

    public function relatedProducts(Material $material): AnonymousResourceCollection
    {
        return ProductResource::collection($material->products()
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
