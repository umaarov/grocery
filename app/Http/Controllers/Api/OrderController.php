<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\ShippingAddress;
use App\Models\ShippingMethod;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function checkout(Request $request): JsonResponse
    {
        $user = $request->user();
        $cart = $user->cart()->with('items.product')->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'message' => 'Your cart is empty'
            ], 400);
        }

        // Check product stock
        foreach ($cart->items as $item) {
            if ($item->product->stock < $item->quantity) {
                return response()->json([
                    'message' => "Not enough stock for {$item->product->name}. Available: {$item->product->stock}"
                ], 400);
            }
        }

        try {
            DB::beginTransaction();

            // Create order
            $subtotal = $cart->items->sum(function ($item) {
                return $item->price * $item->quantity;
            });

            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => 'ORD-' . strtoupper(Str::random(10)),
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'status' => 'pending',
                'shipping_name' => $user->first_name . ' ' . $user->last_name,
                'shipping_email' => $user->email,
                'shipping_phone' => '',
                'shipping_address' => '',
                'shipping_city' => '',
                'shipping_zip_code' => '',
                'shipping_country' => '',
            ]);

            // Create order items
            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'total' => $item->price * $item->quantity
                ]);

                // Update product stock
                $item->product->decrement('stock', $item->quantity);
            }

            // Clear cart
            $cart->items()->delete();

            DB::commit();

            return response()->json([
                'message' => 'Order created successfully',
                'order' => $order->load('items.product')
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getShippingMethods(): JsonResponse
    {
        $shippingMethods = ShippingMethod::where('is_active', true)->get();

        return response()->json([
            'shipping_methods' => $shippingMethods
        ]);
    }

    public function setShippingMethod(Request $request, $orderId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'shipping_method_id' => 'required|exists:shipping_methods,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $order = Order::where('id', $orderId)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Order not found or cannot be modified'
            ], 404);
        }

        $shippingMethod = ShippingMethod::findOrFail($request->shipping_method_id);

        // Update order with shipping method and recalculate total
        $order->shipping_method = $shippingMethod->name;
        $order->shipping_charges = $shippingMethod->cost;
        $order->total = $order->subtotal + $shippingMethod->cost;
        $order->save();

        return response()->json([
            'message' => 'Shipping method set successfully',
            'order' => $order->load('items.product')
        ]);
    }

    public function getShippingAddresses(Request $request): JsonResponse
    {
        $addresses = $request->user()->shippingAddresses()->orderBy('is_default', 'desc')->get();

        return response()->json([
            'addresses' => $addresses
        ]);
    }

    public function saveShippingAddress(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'zip_code' => 'required|string|max:20',
            'country' => 'required|string|max:100',
            'is_default' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        try {
            DB::beginTransaction();

            // If this is the default address, unset any existing default
            if ($request->is_default) {
                $user->shippingAddresses()->update(['is_default' => false]);
            }

            $address = $user->shippingAddresses()->create($request->all());

            DB::commit();

            return response()->json([
                'message' => 'Shipping address saved successfully',
                'address' => $address
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to save shipping address',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function setOrderShippingAddress(Request $request, $orderId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'address_id' => 'required|exists:shipping_addresses,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $order = Order::where('id', $orderId)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Order not found or cannot be modified'
            ], 404);
        }

        $address = ShippingAddress::where('id', $request->address_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$address) {
            return response()->json([
                'message' => 'Shipping address not found'
            ], 404);
        }

        // Update order with shipping address
        $order->update([
            'shipping_address_id' => $address->id,
            'shipping_name' => $address->name,
            'shipping_email' => $address->email,
            'shipping_phone' => $address->phone,
            'shipping_address' => $address->address,
            'shipping_city' => $address->city,
            'shipping_zip_code' => $address->zip_code,
            'shipping_country' => $address->country,
        ]);

        return response()->json([
            'message' => 'Shipping address set successfully',
            'order' => $order->load('items.product')
        ]);
    }

    public function getPaymentMethods(Request $request): JsonResponse
    {
        $paymentMethods = $request->user()->paymentMethods()->orderBy('is_default', 'desc')->get();

        return response()->json([
            'payment_methods' => $paymentMethods
        ]);
    }

    public function processPayment(Request $request, $orderId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'required|exists:payment_methods,id',
            'payment_details' => 'sometimes|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $order = Order::where('id', $orderId)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Order not found or cannot be processed'
            ], 404);
        }

        $paymentMethod = PaymentMethod::where('id', $request->payment_method_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$paymentMethod) {
            return response()->json([
                'message' => 'Payment method not found'
            ], 404);
        }

        try {
            $order->payment_method = $paymentMethod->type;
            $order->payment_details = [
                'payment_id' => 'PAY-' . strtoupper(Str::random(10)),
                'method' => $paymentMethod->type,
                'card_type' => $paymentMethod->card_type,
                'masked_card_number' => $paymentMethod->getMaskedCardNumberAttribute(),
                'status' => 'succeeded',
                'timestamp' => now()->toIso8601String(),
            ];
            $order->status = 'processing';
            $order->save();

            return response()->json([
                'message' => 'Payment processed successfully',
                'order' => $order->load('items.product')
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Payment processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getOrderHistory(Request $request): JsonResponse
    {
        $orders = $request->user()->orders()
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($orders);
    }

    public function getOrderDetails(Request $request, $orderId): JsonResponse
    {
        $order = Order::where('id', $orderId)
            ->where('user_id', $request->user()->id)
            ->with('items.product', 'shippingAddress')
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'order' => $order
        ]);
    }

    public function trackOrder(Request $request, $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)
            ->where('user_id', $request->user()->id)
            ->with('items.product')
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Order not found'
            ], 404);
        }

        $trackingInfo = [
            'status' => $order->status,
            'timeline' => [
                [
                    'status' => 'Order Placed',
                    'date' => $order->created_at->format('Y-m-d H:i:s'),
                    'completed' => true
                ],
                [
                    'status' => 'Processing',
                    'date' => $order->status === 'pending' ? null : $order->updated_at->format('Y-m-d H:i:s'),
                    'completed' => $order->status !== 'pending'
                ],
                [
                    'status' => 'Shipped',
                    'date' => $order->status === 'shipped' || $order->status === 'delivered' ? $order->updated_at->format('Y-m-d H:i:s') : null,
                    'completed' => $order->status === 'shipped' || $order->status === 'delivered'
                ],
                [
                    'status' => 'Delivered',
                    'date' => $order->status === 'delivered' ? $order->updated_at->format('Y-m-d H:i:s') : null,
                    'completed' => $order->status === 'delivered'
                ]
            ]
        ];

        return response()->json([
            'order' => $order,
            'tracking' => $trackingInfo
        ]);
    }
}
