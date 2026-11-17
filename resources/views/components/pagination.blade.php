@props(['paginator'])

@if ($paginator->total() > 0)
    <div class="hb-pager">
        <div class="small text-muted">
            Menampilkan {{ $paginator->firstItem() }}-{{ $paginator->lastItem() }} dari {{ $paginator->total() }} data
        </div>

        @if ($paginator->hasPages())
            @php
                $current = $paginator->currentPage();
                $last = $paginator->lastPage();
                $from = max(1, $current - 2);
                $to = min($last, $current + 2);
            @endphp
            <nav aria-label="Navigasi halaman">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ $paginator->previousPageUrl() ?? '#' }}" aria-label="Sebelumnya"><i class="bi bi-chevron-left"></i></a>
                    </li>
                    @if ($from > 1)
                        <li class="page-item"><a class="page-link" href="{{ $paginator->url(1) }}">1</a></li>
                        @if ($from > 2)<li class="page-item disabled"><span class="page-link">...</span></li>@endif
                    @endif
                    @for ($page = $from; $page <= $to; $page++)
                        <li class="page-item {{ $page === $current ? 'active' : '' }}">
                            <a class="page-link" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                        </li>
                    @endfor
                    @if ($to < $last)
                        @if ($to < $last - 1)<li class="page-item disabled"><span class="page-link">...</span></li>@endif
                        <li class="page-item"><a class="page-link" href="{{ $paginator->url($last) }}">{{ $last }}</a></li>
                    @endif
                    <li class="page-item {{ $paginator->hasMorePages() ? '' : 'disabled' }}">
                        <a class="page-link" href="{{ $paginator->nextPageUrl() ?? '#' }}" aria-label="Berikutnya"><i class="bi bi-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        @endif
    </div>
@endif
