@props(['status' => null])

@if ($status instanceof \App\Domain\Enums\ServiceStatus)
    <span class="badge text-bg-{{ $status->color() }}">{{ $status->label() }}</span>
@else
    <span class="badge text-bg-secondary">—</span>
@endif
