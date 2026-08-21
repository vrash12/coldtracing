<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    private const DEFAULT_PICKUP_ADDRESS = 'ColdTrace Warehouse, Quezon City';

    private const DEFAULT_PICKUP_LAT = 14.6760000;

    private const DEFAULT_PICKUP_LNG = 121.0437000;

    private function authorizeCustomer(): void
    {
        if (! Auth::check() || ! Auth::user()->isReceiver()) {
            abort(403, 'Only customers can access customer orders.');
        }
    }

    private function authorizeCustomerOrder(Order $order): void
    {
        $this->authorizeCustomer();

        if ((int) $order->receiver_id !== (int) Auth::id()) {
            abort(404);
        }
    }

    private function canEdit(Order $order): bool
    {
        return $order->status === 'pending';
    }

    private function canDelete(Order $order): bool
    {
        return $order->status === 'pending';
    }

    private function canCancel(Order $order): bool
    {
        return in_array($order->status, ['pending', 'approved'], true);
    }

    public function index(Request $request)
    {
        $this->authorizeCustomer();

        $customer = Auth::user();

        $search = $request->input('search');
        $status = $request->input('status');

        $orders = Order::with(['orderItems.product'])
            ->where('receiver_id', $customer->id)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('order_code', 'like', "%{$search}%")
                        ->orWhere('delivery_address', 'like', "%{$search}%")
                        ->orWhereHas('orderItems.product', function ($productQuery) use ($search) {
                            $productQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $stats = Order::query()
            ->where('receiver_id', $customer->id)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('customer.orders.index', compact(
            'orders',
            'search',
            'status',
            'stats'
        ));
    }

    public function create()
    {
        $this->authorizeCustomer();

        $customer = Auth::user();
        $products = Product::orderBy('name')->get();

        $googleMapsApiKey = config('services.google_maps.key', env('GOOGLE_MAPS_API_KEY'));

        return view('customer.orders.create', compact(
            'customer',
            'products',
            'googleMapsApiKey'
        ));
    }

    public function store(Request $request)
    {
        $this->authorizeCustomer();

        $validated = $this->validateOrder($request);

        DB::transaction(function () use ($request, $validated) {
            $customer = Auth::user();
            $firstItem = $validated['items'][0];

            $order = Order::create([
                'order_code' => $this->generateOrderCode(),
                'created_by' => $customer->id,
                'receiver_id' => $customer->id,

                // Backward compatibility with existing orders table.
                'product_id' => $firstItem['product_id'],
                'quantity' => $firstItem['quantity'],
                'unit' => $firstItem['unit'],

                'pickup_address' => self::DEFAULT_PICKUP_ADDRESS,
                'pickup_lat' => self::DEFAULT_PICKUP_LAT,
                'pickup_lng' => self::DEFAULT_PICKUP_LNG,

                'delivery_address' => $validated['delivery_address'],
                'delivery_lat' => $validated['delivery_lat'],
                'delivery_lng' => $validated['delivery_lng'],

                'expected_delivery_at' => $validated['expected_delivery_at'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'pending',
            ]);

            foreach ($validated['items'] as $item) {
                $order->orderItems()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                ]);
            }

            if ($request->boolean('save_permanent_address')) {
                $customer->update([
                    'permanent_delivery_address' => $validated['delivery_address'],
                    'permanent_delivery_lat' => $validated['delivery_lat'],
                    'permanent_delivery_lng' => $validated['delivery_lng'],
                ]);
            }
        });

        return redirect()
            ->route('customer.orders.index')
            ->with('success', 'Your order request has been submitted successfully.');
    }

    public function show(Order $order)
    {
        $this->authorizeCustomerOrder($order);

        $order->load([
            'orderItems.product',
            'creator',
            'receiver',
        ]);

        return view('customer.orders.show', [
            'order' => $order,
            'canEdit' => $this->canEdit($order),
            'canDelete' => $this->canDelete($order),
            'canCancel' => $this->canCancel($order),
        ]);
    }

    public function edit(Order $order)
    {
        $this->authorizeCustomerOrder($order);

        if (! $this->canEdit($order)) {
            return redirect()
                ->route('customer.orders.show', $order)
                ->with('error', 'Only pending orders can be edited.');
        }

        $customer = Auth::user();
        $products = Product::orderBy('name')->get();
        $order->load('orderItems.product');

        $googleMapsApiKey = config('services.google_maps.key', env('GOOGLE_MAPS_API_KEY'));

        return view('customer.orders.edit', compact(
            'order',
            'customer',
            'products',
            'googleMapsApiKey'
        ));
    }

    public function update(Request $request, Order $order)
    {
        $this->authorizeCustomerOrder($order);

        if (! $this->canEdit($order)) {
            return redirect()
                ->route('customer.orders.show', $order)
                ->with('error', 'Only pending orders can be edited.');
        }

        $validated = $this->validateOrder($request);

        DB::transaction(function () use ($request, $order, $validated) {
            $customer = Auth::user();
            $firstItem = $validated['items'][0];

            $order->update([
                'product_id' => $firstItem['product_id'],
                'quantity' => $firstItem['quantity'],
                'unit' => $firstItem['unit'],

                'delivery_address' => $validated['delivery_address'],
                'delivery_lat' => $validated['delivery_lat'],
                'delivery_lng' => $validated['delivery_lng'],

                'expected_delivery_at' => $validated['expected_delivery_at'] ?? null,
                'notes' => $validated['notes'] ?? null,

                // Customer editing should not change workflow status.
                'status' => $order->status,
            ]);

            $order->orderItems()->delete();

            foreach ($validated['items'] as $item) {
                $order->orderItems()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                ]);
            }

            if ($request->boolean('save_permanent_address')) {
                $customer->update([
                    'permanent_delivery_address' => $validated['delivery_address'],
                    'permanent_delivery_lat' => $validated['delivery_lat'],
                    'permanent_delivery_lng' => $validated['delivery_lng'],
                ]);
            }
        });

        return redirect()
            ->route('customer.orders.show', $order)
            ->with('success', 'Your order has been updated successfully.');
    }

    public function destroy(Order $order)
    {
        $this->authorizeCustomerOrder($order);

        if (! $this->canDelete($order)) {
            return redirect()
                ->route('customer.orders.show', $order)
                ->with('error', 'Only pending orders can be deleted.');
        }

        DB::transaction(function () use ($order) {
            $order->orderItems()->delete();
            $order->delete();
        });

        return redirect()
            ->route('customer.orders.index')
            ->with('success', 'Your pending order has been deleted.');
    }

    public function cancel(Order $order)
    {
        $this->authorizeCustomerOrder($order);

        if (! $this->canCancel($order)) {
            return redirect()
                ->route('customer.orders.show', $order)
                ->with('error', 'This order can no longer be cancelled.');
        }

        $order->update([
            'status' => 'cancelled',
        ]);

        return redirect()
            ->route('customer.orders.show', $order)
            ->with('success', 'Your order has been cancelled.');
    }

    private function validateOrder(Request $request): array
    {
        return $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['required', Rule::in(['kg', 'gram'])],

            'delivery_address' => ['required', 'string', 'max:255'],
            'delivery_lat' => ['required', 'numeric', 'between:-90,90'],
            'delivery_lng' => ['required', 'numeric', 'between:-180,180'],

            'expected_delivery_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'save_permanent_address' => ['nullable', 'boolean'],
        ]);
    }

    private function generateOrderCode(): string
    {
        do {
            $code = 'ORD-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Order::where('order_code', $code)->exists());

        return $code;
    }
}
