@props(['title', 'message' => null, 'actionLabel' => null, 'actionUrl' => null])

<div class="app-card">
    <div class="app-card-body text-center py-4">
        <p class="h6 mb-1">{{ $title }}</p>
        @if ($message !== null)
            <p class="service-meta mb-3">{{ $message }}</p>
        @endif
        @if ($actionLabel !== null && $actionUrl !== null)
            <a class="btn btn-sm btn-primary" href="{{ $actionUrl }}">{{ $actionLabel }}</a>
        @endif
    </div>
</div>
