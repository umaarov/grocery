<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductReview;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Product::with('category');

            // Filter by category
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // Filter featured products
            if ($request->has('featured') && $request->featured) {
                $query->where('is_featured', true);
            }

            // Filter new products
            if ($request->has('new') && $request->new) {
                $query->where('is_new', true);
            }

            // Filter organic products
            if ($request->has('organic') && $request->organic) {
                $query->where('is_organic', true);
            }

            // Filter by price range
            if ($request->has('min_price')) {
                $query->where('price', '>=', $request->min_price);
            }
            if ($request->has('max_price')) {
                $query->where('price', '<=', $request->max_price);
            }

            // Search by name
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%');
            }

            // Sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');

            if (in_array($sortBy, ['name', 'price', 'created_at'])) {
                $query->orderBy($sortBy, $sortOrder);
            }

            $perPage = $request->input('per_page', 10);
            $products = $query->paginate($perPage);

            return response()->json([
                'message' => 'Products retrieved successfully',
                'data' => $products
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to retrieve products: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to retrieve products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $product = Product::with(['category', 'reviews.user'])->findOrFail($id);

            return response()->json([
                'message' => 'Product retrieved successfully',
                'data' => $product
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to retrieve product: ' . $e->getMessage(), [
                'id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Product not found',
                'error' => $e->getMessage()
            ], $e instanceof ModelNotFoundException ? 404 : 500);
        }
    }

    public function addReview(Request $request, string $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $product = Product::findOrFail($id);
            $user = $request->user();

            $existingReview = ProductReview::where('product_id', $product->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existingReview) {
                // Update existing review
                $existingReview->update([
                    'rating' => $request->rating,
                    'comment' => $request->comment ?? $existingReview->comment,
                ]);

                $review = $existingReview;
                $message = 'Review updated successfully';
            } else {
                $review = ProductReview::create([
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                    'rating' => $request->rating,
                    'comment' => $request->comment,
                ]);

                $message = 'Review added successfully';
            }

            return response()->json([
                'message' => $message,
                'data' => $review->load('user')
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to add review: ' . $e->getMessage(), [
                'product_id' => $id,
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to add review',
                'error' => $e->getMessage()
            ], $e instanceof ModelNotFoundException ? 404 : 500);
        }
    }

    public function toggleFavorite(Request $request, string $id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);
            $user = $request->user();

            $isFavorited = $user->favorites()->where('product_id', $product->id)->exists();

            if ($isFavorited) {
                $user->favorites()->detach($product->id);
                $message = 'Product removed from favorites';
                $status = false;
            } else {
                $user->favorites()->attach($product->id);
                $message = 'Product added to favorites';
                $status = true;
            }

            return response()->json([
                'message' => $message,
                'is_favorited' => $status
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to toggle favorite: ' . $e->getMessage(), [
                'product_id' => $id,
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to update favorite status',
                'error' => $e->getMessage()
            ], $e instanceof ModelNotFoundException ? 404 : 500);
        }
    }

    /**
     * Get user's favorite products.
     */
    public function getFavorites(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $favorites = $user->favorites()->with('category')->paginate(10);

            return response()->json([
                'message' => 'Favorites retrieved successfully',
                'data' => $favorites
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get favorites: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to retrieve favorites',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
