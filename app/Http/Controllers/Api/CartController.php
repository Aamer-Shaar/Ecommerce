<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartItemResource;
use App\Models\Cart;
use App\Models\Product;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class CartController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        $cart = Cart::with('product')
            ->where('user_id', auth()->id())
            ->get();

        $total = $cart->sum(function ($item) {
            return $item->quantity * $item->product->price;
        });

        $data = [
            'items' => CartItemResource::collection($cart),
            'total' => $total,
        ];

        return $this->successResponse($data, 'Cart retrieved successfully');
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::findOrFail($request->product_id);

        if ($product->inventory->quantity < $request->quantity) {
            return $this->errorResponse('Insufficient stock', null, 400);
        }

        $cartItem = Cart::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'product_id' => $request->product_id,
            ],
            [
                'quantity' => $request->quantity,
            ]
        );

        return $this->successResponse( new CartItemResource($cartItem->load('product')),
         'Product added to cart successfully',
          201);
    }

    public function update(Request $request, $id)
    {
        $cartItem = Cart::where('user_id', auth()->id())->findOrFail($id);

        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $product = $cartItem->product;

        if ($product->inventory->quantity < $request->quantity) {
            return $this->errorResponse('Insufficient stock', null, 400);
        }

        $cartItem->update(['quantity' => $request->quantity]);

        return $this->successResponse($cartItem, 'Cart updated successfully');
    }

    public function destroy($id)
    {
        $cartItem = Cart::where('user_id', auth()->id())->findOrFail($id);
        $cartItem->delete();
        return $this->successResponse(null, 'Item removed from cart successfully');
    }
}