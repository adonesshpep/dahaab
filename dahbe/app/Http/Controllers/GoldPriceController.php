<?php

namespace App\Http\Controllers;

use App\Http\Resources\GoldPriceResource;
use App\Models\GoldPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoldPriceController extends Controller
{
    public function show(): JsonResponse
    {
        return (new GoldPriceResource(
            GoldPrice::query()->firstOrCreate(['id' => 1], ['price' => 0])
        ))->response()->setStatusCode(200);
    }

    public function update(Request $request): JsonResponse
    {
        $price = $request->validate([
            'price' => ['required', 'numeric', 'min:0'],
        ])['price'];

        $goldPrice = GoldPrice::query()->updateOrCreate(['id' => 1], ['price' => $price]);

        return (new GoldPriceResource($goldPrice))->response()->setStatusCode(200);
    }
}
