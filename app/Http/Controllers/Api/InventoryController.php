<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class InventoryController extends Controller
{
    use ApiResponseTrait;

    public function show($productId)
    {
        $inventory = Cache::remember("inventory_{$productId}", 3600, function () use ($productId) {
            return Inventory::where('product_id', $productId)->firstOrFail();
        });

        return $this->successResponse($inventory, 'Inventory retrieved successfully');
    }

    public function update(Request $request, $productId)
    {
        if (!auth()->user() || !auth()->user()->is_admin) {
            return $this->errorResponse('Unauthorized', null, 403);
        }

        $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        $inventory = Inventory::where('product_id', $productId)->firstOrFail();
        $inventory->update(['quantity' => $request->quantity]);

        //امسح كاش المخزون + المنتج + القوائم  
        Cache::forget("inventory_{$productId}");
        Cache::forget("product_{$productId}");
        $this->clearProductListCache();

        return $this->successResponse($inventory, 'Inventory updated successfully');
    }

    private function clearProductListCache(): void
    {
        $totalPages = ceil(\App\Models\Product::count() / 15);
        for ($page = 1; $page <= $totalPages; $page++) {
            Cache::forget("products_all_page_{$page}");
        }
    }
}