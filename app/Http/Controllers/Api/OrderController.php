<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Jobs\SendOrderInvoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Traits\ApiResponseTrait;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Throwable;

class OrderController extends Controller
{
    use ApiResponseTrait;

    private int $checkoutLockSeconds = 15;

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
        $lockKey = "checkout:user:{$user->id}";
        $lock = null;

        try {
            /** @var LockProvider $lockProvider */
            $lockProvider = Cache::store('redis');
            $lock = $lockProvider->lock($lockKey, $this->checkoutLockSeconds);

            if (! $lock->get()) {
                return $this->errorResponse(
                    'Checkout is already in progress for this user. Please wait a moment and try again.',
                    null,
                    409
                );
            }
        } catch (Throwable $e) {
            return $this->errorResponse(
                'Distributed checkout lock is currently unavailable. Please try again shortly.',
                null,
                503
            );
        }

        try {
            DB::beginTransaction();

            // Lock the user's cart rows during checkout to prevent double processing
            // or concurrent cart edits while the order is being created.
            $cartItems = Cart::with('product')
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->get();

            if ($cartItems->isEmpty()) {
                throw new \RuntimeException('Cart is empty');
            }

            $totalAmount = 0;
            $affectedProductIds = [];
            $affectedCategoryIds = [];

            foreach ($cartItems as $item) {
                $inventory = \App\Models\Inventory::where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (! $inventory || $inventory->quantity < $item->quantity) {
                    throw new \RuntimeException("Insufficient stock for product: {$item->product->name}");
                }

                $totalAmount += $item->quantity * $item->product->price;
                $inventory->decrement('quantity', $item->quantity);
                $affectedProductIds[] = $item->product_id;
                $affectedCategoryIds[] = $item->product->category_id;
            }

            $order = Order::create([
                'order_number' => 'ORD-' . Str::random(8) . time(),
                'user_id' => $user->id,
                'total_amount' => $totalAmount,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->product->price,
                ]);
            }

            Cart::where('user_id', $user->id)->delete();

            DB::commit();

            foreach ($affectedProductIds as $productId) {
                Cache::forget("product_{$productId}");
                Cache::forget("inventory_{$productId}");
            }

            $this->clearProductListCache(array_unique($affectedCategoryIds));

            SendOrderInvoice::dispatch($order);

            return $this->successResponse(
                new OrderResource($order->load('items.product')),
                'Order placed successfully',
                201
            );
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->errorResponse('Checkout failed: ' . $e->getMessage(), null, 400);
        } finally {
            optional($lock)->release();
        }
    }

    private function clearProductListCache(array $categoryIds = []): void
    {
        $totalPages = ceil(\App\Models\Product::count() / 15);

        for ($page = 1; $page <= $totalPages; $page++) {
            Cache::forget("products_all_page_{$page}");

            foreach ($categoryIds as $categoryId) {
                Cache::forget("products_{$categoryId}_page_{$page}");
            }
        }
    }
}
