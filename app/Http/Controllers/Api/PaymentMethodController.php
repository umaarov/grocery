<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentMethodController extends Controller
{
    public function getPaymentMethods(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $paymentMethods = $user->paymentMethods()
                ->orderBy('is_default', 'desc')
                ->get()
                ->map(function ($method) {
                    $method->masked_card_number = $method->getMaskedCardNumberAttribute();
                    return $method;
                });

            return response()->json([
                'message' => 'Payment methods retrieved successfully',
                'data' => $paymentMethods
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to retrieve payment methods: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to retrieve payment methods',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function addPaymentMethod(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'card_type' => 'required|string|in:visa,mastercard,paypal,uzcard,humo',
                'card_number' => 'required_unless:card_type,paypal|string|max:19',
                'card_holder' => 'required_unless:card_type,paypal|string|max:255',
                'expiry_month' => 'required_unless:card_type,paypal|string|max:2',
                'expiry_year' => 'required_unless:card_type,paypal|string|max:2',
                'cvv' => 'required_unless:card_type,paypal|string|max:4',
                'is_default' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            if ($request->is_default) {
                $user->paymentMethods()->update(['is_default' => false]);
            }

            $normalizedCardType = $request->card_type;
            if ($request->card_type === 'mastercard') {
                $normalizedCardType = 'mastercard';
            }

            $paymentMethod = $user->paymentMethods()->create([
                'type' => $normalizedCardType,
                'card_type' => $normalizedCardType,
                'card_number' => $request->card_number ?? '',
                'card_holder' => $request->card_holder ?? $user->first_name . ' ' . $user->last_name,
                'expiry_month' => $request->expiry_month ?? '',
                'expiry_year' => $request->expiry_year ?? '',
                'is_default' => $request->is_default ?? false
            ]);

            $paymentMethod->masked_card_number = $paymentMethod->getMaskedCardNumberAttribute();

            return response()->json([
                'message' => 'Payment method added successfully',
                'data' => $paymentMethod
            ], 201);

        } catch (Exception $e) {
            Log::error('Failed to add payment method: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to add payment method',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updatePaymentMethod(Request $request, string $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'card_holder' => 'sometimes|string|max:255',
                'expiry_month' => 'sometimes|string|max:2',
                'expiry_year' => 'sometimes|string|max:2',
                'is_default' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            $paymentMethod = PaymentMethod::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            if ($request->is_default) {
                $user->paymentMethods()->where('id', '!=', $id)->update(['is_default' => false]);
            }

            $paymentMethod->update($request->only([
                'card_holder',
                'expiry_month',
                'expiry_year',
                'is_default'
            ]));

            $paymentMethod->masked_card_number = $paymentMethod->getMaskedCardNumberAttribute();

            return response()->json([
                'message' => 'Payment method updated successfully',
                'data' => $paymentMethod
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to update payment method: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'payment_method_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to update payment method',
                'error' => $e->getMessage()
            ], $e instanceof ModelNotFoundException ? 404 : 500);
        }
    }

    public function deletePaymentMethod(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            $paymentMethod = PaymentMethod::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $wasDefault = $paymentMethod->is_default;

            $paymentMethod->delete();

            if ($wasDefault) {
                $newDefault = $user->paymentMethods()->first();
                if ($newDefault) {
                    $newDefault->update(['is_default' => true]);
                }
            }

            return response()->json([
                'message' => 'Payment method deleted successfully'
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to delete payment method: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'payment_method_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to delete payment method',
                'error' => $e->getMessage()
            ], $e instanceof ModelNotFoundException ? 404 : 500);
        }
    }

    public function setDefaultPaymentMethod(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            $paymentMethod = PaymentMethod::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $user->paymentMethods()->update(['is_default' => false]);

            $paymentMethod->update(['is_default' => true]);

            return response()->json([
                'message' => 'Default payment method set successfully',
                'data' => $paymentMethod
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to set default payment method: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'payment_method_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to set default payment method',
                'error' => $e->getMessage()
            ], $e instanceof ModelNotFoundException ? 404 : 500);
        }
    }
}
