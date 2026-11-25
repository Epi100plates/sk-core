@props(['page', 'baseUrl' => null, 'query' => null])

@php
    // `query` is passed when the list is rendered without the page shell (the
    // live search fragment): its own URL is the fragment endpoint, so links must
    // point back at the queue, not at the partial.
    $pageUrl = static function (int $number) use ($baseUrl, $query): string {
        if ($query === null) {
            return request()->fullUrlWithQuery(['page' => $number]);
        }

        $base = $baseUrl ?? url()->current();

        return $base.'?'.http_build_query(array_merge($query, ['page' => $number]));
    };
@endphp

@if ($page->total > $page->perPage)
    <nav class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3" aria-label="Paginacja">
        <p class="service-meta mb-0">
            Pozycje {{ $page->from() }}–{{ $page->to() }} z {{ $page->total }}
        </p>
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item {{ $page->previousPage() === null ? 'disabled' : '' }}">
                @if ($page->previousPage() !== null)
                    <a class="page-link" href="{{ $pageUrl($page->previousPage()) }}" rel="prev">Poprzednia</a>
                @else
                    <span class="page-link">Poprzednia</span>
                @endif
            </li>
            <li class="page-item disabled">
                <span class="page-link">Strona {{ $page->page }} z {{ $page->lastPage() }}</span>
            </li>
            <li class="page-item {{ $page->nextPage() === null ? 'disabled' : '' }}">
                @if ($page->nextPage() !== null)
                    <a class="page-link" href="{{ $pageUrl($page->nextPage()) }}" rel="next">Następna</a>
                @else
                    <span class="page-link">Następna</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
