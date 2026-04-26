<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Inventory;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request)
    {
        $query = Product::with(['category', 'inventory']);
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        $products = $query->paginate(15);
        return $this->successResponse($products, 'Products retrieved successfully');
    }

    public function show($id)
    {
        $product = Product::with(['category', 'inventory'])->findOrFail($id);
        return $this->successResponse($product, 'Product retrieved successfully');
    }

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

        return $this->successResponse($product, 'Product created successfully', 201);
    }

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

        return $this->successResponse($product, 'Product updated successfully');
    }

    public function destroy($id)
    {
        if (!auth()->user() || !auth()->user()->is_admin) {
            return $this->errorResponse('Unauthorized', null, 403);
        }

        $product = Product::findOrFail($id);
        $product->delete();
        return $this->successResponse(null, 'Product deleted successfully');
    }
}