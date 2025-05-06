<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    /**
     * Get the current user's cart with items
     */
    public function getCart(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $cart = Cart::where('user_id', $user->id)
                ->with(['items.product'])
                ->first();

            if (!$cart) {
                // Create empty cart if none exists
                $cart = Cart::create([
                    'user_id' => $user->id
                ]);
            }

            // Calculate totals
            $subtotal = 0;
            foreach ($cart->items as $item) {
                $subtotal += $item->quantity * $item->product->price;
            }

            return response()->json([
                'message' => 'Cart retrieved successfully',
                'data' => [
                    'cart' => $cart,
                    'subtotal' => $subtotal,
                    'shipping_charges' => config('app.shipping_charges', 0),
                    'total' => $subtotal + config('app.shipping_charges', 0)
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to retrieve cart: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to retrieve cart',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add an item to the cart
     */
    public function addItem(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $product = Product::findOrFail($request->product_id);

            // Check stock availability
            if ($product->stock < $request->quantity) {
                return response()->json([
                    'message' => 'Not enough product in stock',
                    'available_stock' => $product->stock
                ], 400);
            }

            // Get or create cart
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);

            // Check if product already in cart
            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->first();

            if ($cartItem) {
                // Update quantity if product already in cart
                $newQuantity = $cartItem->quantity + $request->quantity;

                // Check stock for total quantity
                if ($product->stock < $newQuantity) {
                    return response()->json([
                        'message' => 'Not enough product in stock for additional quantity',
                        'current_quantity' => $cartItem->quantity,
                        'available_stock' => $product->stock
                    ], 400);
                }

                $cartItem->quantity = $newQuantity;
                $cartItem->save();
                $message = 'Cart item quantity updated';
            } else {
                // Add new cart item
                $cartItem = CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'quantity' => $request->quantity,
                    'price' => $product->price
                ]);
                $message = 'Product added to cart';
            }

            return response()->json([
                'message' => $message,
                'data' => $cartItem->load('product')
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to add item to cart: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'product_id' => $request->product_id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to add item to cart',
                'error' => $e->getMessage()
            ], $e instanceof ModelNotFoundException ? 404 : 500);
        }
    }

    /**
     * Update cart item quantity
     */
    public function updateItemQuantity(Request $request, string $itemId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'quantity' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $cart = Cart::where('user_id', $user->id)->firstOrFail();

            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('id', $itemId)
                ->firstOrFail();

            $product = Product::findOrFail($cartItem->product_id);

            // Check stock availability
            if ($product->stock < $request->quantity) {
                return response()->json([
                    'message' => 'Not enough product in stock',
                    'available_stock' => $product->stock
                ], 400);
            }

            $cartItem->quantity = $request->quantity;
            $cartItem->save();

            return response()->json([
                'message' => 'Cart item updated successfully',
                'data' => $cartItem->load('product')
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to update cart item: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'item_id' => $itemId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to update cart item',
                'error' => $e->getMessage()
            ], $e instanceof ModelNotFoundException ? 404 : 500);
        }
    }

    /**
     * Remove an item from cart
     */
    public function removeItem(Request $request, string $itemId): JsonResponse
    {
        try {
            $user = $request->user();
            $cart = Cart::where('user_id', $user->id)->firstOrFail();

            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('id', $itemId)
                ->firstOrFail();

            $cartItem->delete();

            return response()->json([
                'message' => 'Item removed from cart successfully'
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to remove cart item: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'item_id' => $itemId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to remove item from cart',
                'error' => $e->getMessage()
            ], $e instanceof ModelNotFoundException ? 404 : 500);
        }
    }

    /**
     * Clear all items from cart
     */
    public function clearCart(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $cart = Cart::where('user_id', $user->id)->first();

            if ($cart) {
                CartItem::where('cart_id', $cart->id)->delete();
            }

            return response()->json([
                'message' => 'Cart cleared successfully'
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to clear cart: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to clear cart',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
