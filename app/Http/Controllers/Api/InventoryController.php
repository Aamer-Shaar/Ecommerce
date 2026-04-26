<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    use ApiResponseTrait;

    public function show($productId)
    {
        $inventory = Inventory::where('product_id', $productId)->firstOrFail();
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

        return $this->successResponse($inventory, 'Inventory updated successfully');
    }
}