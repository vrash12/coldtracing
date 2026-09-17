@php
    $order = $order ?? null;
    $isEditing = $order !== null;
    $oldItems = old('items');

    if (! $oldItems && $order && $order->orderItems->count()) {
        $oldItems = $order->orderItems->map(fn ($item) => [
            'product_id' => $item->product_id,
            'quantity' => $item->quantity,
            'unit' => in_array($item->unit, ['kg', 'gram'], true) ? $item->unit : 'kg',
        ])->toArray();
    }

    $oldItems = $oldItems ?: [[
        'product_id' => '',
        'quantity' => 1,
        'unit' => 'kg',
    ]];

    $deliveryAddress = old('delivery_address', $order?->delivery_address);
    $deliveryLat = old('delivery_lat', $order?->delivery_lat);
    $deliveryLng = old('delivery_lng', $order?->delivery_lng);
    $selectedDriverId = (string) old('driver_id', $order?->driver_id);
@endphp

<div class="order-form-wrapper">
    @if ($products->isEmpty())
        <div class="ct-callout warning">
            <span class="ct-callout-icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
            <div>
                <strong>There are no products to choose from yet.</strong>
                <span>
                    Every order needs at least one product, so this form cannot be saved until the
                    catalogue has one. Contact the system maintainer to configure the product catalogue.
                </span>
            </div>
        </div>
    @endif

    @if ($drivers->isEmpty())
        <div class="ct-callout">
            <span class="ct-callout-icon"><i class="bi bi-info-circle-fill"></i></span>
            <div>
                <strong>No driver is available to assign yet.</strong>
                <span>
                    You can still save this order and it will wait as <em>pending</em>. A driver
                    becomes selectable once they have an active account and a truck in service.
                    Contact the system maintainer if a truck assignment is needed.
                </span>
            </div>
        </div>
    @endif

    <section class="form-section-card order-step-card">
        <header class="section-header order-step-header">
            <span class="order-step-number" aria-hidden="true">1</span>
            <div class="order-step-copy">
                <h2>Order items</h2>
                <p>Add the products and quantities included in this delivery.</p>
            </div>
            <button type="button" class="add-product-button" onclick="addOrderItem()">
                <i class="bi bi-plus-lg"></i>
                Add item
            </button>
        </header>

        <div class="order-items-card">
            <div id="orderItemsList" class="order-items-list">
                @foreach ($oldItems as $index => $item)
                    @php
                        $selectedUnit = in_array(($item['unit'] ?? 'kg'), ['kg', 'gram'], true)
                            ? ($item['unit'] ?? 'kg')
                            : 'kg';
                    @endphp

                    <div class="order-item-row">
                        <div class="form-group product-field">
                            <label for="admin_item_product_{{ $index }}">Product</label>
                            <select id="admin_item_product_{{ $index }}" name="items[{{ $index }}][product_id]" required>
                                <option value="">Choose a product</option>
                                @foreach ($products as $product)
                                    <option
                                        value="{{ $product->id }}"
                                        {{ (string) ($item['product_id'] ?? '') === (string) $product->id ? 'selected' : '' }}
                                    >
                                        {{ $product->name }} ({{ $product->min_temp }}°C–{{ $product->max_temp }}°C)
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group quantity-field">
                            <label for="admin_item_quantity_{{ $index }}">Quantity</label>
                            <input
                                id="admin_item_quantity_{{ $index }}"
                                type="number"
                                inputmode="decimal"
                                step="0.01"
                                min="0.01"
                                name="items[{{ $index }}][quantity]"
                                value="{{ $item['quantity'] ?? 1 }}"
                                required
                            >
                        </div>

                        <div class="form-group unit-field">
                            <label for="admin_item_unit_{{ $index }}">Unit</label>
                            <select id="admin_item_unit_{{ $index }}" name="items[{{ $index }}][unit]" required>
                                <option value="kg" {{ $selectedUnit === 'kg' ? 'selected' : '' }}>kg</option>
                                <option value="gram" {{ $selectedUnit === 'gram' ? 'selected' : '' }}>gram</option>
                            </select>
                        </div>

                        <button
                            type="button"
                            class="remove-product-button"
                            onclick="removeOrderItem(this)"
                            aria-label="Remove this item"
                            title="Remove item"
                        >
                            <i class="bi bi-trash3"></i>
                        </button>
                    </div>
                @endforeach
            </div>
        </div>

        @error('items')
            <small class="error-text">{{ $message }}</small>
        @enderror
        @error('items.*.product_id')
            <small class="error-text">{{ $message }}</small>
        @enderror
        @error('items.*.quantity')
            <small class="error-text">{{ $message }}</small>
        @enderror
        @error('items.*.unit')
            <small class="error-text">{{ $message }}</small>
        @enderror
    </section>

    <section class="form-section-card order-step-card">
        <header class="section-header order-step-header">
            <span class="order-step-number" aria-hidden="true">2</span>
            <div class="order-step-copy">
                <h2>Customer and dispatch</h2>
                <p>Assigning a driver now also creates the pending trip.</p>
            </div>
        </header>

        <div class="form-grid dispatch-grid">
            @if ($receivers->isNotEmpty())
                <div class="form-group">
                    <label for="receiver_id">Customer <span class="field-optional">Optional</span></label>
                    <select id="receiver_id" name="receiver_id">
                        <option value="">No customer selected</option>
                        @foreach ($receivers as $receiver)
                            <option
                                value="{{ $receiver->id }}"
                                {{ (string) old('receiver_id', $order?->receiver_id) === (string) $receiver->id ? 'selected' : '' }}
                            >
                                {{ $receiver->name }} · {{ $receiver->email }}
                            </option>
                        @endforeach
                    </select>
                    @error('receiver_id')
                        <small class="error-text">{{ $message }}</small>
                    @enderror
                </div>
            @endif

            <div class="form-group">
                <label for="driver_id">Driver <span class="field-optional">Optional</span></label>
                <select id="driver_id" name="driver_id" aria-describedby="driverAssignmentHelp">
                    <option value="">Assign later</option>
                    @foreach ($drivers as $driver)
                        @php
                            $truck = $driver->assignedTruck;
                            $driverReady = $truck && $truck->status !== 'maintenance';
                            $isCurrentDriver = $selectedDriverId === (string) $driver->id;
                            $driverSuffix = ! $truck
                                ? 'No truck assigned'
                                : ($truck->status === 'maintenance'
                                    ? $truck->plate_number . ' · maintenance'
                                    : $truck->plate_number);
                        @endphp
                        <option
                            value="{{ $driver->id }}"
                            {{ $isCurrentDriver ? 'selected' : '' }}
                            @disabled(! $driverReady && ! $isCurrentDriver)
                        >
                            {{ $driver->name }} · {{ $driverSuffix }}
                        </option>
                    @endforeach
                </select>
                <small class="field-help" id="driverAssignmentHelp">
                    Leave blank to keep the order in Pending status.
                </small>
                @error('driver_id')
                    <small class="error-text">{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group schedule-group" id="scheduleGroup">
                <label for="expected_delivery_at">Delivery date and time <span class="field-conditional" id="scheduleRequirement">Optional until a driver is assigned</span></label>
                <input
                    type="datetime-local"
                    id="expected_delivery_at"
                    name="expected_delivery_at"
                    value="{{ old('expected_delivery_at', $order?->expected_delivery_at?->format('Y-m-d\TH:i')) }}"
                    @unless ($isEditing) min="{{ now()->format('Y-m-d\TH:i') }}" @endunless
                >
                @error('expected_delivery_at')
                    <small class="error-text">{{ $message }}</small>
                @enderror
            </div>
        </div>
    </section>

    <section class="form-section-card order-step-card">
        <header class="section-header order-step-header">
            <span class="order-step-number" aria-hidden="true">3</span>
            <div class="order-step-copy">
                <h2>Delivery destination</h2>
                <p>Search for the address, then confirm the pin on the map.</p>
            </div>
        </header>

        @if (empty($googleMapsApiKey))
            <div class="warning-box">
                <i class="bi bi-exclamation-triangle-fill"></i>
                Location search is unavailable until Google Maps is configured.
            </div>
        @endif

        <div class="selected-location-summary">
            <span class="selected-location-icon"><i class="bi bi-geo-alt-fill"></i></span>
            <span class="selected-location-copy">
                <small>Selected destination</small>
                <strong id="selectedLocationText" aria-live="polite">
                    {{ $deliveryAddress ?: 'No location selected' }}
                </strong>
            </span>
        </div>

        <div class="map-search-row">
            <label class="search-input-wrap full" for="delivery_search">
                <i class="bi bi-search"></i>
                <input
                    type="search"
                    id="delivery_search"
                    value="{{ $deliveryAddress }}"
                    placeholder="Search for the delivery address"
                    autocomplete="street-address"
                >
            </label>
            <button type="button" class="map-search-button" onclick="searchDeliveryAddress()">
                Find address
            </button>
        </div>

        <div class="location-picker-card">
            <div class="location-picker-helper">
                <i class="bi bi-cursor-fill"></i>
                <span>Search above or click the map to place the delivery pin.</span>
            </div>
            <div id="deliveryMap"></div>
        </div>

        <input type="hidden" id="delivery_address" name="delivery_address" value="{{ $deliveryAddress }}">
        <input type="hidden" id="delivery_lat" name="delivery_lat" value="{{ $deliveryLat }}">
        <input type="hidden" id="delivery_lng" name="delivery_lng" value="{{ $deliveryLng }}">

        @error('delivery_address')
            <small class="error-text">{{ $message }}</small>
        @enderror
        @error('delivery_lat')
            <small class="error-text">{{ $message }}</small>
        @enderror
        @error('delivery_lng')
            <small class="error-text">{{ $message }}</small>
        @enderror
        <small id="deliveryLocationClientError" class="error-text" hidden>
            Choose a delivery destination before saving the order.
        </small>
    </section>

    <section class="form-section-card order-step-card">
        <header class="section-header order-step-header">
            <span class="order-step-number" aria-hidden="true">4</span>
            <div class="order-step-copy">
                <h2>Instructions</h2>
                <p>Add only the handling details the driver or receiver needs.</p>
            </div>
        </header>

        <div class="form-group">
            <label for="notes">Handling notes <span class="field-optional">Optional</span></label>
            <textarea
                id="notes"
                name="notes"
                rows="3"
                placeholder="For example: Keep frozen, call before arrival, or deliver before noon."
            >{{ old('notes', $order?->notes) }}</textarea>
            @error('notes')
                <small class="error-text">{{ $message }}</small>
            @enderror
        </div>
    </section>
</div>

<footer class="form-actions sticky-actions">
    <span class="form-action-note" id="adminOrderActionNote">
        <i class="bi bi-clock-history"></i>
        Without a driver, this order will stay pending.
    </span>
    <a href="{{ $order ? route('orders.show', $order) : route('orders.index') }}" class="secondary-button">
        Cancel
    </a>
    <button type="submit" class="primary-button" data-order-submit>
        <i class="bi bi-check2-circle"></i>
        {{ $buttonText }}
    </button>
</footer>

<template id="orderItemTemplate">
    <div class="order-item-row">
        <div class="form-group product-field">
            <label>Product</label>
            <select data-name="product_id" required>
                <option value="">Choose a product</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}">
                        {{ $product->name }} ({{ $product->min_temp }}°C–{{ $product->max_temp }}°C)
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group quantity-field">
            <label>Quantity</label>
            <input
                type="number"
                inputmode="decimal"
                step="0.01"
                min="0.01"
                data-name="quantity"
                value="1"
                required
            >
        </div>

        <div class="form-group unit-field">
            <label>Unit</label>
            <select data-name="unit" required>
                <option value="kg">kg</option>
                <option value="gram">gram</option>
            </select>
        </div>

        <button
            type="button"
            class="remove-product-button"
            onclick="removeOrderItem(this)"
            aria-label="Remove this item"
            title="Remove item"
        >
            <i class="bi bi-trash3"></i>
        </button>
    </div>
