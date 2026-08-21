@php
    $order = $order ?? null;
    $isEditing = $order !== null;

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

    $shouldSaveAddress = old('save_permanent_address', !$hasSavedAddress && !$isEditing);
@endphp

{{-- PRODUCTS --}}
<section class="form-section-card">
    <div class="section-header">
        <div class="section-icon">
            <i class="bi bi-basket"></i>
        </div>

        <div>
            <h2>Product Information</h2>
            <p>Choose the products you want to order. You may add more than one item.</p>
        </div>
    </div>

    <div class="order-items-card">
        <div class="order-items-header">
            <div>
                <strong>Selected Products</strong>
                <span>Units are limited to kilogram and gram.</span>
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
                            <option value="kg" {{ $selectedUnit === 'kg' ? 'selected' : '' }}>
                                Kilogram
                            </option>
                            <option value="gram" {{ $selectedUnit === 'gram' ? 'selected' : '' }}>
                                Gram
                            </option>
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
</section>

{{-- DELIVERY SCHEDULE --}}
<section class="form-section-card">
    <div class="section-header">
        <div class="section-icon">
            <i class="bi bi-calendar-check"></i>
        </div>

        <div>
            <h2>Delivery Schedule</h2>
            <p>Choose your preferred delivery date and time, if available.</p>
        </div>
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
</section>

{{-- DELIVERY LOCATION --}}
<section class="form-section-card delivery-location-section">
    <div class="section-header">
        <div class="section-icon">
            <i class="bi bi-geo-alt"></i>
        </div>

        <div>
            <h2>Delivery Location</h2>
            <p>
                Confirm where this order should be delivered. You can reuse your saved address
                or select a new location from the map.
            </p>
        </div>
    </div>

    @if (empty($googleMapsApiKey))
        <div class="warning-box">
            Google Maps API key is missing. Please add <strong>GOOGLE_MAPS_API_KEY</strong> to your <strong>.env</strong> file.
        </div>
    @endif

    <div class="delivery-location-layout">

        <div class="address-summary-card {{ $hasSavedAddress ? 'has-address' : 'no-address' }}">
            <div class="address-summary-icon">
                <i class="bi {{ $hasSavedAddress ? 'bi-house-check' : 'bi-house-add' }}"></i>
            </div>

            <div class="address-summary-content">
                <span>{{ $hasSavedAddress ? 'Saved delivery address' : 'No permanent address yet' }}</span>

                <strong>
                    {{ $hasSavedAddress ? $savedAddress : 'Choose your delivery location below.' }}
                </strong>

                <small>
                    {{ $hasSavedAddress
                        ? 'This saved address can be reused for faster ordering.'
                        : 'Select a location on the map, then save it as your permanent address for faster future orders.'
                    }}
                </small>
            </div>

            @if ($hasSavedAddress)
                <div class="address-summary-actions">
                    <button type="button" class="saved-address-button" onclick="useSavedAddress()">
                        Use Saved
                    </button>

                    <button type="button" class="change-address-button" onclick="startChoosingNewAddress()">
                        Change
                    </button>
                </div>
            @endif
        </div>

        <div class="selected-address-card">
            <div class="selected-address-top">
                <div>
                    <span>Selected for this order</span>
                    <strong id="selectedLocationText">
                        {{ $deliveryAddress ?: 'No delivery location selected yet.' }}
                    </strong>
                </div>

                <button type="button" class="clear-location-button" onclick="clearSelectedLocation()">
                    Clear
                </button>
            </div>

            <div class="address-mode-box" id="addressModeBox">
                <div class="address-mode-icon">
                    <i class="bi bi-info-circle"></i>
                </div>

                <div>
                    <strong id="addressModeTitle">
                        {{ $hasSavedAddress ? 'Using saved delivery address' : 'Set your first delivery address' }}
                    </strong>

                    <span id="addressModeText">
                        {{ $hasSavedAddress
                            ? 'You can use your saved address or choose a different location for this order.'
                            : 'Search or click the map to choose where your order should be delivered.'
                        }}
                    </span>
                </div>
            </div>

            <label class="save-address-option {{ !$hasSavedAddress ? 'recommended' : '' }}" id="saveAddressOption">
                <input
                    type="checkbox"
                    id="save_permanent_address"
                    name="save_permanent_address"
                    value="1"
                    {{ $shouldSaveAddress ? 'checked' : '' }}
                >

                <span>
                    <strong id="saveAddressLabel">
                        {{ $hasSavedAddress ? 'Update my permanent address to this location' : 'Save this as my permanent address' }}
                    </strong>

                    <small id="saveAddressHelp">
                        {{ $hasSavedAddress
                            ? 'Only check this if this should replace your saved delivery address.'
                            : 'Recommended: your next order will automatically use this location.'
                        }}
                    </small>
                </span>
            </label>
        </div>
    </div>

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
        <div class="location-picker-helper">
            <i class="bi bi-cursor"></i>
            <span>Tip: Search an address or click directly on the map to move the delivery pin.</span>
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
</section>

