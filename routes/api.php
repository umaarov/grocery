<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/email/verify/{id}/{token}', [AuthController::class, 'verifyEmail'])
//    ->middleware(['signed', 'throttle:6,1'])
    ->name('api.verification.verify');

Route::get('/auth/google/redirect', [AuthController::class, 'googleRedirect'])->name('api.auth.google.redirect');
Route::get('/auth/google/callback', [AuthController::class, 'googleCallback'])->name('api.auth.google.callback');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail']);
//        ->middleware(['throttle:6,1']);
});

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/products/{id}/review', [ProductController::class, 'addReview']);

    Route::post('/products/{id}/favorite', [ProductController::class, 'toggleFavorite']);
    Route::get('/favorites', [ProductController::class, 'getFavorites']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/cart', [CartController::class, 'getCart']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::put('/cart/items/{itemId}', [CartController::class, 'updateItemQuantity']);
    Route::delete('/cart/items/{itemId}', [CartController::class, 'removeItem']);
    Route::delete('/cart', [CartController::class, 'clearCart']);

    Route::get('/payment-methods', [PaymentMethodController::class, 'getPaymentMethods']);
    Route::post('/payment-methods', [PaymentMethodController::class, 'addPaymentMethod']);
    Route::put('/payment-methods/{id}', [PaymentMethodController::class, 'updatePaymentMethod']);
    Route::delete('/payment-methods/{id}', [PaymentMethodController::class, 'deletePaymentMethod']);
    Route::post('/payment-methods/{id}/default', [PaymentMethodController::class, 'setDefaultPaymentMethod']);

    Route::post('/checkout', [OrderController::class, 'checkout']);
    Route::get('/shipping-methods', [OrderController::class, 'getShippingMethods']);
    Route::post('/orders/{orderId}/shipping-method', [OrderController::class, 'setShippingMethod']);
    Route::get('/shipping-addresses', [OrderController::class, 'getShippingAddresses']);
    Route::post('/shipping-addresses', [OrderController::class, 'saveShippingAddress']);
    Route::post('/orders/{orderId}/shipping-address', [OrderController::class, 'setOrderShippingAddress']);
    Route::get('/payment-methods', [OrderController::class, 'getPaymentMethods']);
    Route::post('/orders/{orderId}/payment', [OrderController::class, 'processPayment']);

    Route::get('/orders', [OrderController::class, 'getOrderHistory']);
    Route::get('/orders/{orderId}', [OrderController::class, 'getOrderDetails']);
    Route::get('/orders/track/{orderNumber}', [OrderController::class, 'trackOrder']);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request): JsonResponse {
    try {
        if (!Auth::check()) {
            Log::warning('Sanctum authenticated route accessed but Auth::check() failed.', [
                'ip' => $request->ip(),
                'token' => $request->bearerToken(),
            ]);

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = $request->user();

        if (!$user) {
            Log::error('Request authenticated, but no user returned.', [
                'ip' => $request->ip(),
                'token' => $request->bearerToken(),
            ]);

            return response()->json(['message' => 'User not found.'], 404);
        }

        $user->makeHidden(['password', 'email_verification_token']);

        Log::info('Authenticated user retrieved', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return response()->json([
            'message' => 'User data retrieved successfully.',
            'user' => $user,
        ], 200);

    } catch (Throwable $e) {
        Log::critical('Unhandled exception in /api/user', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'message' => 'Unexpected error occurred.',
            'error' => 'Server error',
        ], 500);
    }
});
