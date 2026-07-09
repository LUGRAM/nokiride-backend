<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketOrder;
use App\Models\Merchant;
use App\Models\Product;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarketController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function merchants(Request $request): JsonResponse
    {
        $query = Merchant::query()->where('is_active', true)->orderByDesc('rating');

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(fn ($merchants) => $merchants
                ->where('name', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%"));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function products(Merchant $merchant): JsonResponse
    {
        return response()->json(['data' => $merchant->products()->where('is_available', true)->get()]);
    }

    public function storeOrder(Request $request): JsonResponse
    {
        $this->authorize('create', MarketOrder::class);

        $validated = $request->validate([
            'merchant_id' => ['required', 'integer', 'exists:merchants,id'],
            'delivery_address' => ['required', 'string', 'min:3', 'max:255'],
            'payment_method' => ['nullable', 'in:'.implode(',', PaymentService::METHODS)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $merchant = Merchant::query()
            ->whereKey($validated['merchant_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $requestedItems = collect($validated['items'])->keyBy('product_id');
        $products = Product::query()
            ->whereIn('id', $requestedItems->keys())
            ->where('merchant_id', $merchant->id)
            ->where('is_available', true)
            ->get()
            ->keyBy('id');

        if ($products->count() !== $requestedItems->count()) {
            throw ValidationException::withMessages([
                'items' => 'Un ou plusieurs produits ne sont pas disponibles chez ce commerçant.',
            ]);
        }

        $order = DB::transaction(function () use ($merchant, $products, $requestedItems, $request, $validated): MarketOrder {
            $subtotal = 0;

            foreach ($products as $product) {
                $subtotal += $product->price * (int) $requestedItems[$product->id]['quantity'];
            }

            $order = MarketOrder::query()->create([
                'reference' => 'MKT-'.Str::upper(Str::random(8)),
                'user_id' => $request->user()->id,
                'merchant_id' => $merchant->id,
                'delivery_address' => $validated['delivery_address'],
                'subtotal_fcfa' => $subtotal,
                'delivery_fee_fcfa' => $merchant->delivery_fee,
                'total_fcfa' => $subtotal + $merchant->delivery_fee,
                'status' => 'pending',
            ]);

            foreach ($products as $product) {
                $quantity = (int) $requestedItems[$product->id]['quantity'];
                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price_fcfa' => $product->price,
                    'total_fcfa' => $product->price * $quantity,
                ]);
            }

            return $order->load(['merchant', 'items.product']);
        });

        Log::info('Market order created', [
            'order_id' => $order->id,
            'reference' => $order->reference,
            'user_id' => $request->user()->id,
            'merchant_id' => $merchant->id,
            'total_fcfa' => $order->total_fcfa,
        ]);

        $payment = $this->paymentService->mockPayment(
            user: $request->user(),
            amountFcfa: $order->total_fcfa,
            method: $validated['payment_method'] ?? 'noki_pay',
            purpose: 'market_order',
            payable: $order,
        );

        return response()->json(['data' => $order, 'payment' => $payment], 201);
    }
}
