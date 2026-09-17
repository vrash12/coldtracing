<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\Trip;
use App\Models\User;
use App\Notifications\OrderAssignedToDriverNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

class OrderController extends Controller
{
    private const DEFAULT_PICKUP_ADDRESS = 'ColdTrace Warehouse, Quezon City';

    private const DEFAULT_PICKUP_LAT = 14.6760000;

    private const DEFAULT_PICKUP_LNG = 121.0437000;

    private function authorizeAdmin(): void
    {
        if (! Auth::check() || ! Auth::user()->isAdministrator()) {
            abort(403, 'Only administrators can access order management.');
        }
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $search = $request->input('search');
        $status = $request->input('status');

        $orders = Order::with([
            'receiver',
            'driver',
            'creator',
            'orderItems.product',
            'trip.truck',
        ])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('order_code', 'like', "%{$search}%")
                        ->orWhere('delivery_address', 'like', "%{$search}%")
                        ->orWhereHas('receiver', function ($receiverQuery) use ($search) {
                            $receiverQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        })
                        ->orWhereHas('driver', function ($driverQuery) use ($search) {
                            $driverQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        })
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
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('admin.orders.index', compact(
            'orders',
            'search',
            'status',
            'stats',
        ));
    }

    public function create()
    {
        $this->authorizeAdmin();

        $products = Product::orderBy('name')->get();
        $receivers = $this->getReceivers();
        $drivers = $this->getDrivers();
        $googleMapsApiKey = config('services.google_maps.key', env('GOOGLE_MAPS_API_KEY'));

        return view('admin.orders.create', compact(
            'products',
            'receivers',
            'drivers',
            'googleMapsApiKey'
        ));
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $this->validateOrder($request);

        $order = DB::transaction(function () use ($validated) {
            $firstItem = $validated['items'][0];
            $hasDriver = ! empty($validated['driver_id']);

            $order = Order::create([
                'order_code' => $this->generateOrderCode(),
                'created_by' => Auth::id(),
                'receiver_id' => $validated['receiver_id'] ?? null,
                'driver_id' => $validated['driver_id'] ?? null,

                // Backward-compatible primary product fields.
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
                'status' => $hasDriver ? 'assigned' : 'pending',
            ]);

            foreach ($validated['items'] as $item) {
                $order->orderItems()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                ]);
            }

            if ($hasDriver) {
                $this->synchronizeAssignedTrip($order);
            }

            return $order->fresh([
                'driver',
                'receiver',
                'orderItems.product',
                'trip.truck',
            ]);
        });

        if ($order->driver_id) {
            $this->notifyAssignedDriver($order);
        }

        $message = $order->driver_id
            ? 'Order created, assigned to the driver, and linked to a pending trip.'
            : 'Order created successfully and is waiting for driver assignment.';

        return redirect()
            ->route('orders.index')
            ->with('success', $message);
    }

    public function show(Order $order)
    {
        $this->authorizeAdmin();

        $order->load([
            'receiver',
            'driver',
            'creator',
            'orderItems.product',
            'trip.truck',
            'trip.latestTelemetry',
        ]);

        return view('admin.orders.show', compact('order'));
    }

    public function edit(Order $order)
    {
        $this->authorizeAdmin();

        $order->load([
            'orderItems.product',
            'driver',
            'trip',
        ]);

        $products = Product::orderBy('name')->get();
        $receivers = $this->getReceivers();
        $drivers = $this->getDrivers();
        $googleMapsApiKey = config('services.google_maps.key', env('GOOGLE_MAPS_API_KEY'));

        return view('admin.orders.edit', compact(
            'order',
            'products',
            'receivers',
            'drivers',
            'googleMapsApiKey'
        ));
    }

    public function update(Request $request, Order $order)
    {
        $this->authorizeAdmin();

        $order->loadMissing('trip');

        if ($this->isLockedForAdministrativeEditing($order)) {
            return redirect()
                ->route('orders.show', $order)
                ->with(
                    'error',
                    'This order can no longer be edited because its trip is active, completed, or cancelled.'
                );
        }

        $oldDriverId = $order->driver_id;
        $validated = $this->validateOrder($request, $order);

        DB::transaction(function () use ($order, $validated) {
            $firstItem = $validated['items'][0];
            $hasDriver = ! empty($validated['driver_id']);

            $order->update([
                'receiver_id' => $validated['receiver_id'] ?? null,
                'driver_id' => $validated['driver_id'] ?? null,

                // Backward-compatible primary product fields.
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
                'status' => $hasDriver ? 'assigned' : 'pending',
            ]);

            $order->orderItems()->delete();

            foreach ($validated['items'] as $item) {
                $order->orderItems()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                ]);
            }

            if ($hasDriver) {
                $this->synchronizeAssignedTrip($order);
            } else {
                $this->removePendingTripAfterDriverRemoval($order);
            }
        });

        $order->refresh();

        if (
            $order->driver_id
            && (int) $oldDriverId !== (int) $order->driver_id
        ) {
            $this->notifyAssignedDriver($order);
        }

        return redirect()
            ->route('orders.index')
            ->with(
                'success',
                'Order, driver assignment, and pending trip were updated successfully.'
            );
    }

    public function cancel(Order $order)
    {
        $this->authorizeAdmin();

        if (! $order->canBeCancelled()) {
            return redirect()
                ->route('orders.index')
                ->with('error', 'This order cannot be cancelled.');
        }

        DB::transaction(function () use ($order) {
            $order->loadMissing('trip.truck');

            $order->update([
                'status' => 'cancelled',
            ]);

            $trip = $order->trip;

            if (! $trip || $trip->status === 'completed') {
                return;
            }

            $truckId = $trip->truck_id;

            $trip->update([
                'status' => 'cancelled',
            ]);

            if ($truckId && ! $this->truckHasAnotherActiveTrip($truckId, $trip->id)) {
                $trip->truck?->update([
                    'status' => 'available',
                ]);
            }
        });

        return redirect()
            ->route('orders.index')
            ->with('success', 'Order and related trip were cancelled successfully.');
    }

    public function destroy(Order $order)
    {
        $this->authorizeAdmin();

        $order->loadMissing('trip');

        if ($order->trip?->status === 'in_progress') {
            return redirect()
                ->route('orders.index')
                ->with('error', 'An order with an active trip cannot be deleted. Cancel it first.');
        }

        DB::transaction(function () use ($order) {
            // Delete the linked trip explicitly because the current database
            // foreign key uses ON DELETE SET NULL, which would leave an orphan trip.
            $order->trip?->delete();
            $order->orderItems()->delete();
            $order->delete();
        });

        return redirect()
            ->route('orders.index')
            ->with('success', 'Order and its related pending trip were deleted successfully.');
    }

    private function validateOrder(Request $request, ?Order $order = null): array
    {
        $driverRoleId = Role::where('name', 'Driver')->value('id');

        $validator = Validator::make($request->all(), [
            'receiver_id' => ['nullable', 'exists:users,id'],

            'driver_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(function ($query) use ($driverRoleId) {
                    $query->where('status', 'active');

                    if ($driverRoleId) {
                        $query->where('role_id', $driverRoleId);
                    }
                }),
            ],

            'expected_delivery_at' => ['nullable', 'required_with:driver_id', 'date'],

            'delivery_address' => ['required', 'string', 'max:255'],
            'delivery_lat' => ['required', 'numeric', 'between:-90,90'],
            'delivery_lng' => ['required', 'numeric', 'between:-180,180'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['required', Rule::in(['kg', 'gram'])],

            'notes' => ['nullable', 'string'],
        ], [
            'items.required' => 'Add at least one item to the order.',
            'items.*.product_id.required' => 'Choose a product for every item.',
            'items.*.quantity.required' => 'Enter a quantity for every item.',
            'items.*.quantity.min' => 'Each quantity must be greater than zero.',
            'delivery_address.required' => 'Choose a delivery destination using the search or map.',
            'delivery_lat.required' => 'Confirm the delivery pin on the map.',
            'delivery_lng.required' => 'Confirm the delivery pin on the map.',
            'expected_delivery_at.required_with' => 'Choose a delivery date and time before assigning a driver.',
            'expected_delivery_at.date' => 'Enter a valid delivery date and time.',
        ], [
            'receiver_id' => 'customer',
            'driver_id' => 'driver',
            'items.*.product_id' => 'product',
            'items.*.quantity' => 'quantity',
            'items.*.unit' => 'unit',
            'expected_delivery_at' => 'delivery time',
        ]);

        $validator->after(function ($validator) use ($request, $order) {
            $driverId = $request->input('driver_id');
            $expectedDeliveryAt = $request->input('expected_delivery_at');

            if (! $driverId) {
                return;
            }

            $driver = User::with('assignedTruck')->find($driverId);

            if (! $driver?->assignedTruck) {
                $validator->errors()->add(
                    'driver_id',
                    'The selected driver does not have an assigned truck.'
                );
            } elseif (in_array($driver->assignedTruck->status, ['maintenance', 'inactive'], true)) {
                $validator->errors()->add(
                    'driver_id',
                    'The selected driver\'s truck is under maintenance or inactive.'
                );
            }

            if (! $expectedDeliveryAt) {
                return;
            }

            if ($this->hasDriverScheduleConflict(
                (int) $driverId,
                $expectedDeliveryAt,
                $order?->id
            )) {
                $validator->errors()->add(
                    'driver_id',
                    'This driver already has an order scheduled at the selected date and time. Please choose another driver or another schedule.'
                );

                $validator->errors()->add(
                    'expected_delivery_at',
                    'The selected delivery schedule is already taken for this driver.'
                );
            }
        });

        return $validator->validate();
    }

    private function hasDriverScheduleConflict(
        int $driverId,
        string $expectedDeliveryAt,
        ?int $ignoreOrderId = null
    ): bool {
        $scheduledAt = Carbon::parse($expectedDeliveryAt)->format('Y-m-d H:i:00');

        return Order::query()
            ->where('driver_id', $driverId)
            ->where('expected_delivery_at', $scheduledAt)
            ->whereNotIn('status', ['cancelled', 'delivered'])
            ->when($ignoreOrderId, function ($query) use ($ignoreOrderId) {
                $query->where('id', '!=', $ignoreOrderId);
            })
            ->exists();
    }

    private function synchronizeAssignedTrip(Order $order): Trip
    {
        if (! $order->driver_id) {
            throw new RuntimeException('A driver is required before creating a trip.');
        }

        $order->unsetRelation('driver');
        $order->unsetRelation('orderItems');

        $order->loadMissing([
            'driver.assignedTruck',
            'receiver',
            'orderItems.product',
            'trip',
        ]);

        $truck = $order->driver?->assignedTruck;
        $firstItem = $order->orderItems->first();

        if (! $truck) {
            throw new RuntimeException(
                'The selected driver does not have an assigned truck.'
            );
        }

        if (! $firstItem) {
            throw new RuntimeException(
                'The order must contain at least one product.'
            );
        }

        $existingTrip = $order->trip;

        // Never reset a trip that has already started or completed.
        if ($existingTrip && $existingTrip->status === 'in_progress') {
            $order->update(['status' => 'in_transit']);

            return $existingTrip;
        }

        if ($existingTrip && $existingTrip->status === 'completed') {
            $order->update(['status' => 'delivered']);

            return $existingTrip;
        }

        $order->update([
            'status' => 'assigned',
        ]);

        return Trip::updateOrCreate(
            [
                'order_id' => $order->id,
            ],
            [
                'truck_id' => $truck->id,
                'product_id' => $firstItem->product_id,
                'driver_id' => $order->driver_id,
                'receiver_id' => $order->receiver_id,

                'origin_address' => $order->pickup_address,
                'origin_lat' => $order->pickup_lat,
                'origin_lng' => $order->pickup_lng,

                'destination_address' => $order->delivery_address,
                'destination_lat' => $order->delivery_lat,
                'destination_lng' => $order->delivery_lng,

                'status' => 'pending',
                'started_at' => null,
                'completed_at' => null,
            ]
        );
    }

    private function removePendingTripAfterDriverRemoval(Order $order): void
    {
        $order->unsetRelation('trip');
        $order->loadMissing('trip');

        $trip = $order->trip;

        if (! $trip) {
            $order->update(['status' => 'pending']);

            return;
        }

        if (! in_array($trip->status, ['pending', 'cancelled'], true)) {
            throw new RuntimeException(
                'The driver cannot be removed from an active or completed trip.'
            );
        }

        $trip->delete();

        $order->update([
            'status' => 'pending',
            'driver_id' => null,
        ]);
    }

    private function isLockedForAdministrativeEditing(Order $order): bool
    {
        if (in_array($order->status, ['in_transit', 'delivered', 'cancelled'], true)) {
            return true;
        }

        return in_array($order->trip?->status, ['in_progress', 'completed', 'cancelled'], true);
    }

    private function truckHasAnotherActiveTrip(int $truckId, int $ignoreTripId): bool
    {
        return Trip::query()
            ->where('truck_id', $truckId)
            ->where('status', 'in_progress')
            ->where('id', '!=', $ignoreTripId)
            ->exists();
    }

    private function notifyAssignedDriver(Order $order): void
    {
        if (! $order->driver_id) {
            return;
        }

        $order->loadMissing([
            'driver',
            'receiver',
            'orderItems.product',
        ]);

        if (! $order->driver) {
            return;
        }

        $order->driver->notify(new OrderAssignedToDriverNotification($order));
    }

    private function getReceivers()
    {
        $receiverRole = Role::where('name', 'Receiver')->first();

        return User::where('status', 'active')
            ->when($receiverRole, function ($query) use ($receiverRole) {
                $query->where('role_id', $receiverRole->id);
            })
            ->orderBy('name')
            ->get();
    }

    private function getDrivers()
    {
        $driverRole = Role::where('name', 'Driver')->first();

        return User::with('assignedTruck')
            ->where('status', 'active')
            ->when($driverRole, function ($query) use ($driverRole) {
                $query->where('role_id', $driverRole->id);
            })
            ->orderBy('name')
            ->get();
    }

    private function generateOrderCode(): string
    {
        do {
            $code = 'ORD-'
                .now()->format('Ymd')
                .'-'
                .strtoupper(substr(uniqid(), -6));
        } while (Order::where('order_code', $code)->exists());

        return $code;
    }
}
