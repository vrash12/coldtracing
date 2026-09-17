@props(['title' => 'This form could not be saved'])

@if ($errors->any())
    <div class="ct-error-summary" role="alert" tabindex="-1" id="formErrorSummary">
        <div class="ct-error-summary-head">
            <span><i class="bi bi-exclamation-triangle-fill"></i></span>
            <div>
                <strong>{{ $title }}</strong>
                <span>
                    {{ $errors->count() }}
                    {{ $errors->count() === 1 ? 'field needs attention' : 'fields need attention' }}.
                    Fix the items below and try again.
                </span>
            </div>
        </div>

        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>

    @push('scripts')
    <script>
        document.getElementById('formErrorSummary')?.focus();
    </script>
    @endpush
@endif
