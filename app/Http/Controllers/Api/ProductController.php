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
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    use ApiResponseTrait;

    private int $cacheTTL = 3600;

    public function index(Request $request)
    {
        $cacheKey = 'products_' . ($request->category_id ?? 'all') . '_page_' . ($request->page ?? 1);
        try {
            $products = Cache::remember($cacheKey, $this->cacheTTL, function () use ($request) {
                $query = Product::with(['category', 'inventory']);

                if ($request->has('category_id')) {
                    $query->where('category_id', $request->category_id);
                }

                return $query->paginate(15);
            });
        } catch (\Throwable $e) {
            // Keep the endpoint responsive even if the remote cache slows down temporarily.
            $query = Product::with(['category', 'inventory']);

            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            $products = $query->paginate(15);
        }

        return $this->successResponse(new ProductCollection($products), 'Products retrieved successfully');
    }

    public function show($id)
    {
        try {
            $product = Cache::remember("product_{$id}", $this->cacheTTL, function () use ($id) {
                return Product::with(['category', 'inventory'])->findOrFail($id);
            });
        } catch (\Throwable $e) {
            $product = Product::with(['category', 'inventory'])->findOrFail($id);
        }

        return $this->successResponse($product, 'Product retrieved successfully');
    }

    public function store(Request $request)
    {
        if (!auth()->user() || !auth()->user()->is_admin) {
            return $this->errorResponse('Unauthorized', null, 403);
        }

        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'required|string',
            'price'       => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
        ]);
        DB::beginTransaction();
         try {
        $product = Product::create([
            'name'        => $request->name,
            'slug'        => Str::slug($request->name),
            'description' => $request->description,
            'price'       => $request->price,
            'category_id' => $request->category_id,
        ]);

        Inventory::create([
            'product_id' => $product->id,
            'quantity'   => 0,
        ]);
         DB::commit();
        
        $this->clearProductListCache($request->category_id);
        return $this->successResponse($product, 'Product created successfully', 201);
        
        } catch (\Exception $e) {
        DB::rollBack();
        return $this->errorResponse('Failed to create product', null, 500);
    }
    }

    public function update(Request $request, $id)
    {
        if (!auth()->user() || !auth()->user()->is_admin) {
            return $this->errorResponse('Unauthorized', null, 403);
        }

        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price'       => 'sometimes|numeric|min:0',
            'category_id' => 'sometimes|exists:categories,id',
        ]);

        if (array_key_exists('name', $validated)) {
            $validated['slug'] = Str::slug($validated['name']);
        }
        $product->update($validated);

        Cache::forget("product_{$id}");
        $this->clearProductListCache($product->category_id);

        return $this->successResponse($product, 'Product updated successfully');
    }

    public function destroy($id)
    {
        if (!auth()->user() || !auth()->user()->is_admin) {
            return $this->errorResponse('Unauthorized', null, 403);
        }

        $product = Product::findOrFail($id);
        $categoryId = $product->category_id;
        $product->delete();

        // ✅ امسح كاش هذا المنتج تحديداً + القوائم
        Cache::forget("product_{$id}");
        $this->clearProductListCache($categoryId);

        return $this->successResponse(null, 'Product deleted successfully');
    }

    private function clearProductListCache(?int $categoryId = null): void
    {
        $totalPages = ceil(\App\Models\Product::count() / 15);
        for ($page = 1; $page <= $totalPages; $page++) {
            Cache::forget("products_all_page_{$page}");

            if ($categoryId) {
                Cache::forget("products_{$categoryId}_page_{$page}");
            }
        }
    }
}
