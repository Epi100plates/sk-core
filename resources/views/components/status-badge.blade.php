@props(['status' => null])

@if ($status instanceof \App\Domain\Enums\ServiceStatus)
    <span class="badge badge--status text-bg-{{ $status->color() }}">{{ $status->label() }}</span>
@else
    <span class="badge badge--status text-bg-secondary">—</span>
@endif
