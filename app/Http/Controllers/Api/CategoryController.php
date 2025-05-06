<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $categories = Category::withCount('products')->get();

            return response()->json([
                'message' => 'Categories retrieved successfully',
                'data' => $categories
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to retrieve categories: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to retrieve categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $category = Category::findOrFail($id);

            $perPage = $request->input('per_page', 10);
            $products = $category->products()->paginate($perPage);

            return response()->json([
                'message' => 'Category retrieved successfully',
                'category' => $category,
                'products' => $products
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to retrieve category: ' . $e->getMessage(), [
                'id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Category not found',
                'error' => $e->getMessage()
            ], $e instanceof ModelNotFoundException ? 404 : 500);
        }
    }
}
