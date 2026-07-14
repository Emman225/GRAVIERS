@php

use Illuminate\Support\Carbon;

$post_date = $commande->created_at;

@endphp


@extends('client.main')
@section('title','Blog')
@section('content')


    <main class="main">

        <div class="page-content mb-50">
            <div class="container">
                <div class="row">
                    <div class="col-lg-9">
                        <div class="shop-product-fillter mb-50 pr-30">
                            <div class="totall-product mt-5">
                                <h2>
                                    <img class="w-36px mr-10" src="assets/imgs/theme/icons/category-1.svg" alt="" />
                                    Articles et info
                                </h2>
                            </div>
                            {{-- <div class="sort-by-product-area">
                                <div class="sort-by-cover mr-10">
                                    <div class="sort-by-product-wrap">
                                        <div class="sort-by">
                                            <span><i class="fi-rs-apps"></i>Show:</span>
                                        </div>
                                        <div class="sort-by-dropdown-wrap">
                                            <span> 50 <i class="fi-rs-angle-small-down"></i></span>
                                        </div>
                                    </div>
                                    <div class="sort-by-dropdown">
                                        <ul>
                                            <li><a class="active" href="#">50</a></li>
                                            <li><a href="#">100</a></li>
                                            <li><a href="#">150</a></li>
                                            <li><a href="#">200</a></li>
                                            <li><a href="#">All</a></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="sort-by-cover">
                                    <div class="sort-by-product-wrap">
                                        <div class="sort-by">
                                            <span><i class="fi-rs-apps-sort"></i>Sort:</span>
                                        </div>
                                        <div class="sort-by-dropdown-wrap">
                                            <span>Featured <i class="fi-rs-angle-small-down"></i></span>
                                        </div>
                                    </div>
                                    <div class="sort-by-dropdown">
                                        <ul>
                                            <li><a class="active" href="#">Featured</a></li>
                                            <li><a href="#">Newest</a></li>
                                            <li><a href="#">Most comments</a></li>
                                            <li><a href="#">Release Date</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div> --}}
                        </div>
                        <div class="loop-grid loop-list pr-30 mb-50">
                            @foreach ($blogs as $blog)

                                <article class="wow fadeIn animated hover-up mb-30 animated">
                                    <div class="post-thumb" style="background-image: url(storage/{{$blog->image}})">

                                    </div>
                                    <div class="entry-content-2 pl-50">
                                        <h3 class="post-title mb-20">
                                            <a href="{{route('client.detailBlog',$blog->id)}}">{{$blog->titre}}</a>
                                        </h3>
                                        <p class="post-exerpt mb-40"
                                        style="
                                            max-width: 500px;
                                            overflow: hidden;
                                            text-overflow: ellipsis;
                                            white-space: nowrap;
                                        "
                                        >
                                            {{$blog->description}}
                                        </p>
                                        <div class="entry-meta meta-1 font-xs color-grey mt-10 pb-10">
                                            <div>
                                                <span class="post-on"> {{ $blog->created_at->isoFormat('LL') }} </span>
                                                <span class="hit-count has-dot">{{$blog->vu}} vu</span>
                                            </div>
                                            <a href="{{route('client.detailBlog',$blog->id)}}" class="text-brand font-heading font-weight-bold">Voir plus <i class="fi-rs-arrow-right"></i></a>
                                        </div>
                                    </div>
                                </article>

                            @endforeach

                        </div>
                        <div class="pagination-area mt-15 mb-sm-5 mb-lg-0">
                            <nav aria-label="Page navigation example">
                                <ul class="pagination justify-content-start">
                                    <li class="page-item">
                                        <a class="page-link" href="#"><i class="fi-rs-arrow-small-left"></i></a>
                                    </li>
                                    <li class="page-item"><a class="page-link" href="#">1</a></li>
                                    <li class="page-item active"><a class="page-link" href="#">2</a></li>
                                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                                    <li class="page-item"><a class="page-link dot" href="#">...</a></li>
                                    <li class="page-item"><a class="page-link" href="#">6</a></li>
                                    <li class="page-item">
                                        <a class="page-link" href="#"><i class="fi-rs-arrow-small-right"></i></a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>

                        @include('client.categorieEtFiltreur')

                </div>
            </div>
        </div>

    </main>

@endsection

