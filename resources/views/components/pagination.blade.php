@props(['page'])

@if ($page->total > $page->perPage)
    <nav class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3" aria-label="Paginacja">
        <p class="service-meta mb-0">
            Pozycje {{ $page->from() }}–{{ $page->to() }} z {{ $page->total }}
        </p>
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item {{ $page->previousPage() === null ? 'disabled' : '' }}">
                @if ($page->previousPage() !== null)
                    <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $page->previousPage()]) }}"
                       rel="prev" data-async-link data-async-scroll>Poprzednia</a>
                @else
                    <span class="page-link">Poprzednia</span>
                @endif
            </li>
            <li class="page-item disabled">
                <span class="page-link">Strona {{ $page->page }} z {{ $page->lastPage() }}</span>
            </li>
            <li class="page-item {{ $page->nextPage() === null ? 'disabled' : '' }}">
                @if ($page->nextPage() !== null)
                    <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $page->nextPage()]) }}"
                       rel="next" data-async-link data-async-scroll>Następna</a>
                @else
                    <span class="page-link">Następna</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