</template>

@push('scripts')
<script>
    let orderItemIndex = {{ count($oldItems) }};

    function updateOrderItemIndexes() {
        const rows = document.querySelectorAll('.order-item-row');

        rows.forEach(function (row, index) {
            row.querySelectorAll('[data-name]').forEach(function (input) {
                input.name = `items[${index}][${input.dataset.name}]`;
            });

            row.querySelectorAll('select[name], input[name]').forEach(function (input) {
                const match = input.name.match(/\[(product_id|quantity|unit)\]/);

                if (match) {
                    input.name = `items[${index}][${match[1]}]`;
                }
            });
        });

        orderItemIndex = rows.length;
    }

    function addOrderItem() {
        const template = document.getElementById('orderItemTemplate');
        const clone = template.content.cloneNode(true);

        document.getElementById('orderItemsList').appendChild(clone);
        updateOrderItemIndexes();
    }

    function removeOrderItem(button) {
        const rows = document.querySelectorAll('.order-item-row');

        if (rows.length <= 1) {
            alert('At least one product is required.');
            return;
        }

        button.closest('.order-item-row').remove();
        updateOrderItemIndexes();
    }

    let deliveryMap;
    let deliveryMarker;
    let deliveryGeocoder;
    let AdvancedMarkerElementClass;

    async function initOrderLocationPicker() {
        const mapElement = document.getElementById('deliveryMap');

        if (!mapElement || !window.google || !google.maps) {
            return;
        }

        const [{ Map }, { AdvancedMarkerElement }, { Geocoder }] = await Promise.all([
            google.maps.importLibrary('maps'),
            google.maps.importLibrary('marker'),
            google.maps.importLibrary('geocoding'),
        ]);

        AdvancedMarkerElementClass = AdvancedMarkerElement;
        deliveryGeocoder = new Geocoder();

        const defaultPosition = {
            lat: 14.5995,
            lng: 120.9842,
        };

        const existingLat = parseFloat(document.getElementById('delivery_lat').value);
        const existingLng = parseFloat(document.getElementById('delivery_lng').value);

        const hasExistingLocation = !isNaN(existingLat) && !isNaN(existingLng);

        const initialPosition = hasExistingLocation
            ? { lat: existingLat, lng: existingLng }
            : defaultPosition;

        deliveryMap = new Map(mapElement, {
            center: initialPosition,
            zoom: hasExistingLocation ? 16 : 12,
            gestureHandling: 'greedy',
            scrollwheel: true,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,
            mapId: 'DEMO_MAP_ID',
        });

        if (hasExistingLocation) {
            placeDeliveryMarker(initialPosition);
        }

        deliveryMap.addListener('click', function (event) {
            const position = {
                lat: event.latLng.lat(),
                lng: event.latLng.lng(),
            };

            setDeliveryLocation(position, true);
        });

    }

    function createDeliveryMarkerContent() {
        const marker = document.createElement('div');
        marker.className = 'delivery-marker';
        marker.innerHTML = '<i class="bi bi-geo-alt-fill"></i>';

        return marker;
    }

    function placeDeliveryMarker(position) {
        if (deliveryMarker) {
            deliveryMarker.position = position;
            return;
        }

        deliveryMarker = new AdvancedMarkerElementClass({
            map: deliveryMap,
            position: position,
            title: 'Delivery Location',
            content: createDeliveryMarkerContent(),
        });
    }

    function setDeliveryLocation(position, shouldReverseGeocode = false, address = null) {
        document.getElementById('delivery_lat').value = position.lat.toFixed(7);
        document.getElementById('delivery_lng').value = position.lng.toFixed(7);

        placeDeliveryMarker(position);
        deliveryMap.panTo(position);
        deliveryMap.setZoom(16);

        if (address) {
            updateDeliveryAddress(address);
            return;
        }

        if (shouldReverseGeocode) {
            reverseGeocodeDeliveryLocation(position);
        }
    }

    function updateDeliveryAddress(address) {
        document.getElementById('delivery_address').value = address;
        document.getElementById('delivery_search').value = address;
        document.getElementById('selectedLocationText').innerText = address;
        document.getElementById('deliveryLocationClientError')?.setAttribute('hidden', 'hidden');
    }

    function reverseGeocodeDeliveryLocation(position) {
        deliveryGeocoder.geocode({ location: position }, function (results, status) {
            if (status === 'OK' && results && results[0]) {
                updateDeliveryAddress(results[0].formatted_address);
            } else {
                const fallbackAddress = `${position.lat.toFixed(7)}, ${position.lng.toFixed(7)}`;
                updateDeliveryAddress(fallbackAddress);
            }
        });
    }

    function searchDeliveryAddress() {
        const searchInput = document.getElementById('delivery_search');
        const address = searchInput.value.trim();

        if (!address) {
            alert('Please enter a delivery location to search.');
            return;
        }

        if (!deliveryGeocoder) {
            alert('Map is still loading. Please try again.');
            return;
        }

        deliveryGeocoder.geocode({ address: address }, function (results, status) {
            if (status === 'OK' && results && results[0]) {
                const location = results[0].geometry.location;

                const position = {
                    lat: location.lat(),
                    lng: location.lng(),
                };

                setDeliveryLocation(position, false, results[0].formatted_address);
            } else {
                alert('Location not found. Please try another search term.');
            }
        });
    }

    function attachOrderFormValidation() {
        const form = document.querySelector('.admin-order-form');

        if (!form || form.dataset.orderValidationReady === '1') {
            return;
        }

        form.dataset.orderValidationReady = '1';

        form.addEventListener('submit', function (event) {
            const deliveryAddress = document.getElementById('delivery_address').value;
            const deliveryLat = document.getElementById('delivery_lat').value;
            const deliveryLng = document.getElementById('delivery_lng').value;
            const locationError = document.getElementById('deliveryLocationClientError');

            if (!deliveryAddress || !deliveryLat || !deliveryLng) {
                event.preventDefault();
                locationError?.removeAttribute('hidden');
                document.getElementById('delivery_search')?.focus();
                return;
            }

            const submitButton = form.querySelector('[data-order-submit]');

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="button-spinner" aria-hidden="true"></span>Saving…';
            }
        });
    }

    function syncDriverScheduleRequirement() {
        const driver = document.getElementById('driver_id');
        const schedule = document.getElementById('expected_delivery_at');
        const requirement = document.getElementById('scheduleRequirement');
        const actionNote = document.getElementById('adminOrderActionNote');
        const hasDriver = Boolean(driver?.value);

        if (schedule) {
            schedule.required = hasDriver;
        }

        if (requirement) {
            requirement.textContent = hasDriver ? 'Required for assignment' : 'Optional until a driver is assigned';
            requirement.classList.toggle('required', hasDriver);
        }

        if (actionNote) {
            actionNote.innerHTML = hasDriver
                ? '<i class="bi bi-truck-front-fill"></i>Saving will assign the driver and create a pending trip.'
                : '<i class="bi bi-clock-history"></i>Without a driver, this order will stay pending.';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        attachOrderFormValidation();
        syncDriverScheduleRequirement();

        document.getElementById('driver_id')?.addEventListener('change', syncDriverScheduleRequirement);
        document.getElementById('delivery_search')?.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                searchDeliveryAddress();
            }
        });
    });

    window.initOrderLocationPicker = initOrderLocationPicker;
</script>

@if (!empty($googleMapsApiKey))
    <script
        async
        defer
        src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsApiKey }}&callback=initOrderLocationPicker&loading=async"
    ></script>
@endif
@endpush