{{-- NOTES --}}
<section class="form-section-card">
    <div class="section-header">
        <div class="section-icon">
            <i class="bi bi-journal-text"></i>
        </div>

        <div>
            <h2>Handling Notes</h2>
            <p>Add instructions for delivery, receiving, or storage.</p>
        </div>
    </div>

    <div class="form-group">
        <label for="notes">Notes</label>

        <textarea
            id="notes"
            name="notes"
            rows="4"
            placeholder="Example: Deliver before noon, call before arrival, keep frozen..."
        >{{ old('notes', $order?->notes) }}</textarea>

        @error('notes')
            <small class="error-text">{{ $message }}</small>
        @enderror
    </div>
</section>

<div class="form-actions sticky-actions">
    <button type="submit" class="primary-button">
        {{ $buttonText }}
    </button>

    <a href="{{ $cancelRoute }}" class="secondary-button">
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

@push('styles')
<style>
    .customer-order-page,
    .customer-order-form {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .customer-order-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 24px;
        padding: 24px;
        border-radius: 24px;
        background:
            radial-gradient(circle at top left, rgba(34, 211, 238, 0.18), transparent 35%),
            linear-gradient(135deg, #ffffff, #f8fafc);
        border: 1px solid #e5e7eb;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.07);
    }

    .eyebrow {
        display: inline-flex;
        background: #ecfeff;
        color: #0891b2;
        border: 1px solid #cffafe;
        border-radius: 999px;
        padding: 7px 12px;
        font-size: 12px;
        font-weight: 800;
        margin-bottom: 12px;
    }

    .customer-order-header h1 {
        margin: 0;
        color: #0f172a;
        font-size: 34px;
        font-weight: 900;
        letter-spacing: -0.05em;
    }

    .customer-order-header p {
        margin: 8px 0 0;
        color: #64748b;
        line-height: 1.6;
        max-width: 720px;
    }

    .form-section-card,
    .sticky-actions {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.07);
    }

    .form-section-card {
        border-radius: 22px;
        padding: 22px;
    }

    .section-header {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 18px;
    }

    .section-icon {
        width: 46px;
        height: 46px;
        border-radius: 16px;
        background: linear-gradient(135deg, #2563eb, #06b6d4);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }

    .section-header h2 {
        margin: 0;
        color: #0f172a;
        font-size: 19px;
        font-weight: 900;
    }

    .section-header p {
        margin: 5px 0 0;
        color: #64748b;
        font-size: 13px;
        line-height: 1.5;
    }

    .flash-message,
    .warning-box {
        padding: 14px 16px;
        border-radius: 16px;
        font-size: 14px;
        font-weight: 800;
    }

    .flash-message.error {
        background: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }

    .warning-box {
        margin-bottom: 14px;
        background: #fffbeb;
        color: #92400e;
        border: 1px solid #fde68a;
    }

    .primary-button,
    .secondary-button,
    .add-product-button,
    .map-search-button,
    .saved-address-button,
    .change-address-button,
    .clear-location-button {
        border: none;
        text-decoration: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        transition: 0.2s ease;
    }

    .primary-button {
        min-height: 44px;
        padding: 0 18px;
        border-radius: 999px;
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 10px 20px rgba(37, 99, 235, 0.2);
    }

    .secondary-button {
        min-height: 44px;
        padding: 0 18px;
        border-radius: 999px;
        background: #f1f5f9;
        color: #475569;
    }

    .order-items-card {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        padding: 16px;
    }

    .order-items-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        margin-bottom: 14px;
    }

    .order-items-header strong {
        display: block;
        color: #0f172a;
        font-size: 15px;
        font-weight: 900;
    }

    .order-items-header span {
        display: block;
        color: #64748b;
        font-size: 12px;
        margin-top: 3px;
    }

    .order-item-row {
        display: grid;
        grid-template-columns: minmax(220px, 1fr) 150px 160px auto;
        gap: 12px;
        align-items: end;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        padding: 14px;
        margin-bottom: 12px;
    }

    .order-item-row:last-child {
        margin-bottom: 0;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .form-group label {
        color: #334155;
        font-size: 13px;
        font-weight: 900;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #0f172a;
        border-radius: 14px;
        padding: 12px 14px;
        outline: none;
        transition: 0.2s ease;
        resize: vertical;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus,
    .search-input-wrap input:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
    }

    .add-product-button {
        border-radius: 999px;
        padding: 10px 14px;
        background: #2563eb;
        color: #ffffff;
        font-size: 13px;
        gap: 7px;
    }

    .remove-product-button {
        width: 44px;
        height: 44px;
        border: none;
        border-radius: 14px;
        background: #fef2f2;
        color: #dc2626;
        cursor: pointer;
        font-size: 16px;
    }

    .delivery-location-layout {
        display: grid;
        grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
        gap: 16px;
        margin-bottom: 16px;
    }

    .address-summary-card,
    .selected-address-card {
        border-radius: 20px;
        border: 1px solid #e5e7eb;
        padding: 18px;
        min-width: 0;
    }

    .address-summary-card {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: 14px;
        align-items: flex-start;
        background: #f8fafc;
    }

    .address-summary-card.has-address {
        background:
            radial-gradient(circle at top left, rgba(37, 99, 235, 0.14), transparent 32%),
            linear-gradient(135deg, #eff6ff, #ecfeff);
        border-color: #bfdbfe;
    }

    .address-summary-card.no-address {
        border-style: dashed;
    }

    .address-summary-icon {
        width: 48px;
        height: 48px;
        border-radius: 16px;
        background: #ffffff;
        color: #2563eb;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
    }

    .address-summary-content span,
    .selected-address-top span {
        display: block;
        color: #2563eb;
        font-size: 12px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 6px;
    }

    .address-summary-content strong,
    .selected-address-top strong {
        display: block;
        color: #0f172a;
        font-size: 15px;
        font-weight: 900;
        line-height: 1.45;
        word-break: break-word;
    }

    .address-summary-content small {
        display: block;
        color: #64748b;
        font-size: 12px;
        margin-top: 6px;
        line-height: 1.5;
    }

    .address-summary-actions {
        grid-column: 1 / -1;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .saved-address-button,
    .change-address-button,
    .clear-location-button {
        min-height: 38px;
        padding: 0 14px;
        border-radius: 999px;
        font-size: 13px;
        white-space: nowrap;
    }

    .saved-address-button {
        background: #2563eb;
        color: #ffffff;
    }

    .change-address-button,
    .clear-location-button {
        background: #ffffff;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    .selected-address-card {
        background: #ffffff;
    }

    .selected-address-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 14px;
    }

    .address-mode-box {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px;
        border-radius: 16px;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        margin-bottom: 14px;
    }

    .address-mode-icon {
        width: 36px;
        height: 36px;
        border-radius: 12px;
        background: #eff6ff;
        color: #2563eb;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .address-mode-box strong {
        display: block;
        color: #0f172a;
        font-size: 14px;
        font-weight: 900;
        margin-bottom: 4px;
    }

    .address-mode-box span {
        display: block;
        color: #64748b;
        font-size: 13px;
        line-height: 1.5;
    }

    .address-mode-box.new-address {
        background: #fffbeb;
        border-color: #fde68a;
    }

    .save-address-option {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 15px;
        border-radius: 18px;
        border: 1px solid #e5e7eb;
        background: #ffffff;
        cursor: pointer;
    }

    .save-address-option input {
        width: 19px;
        height: 19px;
        margin-top: 2px;
        accent-color: #2563eb;
        flex-shrink: 0;
    }

    .save-address-option span {
        color: #0f172a;
        font-size: 14px;
        font-weight: 900;
    }

    .save-address-option small {
        display: block;
        margin-top: 4px;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        line-height: 1.5;
    }

    .save-address-option.recommended {
        border-color: #bfdbfe;
        background: #eff6ff;
    }

    .map-search-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 10px;
        margin-bottom: 14px;
    }

    .search-input-wrap {
        position: relative;
    }

    .search-input-wrap i {
        position: absolute;
        top: 50%;
        left: 13px;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 14px;
        pointer-events: none;
    }

    .search-input-wrap input {
        width: 100%;
        height: 44px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        border-radius: 999px;
        padding: 0 15px 0 38px;
        color: #0f172a;
        font-size: 14px;
        outline: none;
    }

    .map-search-button {
        min-height: 44px;
        border-radius: 14px;
        padding: 0 18px;
        background: #2563eb;
        color: #ffffff;
    }

    .location-picker-card {
        border: 1px solid #e5e7eb;
        border-radius: 20px;
        overflow: hidden;
        background: #ffffff;
    }

    .location-picker-helper {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 16px;
        border-bottom: 1px solid #e5e7eb;
        background: #f8fafc;
        color: #64748b;
        font-size: 13px;
        font-weight: 700;
    }

    .location-picker-helper i {
        color: #2563eb;
    }

    #deliveryMap {
        width: 100%;
        height: 430px;
    }

    .delivery-marker {
        width: 44px;
        height: 44px;
        border-radius: 999px;
        background: linear-gradient(135deg, #2563eb, #06b6d4);
        color: #ffffff;
        border: 3px solid #ffffff;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.28);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .error-text {
        color: #dc2626;
        font-size: 12px;
        font-weight: 800;
        margin-top: 8px;
        display: block;
    }

    .form-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .sticky-actions {
        border-radius: 18px;
        padding: 14px;
    }

    @media (max-width: 1100px) {
        .delivery-location-layout {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 900px) {
        .order-item-row,
        .map-search-row {
            grid-template-columns: 1fr;
        }

        .order-items-header,
        .customer-order-header,
        .selected-address-top {
            align-items: stretch;
            flex-direction: column;
        }

        .add-product-button,
        .map-search-button,
        .remove-product-button,
        .customer-order-header .secondary-button,
        .saved-address-button,
        .change-address-button,
        .clear-location-button {
            width: 100%;
        }

        .remove-product-button {
            height: 44px;
        }

        .address-summary-actions {
            flex-direction: column;
        }
    }

    @media (max-width: 760px) {
        .customer-order-header {
            padding: 20px;
        }

        .customer-order-header h1 {
            font-size: 28px;
        }

        .form-section-card {
            padding: 16px;
            border-radius: 20px;
        }

        #deliveryMap {
            height: 360px;
        }
    }
</style>
@endpush

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

        attachCustomerOrderFormValidation();

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

        if (!form) {
            return;
        }

        form.addEventListener('submit', function (event) {
            const address = document.getElementById('delivery_address').value;
            const lat = document.getElementById('delivery_lat').value;
            const lng = document.getElementById('delivery_lng').value;

            if (!address || !lat || !lng) {
                event.preventDefault();
                alert('Please select a delivery location on the map before submitting your order.');
            }
        });
    }
</script>

@if (!empty($googleMapsApiKey))
    <script
        async
        defer
        src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsApiKey }}&loading=async&callback=initCustomerOrderMap"
    ></script>
@endif
@endpush