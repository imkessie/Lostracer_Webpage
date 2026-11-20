@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; margin-top: 20px;">
        <div class="caption">
            Showing <strong>{{ $paginator->firstItem() }}</strong> to <strong>{{ $paginator->lastItem() }}</strong> of <strong>{{ $paginator->total() }}</strong> results
        </div>

        <div style="display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap;">
            @if ($paginator->onFirstPage())
                <span class="btn btn-outline btn-sm" style="opacity: 0.45; cursor: not-allowed;">&larr; Prev</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn btn-outline btn-sm">&larr; Prev</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="caption" style="padding: 0 6px;">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="btn btn-primary btn-sm" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="btn btn-outline btn-sm">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn btn-outline btn-sm">Next &rarr;</a>
            @else
                <span class="btn btn-outline btn-sm" style="opacity: 0.45; cursor: not-allowed;">Next &rarr;</span>
            @endif
        </div>
    </nav>
@endif
