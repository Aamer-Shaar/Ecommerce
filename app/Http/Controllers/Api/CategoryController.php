<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    use ApiResponseTrait;

    private int $cacheTTL = 3600;

    public function index()
    {
        $categories = Cache::remember('categories_all', $this->cacheTTL, function () {
            return Category::with('products')->paginate(15);
        });

        return $this->successResponse($categories, 'Categories retrieved successfully');
    }

    public function show($id)
    {
        $category = Cache::remember("category_{$id}", $this->cacheTTL, function () use ($id) {
            return Category::with('products')->findOrFail($id);
        });

        return $this->successResponse($category, 'Category retrieved successfully');
    }

    public function store(Request $request)
    {
        if (!auth()->user() || !auth()->user()->is_admin) {
            return $this->errorResponse('Unauthorized', null, 403);
        }

        $request->validate([
            'name'        => 'required|string|max:255|unique:categories',
            'description' => 'nullable|string',
        ]);

        $category = Category::create([
            'name'        => $request->name,
            'slug'        => Str::slug($request->name),
            'description' => $request->description,
        ]);

        Cache::forget('categories_all');

        return $this->successResponse($category, 'Category created successfully', 201);
    }

    public function update(Request $request, $id)
    {
        if (!auth()->user() || !auth()->user()->is_admin) {
            return $this->errorResponse('Unauthorized', null, 403);
        }

        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
        ]);

        if (array_key_exists('name', $validated)) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category->update($validated);

        Cache::forget("category_{$id}");
        Cache::forget('categories_all');

        return $this->successResponse($category, 'Category updated successfully');
    }

    public function destroy($id)
    {
        if (!auth()->user() || !auth()->user()->is_admin) {
            return $this->errorResponse('Unauthorized', null, 403);
        }

        $category = Category::findOrFail($id);
        $category->delete();

       
        Cache::forget("category_{$id}");
        Cache::forget('categories_all');

        return $this->successResponse(null, 'Category deleted successfully');
    }
}