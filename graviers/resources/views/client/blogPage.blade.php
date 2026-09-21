@php
    use Illuminate\Support\Str;
@endphp

@extends('client.main')
@section('title','Blog')
@section('content')

<style>
    /* Lot 109 (17/09/2026) : le blog en cartes, trois par page. */
    .blog-entete { display:flex; align-items:flex-end; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:28px; }
    .blog-entete h1 { font-size:2rem; font-weight:800; color:#0A2540; margin:0; }
    .blog-entete h1::after { content:""; display:block; width:64px; height:4px; border-radius:2px; background:#FFB300; margin-top:10px; }
    .blog-entete p { margin:0; color:#6b7a90; }
    .blog-grille { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:24px; }
    @media (max-width: 991px) { .blog-grille { grid-template-columns:repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 575px) { .blog-grille { grid-template-columns:1fr; } }
    .blog-carte { background:#fff; border:1px solid #e9eef5; border-radius:16px; overflow:hidden; display:flex; flex-direction:column;
        transition:transform .2s ease, box-shadow .2s ease; }
    .blog-carte:hover { transform:translateY(-4px); box-shadow:0 14px 30px rgba(10,37,64,.10); }
    .blog-carte__visuel { position:relative; aspect-ratio:16/10; background:#eef2f7 center/cover no-repeat; display:block; }
    .blog-carte__visuel .blog-carte__vide { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:#9fb0c6; font-size:40px; }
    .blog-carte__date { position:absolute; left:14px; top:14px; background:#0A2540; color:#fff; font-size:12px; font-weight:700;
        padding:5px 10px; border-radius:20px; letter-spacing:.2px; }
    .blog-carte__corps { padding:18px 20px 20px; display:flex; flex-direction:column; flex:1 1 auto; }
    .blog-carte__titre { font-size:1.15rem; font-weight:800; line-height:1.3; margin:0 0 10px; }
    .blog-carte__titre a { color:#0A2540; }
    .blog-carte__titre a:hover { color:#1C57A3; }
    .blog-carte__extrait { color:#56637a; font-size:14.5px; line-height:1.6; margin:0 0 16px; flex:1 1 auto; }
    .blog-carte__pied { display:flex; align-items:center; justify-content:space-between; gap:10px; font-size:12.5px; color:#8794ab; }
    .blog-carte__pied a { color:#1C57A3; font-weight:700; white-space:nowrap; }
    .blog-carte__pied a i { font-size:12px; margin-left:4px; }
    .blog-vide { text-align:center; padding:60px 20px; background:#f7f9fc; border-radius:16px; }
    .blog-pagination .pagination { justify-content:center; gap:6px; }
    .blog-pagination .page-link { border-radius:50% !important; width:40px; height:40px; display:flex; align-items:center; justify-content:center; padding:0; }
    .blog-pagination .page-item.active .page-link { background:#1C57A3; border-color:#1C57A3; color:#fff; }
</style>

    <main class="main">

        @if (session('fail'))
            <div class="container mt-30">
                <div class="alert alert-warning text-center">{{ session('fail') }}</div>
            </div>
        @endif

        <div class="page-content mb-50 mt-30">
            <div class="container">
                <div class="row">
                    <div class="col-lg-9">
                        <div class="blog-entete pr-lg-4">
                            <h1>Blog &amp; actualités</h1>
                            <p>{{ $blogs->total() }} article{{ $blogs->total() > 1 ? 's' : '' }} publié{{ $blogs->total() > 1 ? 's' : '' }}</p>
                        </div>

                        @if ($blogs->count())
                            <div class="blog-grille pr-lg-4 mb-40">
                                @foreach ($blogs as $blog)
                                    @php
                                        // L'extrait : sans balises ni marques de mise en forme (« ## », « ** »), coupé proprement.
                                        $texte = trim(preg_replace('/\s+/', ' ', preg_replace('/[#*_>`]+/', '', strip_tags((string) $blog->description))));
                                    @endphp
                                    <article class="blog-carte">
                                        <a class="blog-carte__visuel" href="{{ route('client.detailBlog', $blog->id) }}" aria-label="{{ $blog->titre }}"
                                           @if($blog->image) style="background-image:url('{{ asset('storage/'.$blog->image) }}')" @endif>
                                            @unless($blog->image)
                                                <span class="blog-carte__vide"><i class="fi-rs-document"></i></span>
                                            @endunless
                                            <span class="blog-carte__date">{{ $blog->created_at?->isoFormat('D MMM YYYY') }}</span>
                                        </a>
                                        <div class="blog-carte__corps">
                                            <h2 class="blog-carte__titre"><a href="{{ route('client.detailBlog', $blog->id) }}">{{ $blog->titre }}</a></h2>
                                            <p class="blog-carte__extrait">{{ Str::limit($texte, 140) }}</p>
                                            <div class="blog-carte__pied">
                                                <span>{{ (int) $blog->vu }} vue{{ (int) $blog->vu > 1 ? 's' : '' }} · {{ $blog->nb_commentaires }} commentaire{{ $blog->nb_commentaires > 1 ? 's' : '' }}</span>
                                                <a href="{{ route('client.detailBlog', $blog->id) }}">Lire l'article <i class="fi-rs-arrow-small-right"></i></a>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <div class="blog-vide pr-lg-4">
                                <h4 class="mb-10">Aucun article pour le moment</h4>
                                <p class="text-muted mb-0">Revenez bientôt : de nouvelles publications arrivent régulièrement.</p>
                            </div>
                        @endif

                        @if ($blogs->hasPages())
                            <div class="blog-pagination pr-lg-4 mb-30">
                                <nav aria-label="Navigation des articles">
                                    <ul class="pagination">
                                        <li class="page-item {{ $blogs->onFirstPage() ? 'disabled' : '' }}">
                                            <a class="page-link" href="{{ $blogs->previousPageUrl() ?? '#' }}" aria-label="Page précédente"><i class="fi-rs-arrow-small-left"></i></a>
                                        </li>
                                        @foreach ($blogs->getUrlRange(1, $blogs->lastPage()) as $page => $url)
                                            <li class="page-item {{ $page == $blogs->currentPage() ? 'active' : '' }}">
                                                <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                                            </li>
                                        @endforeach
                                        <li class="page-item {{ $blogs->hasMorePages() ? '' : 'disabled' }}">
                                            <a class="page-link" href="{{ $blogs->nextPageUrl() ?? '#' }}" aria-label="Page suivante"><i class="fi-rs-arrow-small-right"></i></a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        @endif
                    </div>

                    @include('client.categorieEtFiltreur')

                </div>
            </div>
        </div>

    </main>

@endsection
