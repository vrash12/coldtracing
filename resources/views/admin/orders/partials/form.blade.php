@php
    $order = $order ?? null;
    $oldItems = old('items');

    if (!$oldItems && $order && $order->orderItems->count()) {
        $oldItems = $order->orderItems->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit' => in_array($item->unit, ['kg', 'gram']) ? $item->unit : 'kg',
            ];
        })->toArray();
    }

    if (!$oldItems) {
        $oldItems = [
            [
                'product_id' => '',
                'quantity' => 1,
                'unit' => 'kg',
            ],
        ];
    }

    $deliveryAddress = old('delivery_address', $order?->delivery_address);
    $deliveryLat = old('delivery_lat', $order?->delivery_lat);
    $deliveryLng = old('delivery_lng', $order?->delivery_lng);
@endphp

<div class="order-form-wrapper">

    {{-- PRODUCT SECTION --}}
    <div class="form-section-card">
        <div class="section-header">
            <div class="section-icon">
                <i class="bi bi-basket"></i>
            </div>

            <div>
                <h2>Product Information</h2>
                <p>Select the meat products included in this order. You can add more than one item.</p>
            </div>
        </div>

        <div class="order-items-card">
            <div class="order-items-header">
                <div>
                    <strong>Selected Products</strong>
                    <span>Chicken, pork, and beef orders can be measured in kilogram or gram.</span>
                </div>

                <button type="button" class="add-product-button" onclick="addOrderItem()">
                    <i class="bi bi-plus-circle"></i>
                    Add Product
                </button>
            </div>

            <div id="orderItemsList">
                @foreach ($oldItems as $index => $item)
                    <div class="order-item-row">
                        <div class="form-group product-field">
                            <label>Product</label>

                            <select name="items[{{ $index }}][product_id]" required>
                                <option value="">Select product</option>

                                @foreach ($products as $product)
                                    <option
                                        value="{{ $product->id }}"
                                        {{ (string) ($item['product_id'] ?? '') === (string) $product->id ? 'selected' : '' }}
                                    >
                                        {{ $product->name }} | {{ $product->min_temp }}°C to {{ $product->max_temp }}°C
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group quantity-field">
                            <label>Quantity</label>

                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                name="items[{{ $index }}][quantity]"
                                value="{{ $item['quantity'] ?? 1 }}"
                                required
                            >
                        </div>

                        <div class="form-group unit-field">
                            <label>Unit</label>

                            @php
                                $selectedUnit = $item['unit'] ?? 'kg';

                                if (!in_array($selectedUnit, ['kg', 'gram'])) {
                                    $selectedUnit = 'kg';
                                }
                            @endphp

                            <select name="items[{{ $index }}][unit]" required>
                                <option value="kg" {{ $selectedUnit === 'kg' ? 'selected' : '' }}>Kilogram</option>
                                <option value="gram" {{ $selectedUnit === 'gram' ? 'selected' : '' }}>Gram</option>
                            </select>
                        </div>

                        <button type="button" class="remove-product-button" onclick="removeOrderItem(this)">
                            <i class="bi bi-trash"></i>
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
    </div>

    {{-- RECEIVER / DRIVER / SCHEDULE SECTION --}}
    <div class="form-section-card">
        <div class="section-header">
            <div class="section-icon">
                <i class="bi bi-person-check"></i>
            </div>

            <div>
                <h2>Receiver, Driver, and Schedule</h2>
                <p>Choose the customer, assign a driver, and set the expected delivery date if available.</p>
            </div>
        </div>

        <div class="form-grid compact-grid">
            <div class="form-group">
                <label for="receiver_id">Customer / Receiver</label>

                <select id="receiver_id" name="receiver_id">
                    <option value="">Select receiver</option>

                    @foreach ($receivers as $receiver)
                        <option
                            value="{{ $receiver->id }}"
                            {{ (string) old('receiver_id', $order?->receiver_id) === (string) $receiver->id ? 'selected' : '' }}
                        >
                            {{ $receiver->name }} - {{ $receiver->email }}
                        </option>
                    @endforeach
                </select>

                @error('receiver_id')
                    <small class="error-text">{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label for="driver_id">Assigned Driver</label>

                <select id="driver_id" name="driver_id">
                    <option value="">Select driver</option>

                    @foreach ($drivers as $driver)
                        <option
                            value="{{ $driver->id }}"
                            {{ (string) old('driver_id', $order?->driver_id) === (string) $driver->id ? 'selected' : '' }}
                        >
                            {{ $driver->name }} - {{ $driver->email }}
                        </option>
                    @endforeach
                </select>

                @error('driver_id')
                    <small class="error-text">{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label for="expected_delivery_at">Expected Delivery Date</label>

                <input
                    type="datetime-local"
                    id="expected_delivery_at"
                    name="expected_delivery_at"
                    value="{{ old('expected_delivery_at', $order?->expected_delivery_at?->format('Y-m-d\TH:i')) }}"
                >

                @error('expected_delivery_at')
                    <small class="error-text">{{ $message }}</small>
                @enderror
            </div>
        </div>
    </div>

    {{-- DELIVERY MAP SECTION --}}
    <div class="form-section-card">
        <div class="section-header">
            <div class="section-icon">
                <i class="bi bi-geo-alt"></i>
            </div>

            <div>
                <h2>Delivery Location</h2>
                <p>Search the destination or click the map to select the exact delivery point.</p>
            </div>
        </div>

        @if (empty($googleMapsApiKey))
            <div class="warning-box">
                Google Maps API key is missing. Please add <strong>GOOGLE_MAPS_API_KEY</strong> to your <strong>.env</strong> file.
            </div>
        @endif

        <div class="map-search-row">
            <div class="search-input-wrap full">
                <i class="bi bi-search"></i>
                <input
                    type="text"
                    id="delivery_search"
                    value="{{ $deliveryAddress }}"
                    placeholder="Search delivery location..."
                >
            </div>

            <button type="button" class="map-search-button" onclick="searchDeliveryAddress()">
                Search
            </button>
        </div>

        <div class="location-picker-card">
            <div class="location-picker-header">
                <div>
                    <strong>Map Location Picker</strong>
                    <span id="selectedLocationText">
                        {{ $deliveryAddress ?: 'No delivery location selected yet.' }}
                    </span>
                </div>
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
    </div>

    {{-- NOTES SECTION --}}
    <div class="form-section-card">
        <div class="section-header">
            <div class="section-icon">
                <i class="bi bi-journal-text"></i>
            </div>

            <div>
                <h2>Handling Notes</h2>
                <p>Add special instructions for storage, loading, or delivery.</p>
            </div>
        </div>

        <div class="form-group">
            <label for="notes">Notes</label>

            <textarea
                id="notes"
                name="notes"
                rows="4"
                placeholder="Example: Keep frozen, deliver before noon, handle carefully..."
            >{{ old('notes', $order?->notes) }}</textarea>

            @error('notes')
                <small class="error-text">{{ $message }}</small>
            @enderror
        </div>
    </div>

</div>

<div class="form-actions sticky-actions">
    <button type="submit" class="primary-button">
        {{ $buttonText }}
    </button>

    <a href="{{ route('orders.index') }}" class="secondary-button">
        Cancel
    </a>
</div>

<template id="orderItemTemplate">
    <div class="order-item-row">
        <div class="form-group product-field">
            <label>Product</label>

            <select data-name="product_id" required>
                <option value="">Select product</option>

                @foreach ($products as $product)
                    <option value="{{ $product->id }}">
                        {{ $product->name }} | {{ $product->min_temp }}°C to {{ $product->max_temp }}°C
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group quantity-field">
            <label>Quantity</label>

            <input
                type="number"
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
                <option value="kg">Kilogram</option>
                <option value="gram">Gram</option>
            </select>
        </div>

        <button type="button" class="remove-product-button" onclick="removeOrderItem(this)">
            <i class="bi bi-trash"></i>
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

        attachOrderFormValidation();
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
        const form = document.querySelector('form');

        if (!form) {
            return;
        }

        form.addEventListener('submit', function (event) {
            const deliveryAddress = document.getElementById('delivery_address').value;
            const deliveryLat = document.getElementById('delivery_lat').value;
            const deliveryLng = document.getElementById('delivery_lng').value;

            if (!deliveryAddress || !deliveryLat || !deliveryLng) {
                event.preventDefault();
                alert('Please select a delivery location on the map before saving the order.');
            }
        });
    }

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