<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Jobs\ProcessOrderJob;
use App\Jobs\SendOrderInvoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        $orders = Order::with('items.product')
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return $this->successResponse($orders, 'Orders retrieved successfully');
    }

    public function show($id)
    {
        $order = Order::with('items.product')
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return $this->successResponse($order, 'Order retrieved successfully');
    }

   public function checkout(Request $request)
{
    $user = auth()->user();

    $cartItems = Cart::with('product')
        ->where('user_id', $user->id)
        ->get();

    if ($cartItems->isEmpty()) {
        return $this->errorResponse('Cart is empty', null, 400);
    }

    DB::beginTransaction();

    try {

        $totalAmount = 0;

        foreach ($cartItems as $item) {

            $inventory = \App\Models\Inventory::where('product_id', $item->product_id)
                ->lockForUpdate()
                ->first();

            if (!$inventory || $inventory->quantity < $item->quantity) {
                throw new \Exception("Insufficient stock for product: {$item->product->name}");
            }

            $totalAmount += $item->quantity * $item->product->price;
        }

        $order = Order::create([
            'order_number' => 'ORD-' . Str::random(8) . time(),
            'user_id' => $user->id,
            'total_amount' => $totalAmount,
            'status' => 'pending',
        ]);

        foreach ($cartItems as $item) {

            $inventory = \App\Models\Inventory::where('product_id', $item->product_id)
                ->lockForUpdate()
                ->first();

            OrderItem::create([
                'order_id'   => $order->id,
                'product_id' => $item->product_id,
                'quantity'   => $item->quantity,
                'price'      => $item->product->price,
            ]);

            $inventory->quantity -= $item->quantity;
            $inventory->save();
        }

        Cart::where('user_id', $user->id)->delete();

        DB::commit();

        SendOrderInvoice::dispatch($order);

        return $this->successResponse(
            new OrderResource($order->load('items.product')),
            'Order placed successfully',
            201
        );

    } catch (\Exception $e) {

        DB::rollBack();

        return $this->errorResponse(
            'Checkout failed: ' . $e->getMessage(),
            null,
            400
        );
    }
}
}