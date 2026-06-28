@props(['paginator', 'perPage' => 20, 'perPageOptions' => [12, 20, 40, 60]])

@if ($paginator->total() > 0)
    <div class="flex flex-col sm:flex-row flex-wrap items-center justify-between gap-4 mt-8 pt-6 border-t border-[#c3c6d7]">
        <div class="flex flex-wrap items-center gap-4 text-sm text-[#737686]">
            <span>
                Showing <strong class="text-[#131b2e]">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</strong>
                of <strong class="text-[#131b2e]">{{ $paginator->total() }}</strong>
            </span>
            <form method="GET" class="flex items-center gap-2">
                @foreach(request()->except(['per_page', 'page']) as $key => $value)
                    @if(is_array($value))
                        @foreach($value as $v)
                            <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <label for="per_page" class="text-xs font-semibold uppercase tracking-wide">Per page</label>
                <select name="per_page" id="per_page" onchange="this.form.submit()" class="pp-input py-1.5 px-2 text-sm w-auto min-w-[4.5rem]">
                    @foreach($perPageOptions as $option)
                        <option value="{{ $option }}" @selected((int) $perPage === (int) $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        @if ($paginator->hasPages())
            <nav class="flex items-center gap-1" aria-label="Pagination">
                @if ($paginator->onFirstPage())
                    <span class="px-3 py-1.5 text-sm text-[#c3c6d7] border border-[#c3c6d7] rounded-lg cursor-not-allowed">Prev</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" class="px-3 py-1.5 text-sm text-[#434655] border border-[#c3c6d7] rounded-lg hover:bg-[#f2f3ff] transition-colors">Prev</a>
                @endif

                @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="px-3 py-1.5 text-sm font-semibold bg-[#004ac6] text-white border border-[#004ac6] rounded-lg">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="px-3 py-1.5 text-sm text-[#434655] border border-[#c3c6d7] rounded-lg hover:bg-[#f2f3ff] transition-colors">{{ $page }}</a>
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" class="px-3 py-1.5 text-sm text-[#434655] border border-[#c3c6d7] rounded-lg hover:bg-[#f2f3ff] transition-colors">Next</a>
                @else
                    <span class="px-3 py-1.5 text-sm text-[#c3c6d7] border border-[#c3c6d7] rounded-lg cursor-not-allowed">Next</span>
                @endif
            </nav>
        @endif
    </div>
@endif
