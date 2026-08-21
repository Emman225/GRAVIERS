@php
    use Illuminate\Support\Str;
@endphp


@extends('client.main')
@section('title','Blog')
@section('content')


    <main class="main">

        @if (session('fail'))
            <div class="container mt-30">
                <div class="alert alert-warning text-center">{{ session('fail') }}</div>
            </div>
        @endif

        <div class="page-content mb-50">
            <div class="container">
                <div class="row">
                    <div class="col-lg-9">
                        <div class="shop-product-fillter mb-50 pr-30">
                            <div class="totall-product mt-5">
                                <h2>
                                    <img class="w-36px mr-10" src="{{ asset('frontend/assets/imgs/theme/icons/category-1.svg') }}" alt="" />
                                    Articles et info
                                </h2>
                                <p class="text-muted mt-5 mb-0">
                                    {{ $blogs->total() }} article{{ $blogs->total() > 1 ? 's' : '' }} publié{{ $blogs->total() > 1 ? 's' : '' }}
                                </p>
                            </div>
                        </div>
                        <div class="loop-grid loop-list pr-30 mb-50">
                            @forelse ($blogs as $blog)

                                <article class="wow fadeIn animated hover-up mb-30 animated">
                                    {{-- L'URL était relative (« storage/… ») : elle se cassait dès que
                                         l'adresse de la page changeait de niveau. asset() donne une URL
                                         absolue, valable partout. --}}
                                    <div class="post-thumb"
                                         style="{{ $blog->image
                                                    ? 'background-image: url('.asset('storage/'.$blog->image).')'
                                                    : 'background-color:#f3f4f6' }}">
                                        <a href="{{ route('client.detailBlog', $blog->id) }}"
                                           style="display:block;width:100%;height:100%;"
                                           aria-label="{{ $blog->titre }}"></a>
                                    </div>
                                    <div class="entry-content-2 pl-50">
                                        <h3 class="post-title mb-20">
                                            <a href="{{route('client.detailBlog',$blog->id)}}">{{$blog->titre}}</a>
                                        </h3>
                                        {{-- Str::limit coupe proprement au lieu de masquer le dépassement
                                             en CSS : le texte reste lisible sur mobile. --}}
                                        <p class="post-exerpt mb-40">
                                            {{ Str::limit($blog->description, 220) }}
                                        </p>
                                        <div class="entry-meta meta-1 font-xs color-grey mt-10 pb-10">
                                            <div>
                                                <span class="post-on"> {{ $blog->created_at?->isoFormat('LL') }} </span>
                                                {{-- « vu » vaut null tant que l'article n'a pas été ouvert :
                                                     on affichait alors « vu » tout seul. --}}
                                                <span class="hit-count has-dot">{{ (int) $blog->vu }} vue{{ (int) $blog->vu > 1 ? 's' : '' }}</span>
                                                <span class="has-dot">{{ $blog->nb_commentaires }} commentaire{{ $blog->nb_commentaires > 1 ? 's' : '' }}</span>
                                            </div>
                                            <a href="{{route('client.detailBlog',$blog->id)}}" class="text-brand font-heading font-weight-bold">Voir plus <i class="fi-rs-arrow-right"></i></a>
                                        </div>
                                    </div>
                                </article>

                            @empty
                                <div class="text-center p-50">
                                    <h4 class="mb-10">Aucun article pour le moment</h4>
                                    <p class="text-muted">Revenez bientôt : de nouvelles publications arrivent régulièrement.</p>
                                </div>
                            @endforelse

                        </div>

                        {{-- Pagination réelle. L'ancienne affichait des numéros figés (1 à 6)
                             qui pointaient tous sur « # ». --}}
                        @if ($blogs->hasPages())
                            <div class="pagination-area mt-15 mb-sm-5 mb-lg-0">
                                <nav aria-label="Navigation des articles">
                                    <ul class="pagination justify-content-start">
                                        <li class="page-item {{ $blogs->onFirstPage() ? 'disabled' : '' }}">
                                            <a class="page-link" href="{{ $blogs->previousPageUrl() ?? '#' }}"><i class="fi-rs-arrow-small-left"></i></a>
                                        </li>
                                        @foreach ($blogs->getUrlRange(1, $blogs->lastPage()) as $page => $url)
                                            <li class="page-item {{ $page == $blogs->currentPage() ? 'active' : '' }}">
                                                <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                                            </li>
                                        @endforeach
                                        <li class="page-item {{ $blogs->hasMorePages() ? '' : 'disabled' }}">
                                            <a class="page-link" href="{{ $blogs->nextPageUrl() ?? '#' }}"><i class="fi-rs-arrow-small-right"></i></a>
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
