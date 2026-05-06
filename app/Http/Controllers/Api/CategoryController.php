<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        $categories = Category::with('products')->paginate(15);
        return $this->successResponse($categories, 'Categories retrieved successfully');
    }

    public function show($id)
    {
        $category = Category::with('products')->findOrFail($id);
        return $this->successResponse($category, 'Category retrieved successfully');
    }

    public function store(Request $request)
    {
        if (!auth()->user() || !auth()->user()->is_admin) {
            return $this->errorResponse('Unauthorized', null, 403);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:categories',
            'description' => 'nullable|string',
        ]);

        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
        ]);

        return $this->successResponse($category, 'Category created successfully', 201);
    }

    public function update(Request $request, $id)
    {
        if (!auth()->user() || !auth()->user()->is_admin) {
            return $this->errorResponse('Unauthorized', null, 403);
        }

        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
        ]);

        if (array_key_exists('name', $validated)) {
            $validated['slug'] = Str::slug($validated['name']);
        }
        $category->update($validated);

        return $this->successResponse($category, 'Category updated successfully');
    }

    public function destroy($id)
    {
        if (!auth()->user() || !auth()->user()->is_admin) {
            return $this->errorResponse('Unauthorized', null, 403);
        }

        $category = Category::findOrFail($id);
        $category->delete();
        return $this->successResponse(null, 'Category deleted successfully');
    }
}
