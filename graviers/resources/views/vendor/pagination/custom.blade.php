@if ($paginator->hasPages())
<style>
    .pagination-premium {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        flex-wrap: wrap;
    }
    .pagination-premium .page-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 40px;
        height: 40px;
        padding: 0 12px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.25s ease;
        border: 2px solid #e2e8f0;
        background: #fff;
        color: #475569;
        cursor: pointer;
    }
    .pagination-premium .page-btn:hover {
        background: #3bb77e;
        border-color: #3bb77e;
        color: #000;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 183, 126, 0.35);
    }
    .pagination-premium .page-btn.active {
        background: linear-gradient(135deg, #3bb77e, #29a56c);
        border-color: #3bb77e;
        color: #fff;
        box-shadow: 0 4px 14px rgba(59, 183, 126, 0.4);
    }
    .pagination-premium .page-btn.disabled {
        opacity: 0.4;
        cursor: not-allowed;
        pointer-events: none;
    }
    .pagination-premium .page-btn.nav-btn {
        padding: 0 16px;
        font-weight: 700;
        background: #f8fafc;
    }
    .pagination-premium .page-dots {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 40px;
        height: 40px;
        font-size: 14px;
        color: #94a3b8;
        font-weight: 700;
        letter-spacing: 2px;
    }
    .pagination-premium .page-info {
        font-size: 13px;
        color: #64748b;
        margin-right: 15px;
    }
</style>

<div class="d-flex flex-column flex-sm-row align-items-center justify-content-center gap-3">
    <span class="pagination-premium page-info">
        Affichage de {{ $paginator->firstItem() }} à {{ $paginator->lastItem() }} sur {{ $paginator->total() }} produits
    </span>

    <nav class="pagination-premium">
        {{-- Bouton Précédent --}}
        @if ($paginator->onFirstPage())
            <span class="page-btn nav-btn disabled"><i class="fi-rs-angle-left"></i> Précédent</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="page-btn nav-btn" rel="prev"><i class="fi-rs-angle-left"></i> Précédent</a>
        @endif

        {{-- Numéros de pages --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="page-dots">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="page-btn active">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="page-btn">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Bouton Suivant --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="page-btn nav-btn" rel="next">Suivant <i class="fi-rs-angle-right"></i></a>
        @else
            <span class="page-btn nav-btn disabled">Suivant <i class="fi-rs-angle-right"></i></span>
        @endif
    </nav>
</div>
@endif
