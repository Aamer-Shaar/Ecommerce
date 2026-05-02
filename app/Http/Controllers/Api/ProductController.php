<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCollection;
use App\Models\Product;
use App\Models\Inventory;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    use ApiResponseTrait;

    //  عرض المنتجات مع Cache
    public function index(Request $request)
    {
        $cacheKey = 'products_' . ($request->category_id ?? 'all') . '_page_' . ($request->page ?? 1);

        $products = Cache::remember($cacheKey, 60, function () use ($request) {


            $query = Product::with(['category', 'inventory']);

            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            return $query->paginate(15);
        });

        return $this->successResponse(new ProductCollection($products), 'Products retrieved successfully');
    }

    //  عرض منتج واحد مع Cache
    public function show($id)
    {
        $product = Cache::remember("product_$id", 60, function () use ($id) {
            return Product::with(['category', 'inventory'])->findOrFail($id);
        });

        return $this->successResponse($product, 'Product retrieved successfully');
    }

    //  إنشاء منتج جديد
    public function store(Request $request)
    {
        if (!auth()->user() || !auth()->user()->is_admin) {
            return $this->errorResponse('Unauthorized', null, 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
        ]);

        $product = Product::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'price' => $request->price,
            'category_id' => $request->category_id,
        ]);

        Inventory::create([
            'product_id' => $product->id,
            'quantity' => 0,
        ]);

        //  حذف كاش 
         Cache::flush();

        return $this->successResponse($product, 'Product created successfully', 201);
    }

    //  تحديث المنتج
    public function update(Request $request, $id)
    {
        if (!auth()->user() || !auth()->user()->is_admin) {
            return $this->errorResponse('Unauthorized', null, 403);
        }

        $product = Product::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric|min:0',
            'category_id' => 'sometimes|exists:categories,id',
        ]);

        if ($request->has('name')) {
            $product->name = $request->name;
            $product->slug = Str::slug($request->name);
        }

        $product->update($request->only(['description', 'price', 'category_id']));

        //  حذف كاش المنتج + كل الكاش
         Cache::forget("product_$id");
        Cache::flush();

        return $this->successResponse($product, 'Product updated successfully');
    }

    //  حذف المنتج
    public function destroy($id)
    {
        if (!auth()->user() || !auth()->user()->is_admin) {
            return $this->errorResponse('Unauthorized', null, 403);
        }

        $product = Product::findOrFail($id);
        $product->delete();

        //   حذف كاش المنتجات فقط
      Cache::forget("product_$id");
        Cache::flush();

        return $this->successResponse(null, 'Product deleted successfully');
    }
}