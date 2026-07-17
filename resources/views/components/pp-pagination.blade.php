@props([
    'paginator',
    'perPage' => 10,
    'perPageOptions' => [10, 20, 40, 50],
    'listTarget' => null, // CSS selector for smooth AJAX page navigation
    'pageName' => 'page',
])

@php
    $total = (int) $paginator->total();
    $hasPages = $paginator->hasPages();
    $hasMore = $paginator->hasMorePages();

    // Rebuild page URLs from current path so subdirectory installs (ngrok /pixflow/public) stay intact.
    $pageQuery = function (int $page) use ($pageName) {
        $query = request()->query();
        $query[$pageName] = $page;
        $qs = http_build_query($query);

        return url()->current().($qs !== '' ? '?'.$qs : '');
    };

    $nextUrl = $hasMore ? $pageQuery($paginator->currentPage() + 1) : null;
    $prevUrl = ! $paginator->onFirstPage() ? $pageQuery($paginator->currentPage() - 1) : null;
    $uid = 'pp-pag-'.substr(md5(($listTarget ?? 'list').$pageName.$paginator->currentPage()), 0, 8);
@endphp

@if ($total > 0)
    <div class="pp-pagination"
         data-pp-pagination="{{ $uid }}"
         data-pp-list-target="{{ $listTarget }}"
         data-current-page="{{ $paginator->currentPage() }}"
         data-last-page="{{ $paginator->lastPage() }}">

        <div class="pp-pagination-meta">
            <p class="pp-pagination-range" data-pp-pagination-range>
                <span class="pp-pagination-range-desktop" data-pp-range-desktop>
                    Showing
                    <strong>{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</strong>
                    of
                    <strong data-pp-range-total>{{ $total }}</strong>
                </span>
                <span class="pp-pagination-range-mobile" data-pp-range-mobile>
                    <strong data-pp-range-total-mobile>{{ $total }}</strong> records
                </span>
            </p>

            <form method="GET" class="pp-pagination-perpage" action="{{ url()->current() }}">
                @foreach(request()->except(['per_page', $pageName]) as $key => $value)
                    @if(is_array($value))
                        @foreach($value as $v)
                            <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <label for="{{ $uid }}-per-page" class="pp-pagination-perpage-label">Per page</label>
                <select name="per_page"
                        id="{{ $uid }}-per-page"
                        class="pp-input pp-pagination-select">
                    @foreach($perPageOptions as $option)
                        <option value="{{ $option }}" @selected((int) $perPage === (int) $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        @if ($hasPages)
            <nav class="pp-pagination-nav" aria-label="Pagination">
                @if ($paginator->onFirstPage())
                    <span class="pp-pagination-btn is-disabled" aria-disabled="true">
                        <span class="material-symbols-outlined">chevron_left</span>
                    </span>
                @else
                    <a href="{{ $prevUrl }}" class="pp-pagination-btn" aria-label="Previous page">
                        <span class="material-symbols-outlined">chevron_left</span>
                    </a>
                @endif

                <div class="pp-pagination-pages">
                    @for ($page = max(1, $paginator->currentPage() - 2); $page <= min($paginator->lastPage(), $paginator->currentPage() + 2); $page++)
                        @if ($page == $paginator->currentPage())
                            <span class="pp-pagination-page is-active" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $pageQuery($page) }}" class="pp-pagination-page">{{ $page }}</a>
                        @endif
                    @endfor
                </div>

                @if ($hasMore)
                    <a href="{{ $nextUrl }}" class="pp-pagination-btn" aria-label="Next page">
                        <span class="material-symbols-outlined">chevron_right</span>
                    </a>
                @else
                    <span class="pp-pagination-btn is-disabled" aria-disabled="true">
                        <span class="material-symbols-outlined">chevron_right</span>
                    </span>
                @endif
            </nav>
        @endif
    </div>
@endif
