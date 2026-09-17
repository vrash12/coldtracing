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

    $savedAddress = $customer->permanent_delivery_address;
    $savedLat = $customer->permanent_delivery_lat;
    $savedLng = $customer->permanent_delivery_lng;
    $hasSavedAddress = $savedAddress && $savedLat !== null && $savedLng !== null;

    $deliveryAddress = old(
        'delivery_address',
        $order?->delivery_address ?: ($hasSavedAddress ? $savedAddress : '')
    );
    $deliveryLat = old(
        'delivery_lat',
        $order?->delivery_lat ?: ($hasSavedAddress ? $savedLat : '')
    );
    $deliveryLng = old(
        'delivery_lng',
        $order?->delivery_lng ?: ($hasSavedAddress ? $savedLng : '')
    );

    $shouldSaveAddress = old('save_permanent_address', ! $hasSavedAddress && ! $isEditing);
@endphp

<section class="form-section-card order-step-card">
    <header class="section-header order-step-header">
        <span class="order-step-number" aria-hidden="true">1</span>
        <div class="order-step-copy">
            <h2>What are we delivering?</h2>
            <p>Select an item and enter its quantity. Add another row only when needed.</p>
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
                        <label for="item_product_{{ $index }}">Product</label>
                        <select id="item_product_{{ $index }}" name="items[{{ $index }}][product_id]" required>
                            <option value="">Choose a product</option>
                            @foreach ($products as $product)
                                <option
                                    value="{{ $product->id }}"
                                    {{ (string) ($item['product_id'] ?? '') === (string) $product->id ? 'selected' : '' }}
                                >
                                    {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group quantity-field">
                        <label for="item_quantity_{{ $index }}">Quantity</label>
                        <input
                            id="item_quantity_{{ $index }}"
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
                        <label for="item_unit_{{ $index }}">Unit</label>
                        <select id="item_unit_{{ $index }}" name="items[{{ $index }}][unit]" required>
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

<section class="form-section-card order-step-card delivery-location-section">
    <header class="section-header order-step-header">
        <span class="order-step-number" aria-hidden="true">2</span>
        <div class="order-step-copy">
            <h2>Where and when?</h2>
            <p>Choose the destination. A preferred delivery time is optional.</p>
        </div>
    </header>

    <div class="delivery-basics-grid">
        <div class="form-group">
            <label for="expected_delivery_at">Preferred date and time <span class="field-optional">Optional</span></label>
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

        <div class="selected-location-summary">
            <span class="selected-location-icon"><i class="bi bi-geo-alt-fill"></i></span>
            <span class="selected-location-copy">
                <small>Delivery destination</small>
                <strong id="selectedLocationText" aria-live="polite">
                    {{ $deliveryAddress ?: 'No location selected' }}
                </strong>
            </span>
            <button
                type="button"
                id="clearLocationButton"
                class="clear-location-button"
                onclick="clearSelectedLocation()"
                {{ $deliveryAddress ? '' : 'hidden' }}
            >
                Clear
            </button>
        </div>
    </div>

    @if ($hasSavedAddress)
        <div class="saved-address-strip">
            <span class="saved-address-icon"><i class="bi bi-house-check-fill"></i></span>
            <span class="saved-address-copy">
                <small>Saved address</small>
                <strong>{{ $savedAddress }}</strong>
            </span>
            <button type="button" class="saved-address-button" onclick="useSavedAddress()">
                Use saved address
            </button>
            <button type="button" class="change-address-button" onclick="startChoosingNewAddress()">
                Choose another
            </button>
        </div>
    @endif

    @if (empty($googleMapsApiKey))
        <div class="warning-box">
            <i class="bi bi-exclamation-triangle-fill"></i>
            Location search is unavailable. Ask the administrator to configure Google Maps.
        </div>
    @endif

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
            <span>Search above or tap the map to place the delivery pin.</span>
        </div>
        <div id="deliveryMap"></div>
    </div>

    <label class="save-address-option {{ ! $hasSavedAddress ? 'recommended' : '' }}" id="saveAddressOption">
        <input
            type="checkbox"
            id="save_permanent_address"
            name="save_permanent_address"
            value="1"
            {{ $shouldSaveAddress ? 'checked' : '' }}
        >
        <span>
            <strong id="saveAddressLabel">
                {{ $hasSavedAddress ? 'Make this my new saved address' : 'Save this address for next time' }}
            </strong>
            <small id="saveAddressHelp">
                {{ $hasSavedAddress ? 'Leave unchecked for a one-time destination.' : 'You can change it on a future order.' }}
            </small>
        </span>
    </label>

    <div id="addressModeBox" hidden>
        <strong id="addressModeTitle"></strong>
        <span id="addressModeText"></span>
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
        Choose a delivery address before submitting the request.
    </small>
</section>

<section class="form-section-card order-step-card">
    <header class="section-header order-step-header">
        <span class="order-step-number" aria-hidden="true">3</span>
        <div class="order-step-copy">
            <h2>Anything else?</h2>
            <p>Add a short instruction only if the delivery team needs it.</p>
        </div>
    </header>

    <div class="form-group">
        <label for="notes">Delivery instructions <span class="field-optional">Optional</span></label>
        <textarea
            id="notes"
            name="notes"
            rows="3"
            placeholder="For example: Call before arrival or keep frozen."
        >{{ old('notes', $order?->notes) }}</textarea>
        @error('notes')
            <small class="error-text">{{ $message }}</small>
        @enderror
    </div>
</section>

<footer class="form-actions sticky-actions">
    <span class="form-action-note">
        <i class="bi bi-shield-check"></i>
        You can edit or cancel the request while it is still pending.
    </span>
    <a href="{{ $cancelRoute }}" class="secondary-button">Cancel</a>
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
                    <option value="{{ $product->id }}">{{ $product->name }}</option>
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
    const savedAddress = @json($savedAddress);
    const savedLat = @json($savedLat);
    const savedLng = @json($savedLng);
    const hasSavedAddress = @json($hasSavedAddress);

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

    async function initCustomerOrderMap() {
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
            setNewAddressMode();
        });

        if (hasExistingLocation && hasSavedAddress) {
            setSavedAddressMode();
        }
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

        if (deliveryMap) {
            deliveryMap.panTo(position);
            deliveryMap.setZoom(16);
        }

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
        document.getElementById('clearLocationButton')?.removeAttribute('hidden');
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
                setNewAddressMode();
            } else {
                alert('Location not found. Please try a more specific address.');
            }
        });
    }

    function useSavedAddress() {
        if (!savedAddress || savedLat === null || savedLng === null) {
            alert('No saved address found.');
            return;
        }

        const position = {
            lat: parseFloat(savedLat),
            lng: parseFloat(savedLng),
        };

        if (isNaN(position.lat) || isNaN(position.lng)) {
            alert('Saved address coordinates are invalid.');
            return;
        }

        setDeliveryLocation(position, false, savedAddress);
        setSavedAddressMode();
    }

    function startChoosingNewAddress() {
        setNewAddressMode();

        const searchInput = document.getElementById('delivery_search');

        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }

    function clearSelectedLocation() {
        document.getElementById('delivery_address').value = '';
        document.getElementById('delivery_lat').value = '';
        document.getElementById('delivery_lng').value = '';
        document.getElementById('delivery_search').value = '';
        document.getElementById('selectedLocationText').innerText = 'No delivery location selected yet.';
        document.getElementById('clearLocationButton')?.setAttribute('hidden', 'hidden');

        if (deliveryMarker) {
            deliveryMarker.map = null;
            deliveryMarker = null;
        }

        setNewAddressMode();
    }

    function setSavedAddressMode() {
        const modeBox = document.getElementById('addressModeBox');
        const title = document.getElementById('addressModeTitle');
        const text = document.getElementById('addressModeText');
        const saveCheckbox = document.getElementById('save_permanent_address');
        const saveOption = document.getElementById('saveAddressOption');
        const saveLabel = document.getElementById('saveAddressLabel');
        const saveHelp = document.getElementById('saveAddressHelp');

        if (modeBox) {
            modeBox.classList.remove('new-address');
        }

        if (title) {
            title.innerText = 'Using saved delivery address';
        }

        if (text) {
            text.innerText = 'This order will be delivered to your saved address. Choose a new location only if this order should go somewhere else.';
        }

        if (saveCheckbox) {
            saveCheckbox.checked = false;
        }

        if (saveOption) {
            saveOption.classList.remove('recommended');
        }

        if (saveLabel) {
            saveLabel.innerText = 'Update my permanent address to this location';
        }

        if (saveHelp) {
            saveHelp.innerText = 'Leave unchecked if you only want to use your current saved address.';
        }
    }

    function setNewAddressMode() {
        const modeBox = document.getElementById('addressModeBox');
        const title = document.getElementById('addressModeTitle');
        const text = document.getElementById('addressModeText');
        const saveCheckbox = document.getElementById('save_permanent_address');
        const saveOption = document.getElementById('saveAddressOption');
        const saveLabel = document.getElementById('saveAddressLabel');
        const saveHelp = document.getElementById('saveAddressHelp');

        if (modeBox) {
            modeBox.classList.add('new-address');
        }

        if (title) {
            title.innerText = hasSavedAddress ? 'Using a different delivery address' : 'New delivery address selected';
        }

        if (text) {
            text.innerText = hasSavedAddress
                ? 'This order will use the new selected location. Check the save option below only if this is your new regular delivery address.'
                : 'This selected location will be used for your order. Saving it is recommended for faster future orders.';
        }

        if (saveOption) {
            saveOption.classList.add('recommended');
        }

        if (saveLabel) {
            saveLabel.innerText = hasSavedAddress
                ? 'Update my permanent address to this new location'
                : 'Save this as my permanent address';
        }

        if (saveHelp) {
            saveHelp.innerText = hasSavedAddress
                ? 'Recommended only if this should replace your current saved address.'
                : 'Recommended: your next order will automatically use this location.';
        }

        if (saveCheckbox && !hasSavedAddress) {
            saveCheckbox.checked = true;
        }
    }

    function attachCustomerOrderFormValidation() {
        const form = document.querySelector('.customer-order-form');

        if (!form || form.dataset.orderValidationReady === '1') {
            return;
        }

        form.dataset.orderValidationReady = '1';

        form.addEventListener('submit', function (event) {
            const address = document.getElementById('delivery_address').value;
            const lat = document.getElementById('delivery_lat').value;
            const lng = document.getElementById('delivery_lng').value;
            const locationError = document.getElementById('deliveryLocationClientError');

            if (!address || !lat || !lng) {
                event.preventDefault();
                locationError?.removeAttribute('hidden');
                document.getElementById('delivery_search')?.focus();
                return;
            }

            const submitButton = form.querySelector('[data-order-submit]');

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="button-spinner" aria-hidden="true"></span>Submitting…';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        attachCustomerOrderFormValidation();

        document.getElementById('delivery_search')?.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                searchDeliveryAddress();
            }
        });
    });
</script>

@if (!empty($googleMapsApiKey))
    <script
        async
        defer
        src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsApiKey }}&loading=async&callback=initCustomerOrderMap"
    ></script>
@endif
@endpush
