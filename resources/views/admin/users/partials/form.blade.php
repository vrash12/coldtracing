@php
    $selectedRoleId = (string) old('role_id', $user?->role_id);
    $selectedStatus = (string) old('status', $user?->status ?? 'active');
@endphp

<div class="form-grid">
    <div class="form-group">
        <label for="name">Full name</label>
        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name', $user?->name) }}"
            maxlength="255"
            autocomplete="name"
            required
        >
        @error('name')
            <small class="error-text">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-group">
        <label for="email">Email address</label>
        <input
            type="email"
            id="email"
            name="email"
            value="{{ old('email', $user?->email) }}"
            maxlength="255"
            autocomplete="email"
            required
        >
        @error('email')
            <small class="error-text">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-group">
        <label for="role_id">System role</label>
        <select id="role_id" name="role_id" required>
            <option value="">Select a role</option>
            @foreach ($roles as $role)
                <option
                    value="{{ $role->id }}"
                    {{ $selectedRoleId === (string) $role->id ? 'selected' : '' }}
                >
                    {{ $role->name }}
                </option>
            @endforeach
        </select>
        @error('role_id')
            <small class="error-text">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-group">
        <label for="phone">Phone number <span class="optional-label">Optional</span></label>
        <input
            type="text"
            id="phone"
            name="phone"
            value="{{ old('phone', $user?->phone) }}"
            maxlength="255"
            autocomplete="tel"
        >
        @error('phone')
            <small class="error-text">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-group">
        <label for="status">Account status</label>
        <select id="status" name="status" required>
            <option value="active" {{ $selectedStatus === 'active' ? 'selected' : '' }}>
                Active
            </option>
            <option value="inactive" {{ $selectedStatus === 'inactive' ? 'selected' : '' }}>
                Inactive
            </option>
        </select>
        @error('status')
            <small class="error-text">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-group note-box">
        <strong>Only administrators and drivers can sign in.</strong>
        <span>
            An inactive account keeps all of its records but is refused at the login screen,
            so suspend an account here instead of deleting it.
        </span>
    </div>

    <div class="form-group">
        <label for="password">
            Password
            @if ($isEdit)
                <span class="optional-label">Leave blank to keep current</span>
            @endif
        </label>
        <input
            type="password"
            id="password"
            name="password"
            minlength="6"
            autocomplete="new-password"
            {{ $isEdit ? '' : 'required' }}
        >
        @error('password')
            <small class="error-text">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-group">
        <label for="password_confirmation">Confirm password</label>
        <input
            type="password"
            id="password_confirmation"
            name="password_confirmation"
            minlength="6"
            autocomplete="new-password"
            {{ $isEdit ? '' : 'required' }}
        >
    </div>
</div>

<div class="form-actions">
    <button type="submit" class="primary-button">{{ $buttonText }}</button>
    <a href="{{ route('users.index') }}" class="secondary-button">Cancel</a>
</div>
