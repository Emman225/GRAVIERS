@php

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

// Image de détail facultative : on retombe sur l'image de couverture plutôt que
// d'afficher une image cassée.
$imagePrincipale = $blog->image_detail ?: $blog->image;
$moyenne = $blog->noteMoyenne();

@endphp


@extends('client.main')
@section('title', $blog->titre)
@section('content')

    <main class="main">
        @if(session('success'))
            <div class="container mt-30">
                <div class="alert alert-success text-center" id="notify">
                    {{session('success')}}
                </div>
            </div>
        @endif
        @if(session('fail'))
            <div class="container mt-30">
                <div class="alert alert-warning text-center" id="notify">
                    {{session('fail')}}
                </div>
            </div>
        @endif

        <div class="page-content mb-50">
            <div class="container">
                <div class="row">
                    <div class="col-lg-9 m-auto">
                        <div class="single-page pt-50 pr-30">
                            <div class="single-header style-2">
                                <div class="row">
                                    <div class="col-xl-10 col-lg-12 m-auto">
                                        <h2 class="mb-10">{{$blog->titre}}</h2>
                                        <div class="single-header-meta">
                                            <div class="entry-meta meta-1 font-xs mt-15 mb-15">
                                                <span class="post-on">{{ $blog->created_at?->isoFormat('LL') }}</span>
                                                <span class="hit-count has-dot">{{ (int) $blog->vu }} vue{{ (int) $blog->vu > 1 ? 's' : '' }}</span>
                                                <span class="has-dot">{{ $commentaires->count() }} commentaire{{ $commentaires->count() > 1 ? 's' : '' }}</span>
                                                @if ($moyenne)
                                                    <span class="has-dot">Note moyenne : {{ $moyenne }}/5</span>
                                                @endif
                                            </div>
                                            <div class="social-icons single-share">
                                                <ul class="text-grey-5 d-inline-block">
                                                    <li><a href="{{ route('client.blog') }}" class="text-brand font-weight-bold">
                                                        <i class="fi-rs-arrow-small-left"></i> Tous les articles</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if ($imagePrincipale)
                                {{-- URL absolue : « storage/… » en relatif se cassait selon la page. --}}
                                <figure class="single-thumbnail">
                                    <img src="{{ asset('storage/'.$imagePrincipale) }}" alt="{{ $blog->titre }}">
                                </figure>
                            @endif

                            <div class="single-content">
                                <div class="row">
                                    <div class="col-xl-10 col-lg-12 m-auto">
                                        {{-- nl2br : les retours à la ligne saisis en back-office étaient
                                             perdus, tout le texte s'affichait d'un bloc. e() reste appliqué
                                             avant, donc aucune balise saisie n'est interprétée. --}}
                                        <p class="single-excerpt">
                                            {!! nl2br(e($blog->description)) !!}
                                        </p>

                                        <!--Formulaire de commentaire-->
                                        <div class="comment-form">
                                            <h3 class="mb-15 text-center mb-30">Laissez un commentaire</h3>
                                            <div class="row">
                                                <div class="col-lg-9 col-md-12  m-auto">

                                                    @if (!Auth::check())
                                                        {{-- Sans ce bloc, le visiteur non connecté envoyait le
                                                             formulaire pour être redirigé sans explication vers
                                                             la page de connexion, son texte perdu. --}}
                                                        <div class="alert alert-info text-center mb-30">
                                                            Vous devez être connecté pour laisser un commentaire.
                                                            <a href="{{ route('client.login') }}" class="text-brand font-weight-bold">Se connecter</a>
                                                            ou <a href="{{ route('client.register') }}" class="text-brand font-weight-bold">créer un compte</a>.
                                                        </div>
                                                    @else
                                                        @if ($monCommentaireEnAttente)
                                                            <div class="alert alert-warning mb-30">
                                                                Votre commentaire du
                                                                {{ $monCommentaireEnAttente->created_at?->isoFormat('LL') }}
                                                                est en attente de validation. Il apparaîtra ici une fois approuvé.
                                                            </div>
                                                        @endif

                                                        @if ($errors->any())
                                                            <div class="alert alert-danger mb-30">
                                                                <ul class="mb-0 ps-3">
                                                                    @foreach ($errors->all() as $erreur)
                                                                        <li>{{ $erreur }}</li>
                                                                    @endforeach
                                                                </ul>
                                                            </div>
                                                        @endif

                                                        <form class="form-contact comment_form mb-50" method="post"
                                                              action="{{ route('client.commentaireEtNote', $blog->id) }}" id="commentForm">
                                                            @csrf
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <div class="form-group">
                                                                        {{-- old() : le texte saisi n'était pas restitué quand
                                                                             la validation échouait. --}}
                                                                        <textarea class="form-control w-100 @error('commentaire') is-invalid @enderror"
                                                                                  name="commentaire" id="comment" cols="30" rows="9"
                                                                                  maxlength="2000"
                                                                                  placeholder="Votre commentaire">{{ old('commentaire') }}</textarea>
                                                                    </div>
                                                                </div>

                                                                <div class="col-12">
                                                                    <div class="form-group">
                                                                        {{-- Le champ libre acceptait n'importe quel nombre
                                                                             (0, 900, négatif) : la note s'affiche ensuite en
                                                                             largeur d'étoiles, ce qui débordait. --}}
                                                                        <select class="form-control @error('note') is-invalid @enderror" name="note" id="note">
                                                                            <option value="">Donnez une note (facultatif)</option>
                                                                            @for ($n = 5; $n >= 1; $n--)
                                                                                <option value="{{ $n }}" {{ old('note') == $n ? 'selected' : '' }}>
                                                                                    {{ $n }} / 5
                                                                                </option>
                                                                            @endfor
                                                                        </select>
                                                                    </div>
                                                                </div>

                                                            </div>
                                                            <div class="form-group">
                                                                <button type="submit" class="button button-contactForm">Poster le commentaire</button>
                                                            </div>
                                                            <p class="font-xs text-muted">
                                                                Votre commentaire sera publié après validation par notre équipe.
                                                            </p>
                                                        </form>
                                                    @endif

                                                    <div class="comments-area">
                                                        <h3 class="mb-30">Les commentaires</h3>
                                                        <div class="comment-list   m-auto">
                                                            {{-- On parcourt désormais les commentaires eux-mêmes et non
                                                                 la table pivot des clients : celle-ci ignorait les
                                                                 suppressions et laissait réapparaître un commentaire
                                                                 retiré par la modération. --}}
                                                            @forelse ($commentaires as $commentaire)
                                                                <div class=" single-comment justify-content-between d-flex mb-30">
                                                                    <div class="user justify-content-between d-flex">
                                                                        <div class="thumb text-center">
                                                                            <img src="{{ asset('frontend/assets/imgs/theme/icons/icon-user.svg') }}" alt="">
                                                                            <span class="font-heading text-brand">
                                                                                {{ $commentaire->client->display_name }}
                                                                            </span>
                                                                        </div>
                                                                        <div class="desc">
                                                                            <div class="d-flex justify-content-between mb-10">
                                                                                <div class="d-flex align-items-center">
                                                                                    <span class="font-xs text-muted">
                                                                                        {{ $commentaire->created_at?->isoFormat('LL') }}
                                                                                        {{ $commentaire->created_at ? Carbon::parse($commentaire->created_at)->format('à H:i') : '' }}
                                                                                    </span>
                                                                                </div>
                                                                                @if ($commentaire->note)
                                                                                    <div class="product-rate d-inline-block ms-5">
                                                                                        <div class="product-rating" style="width: {{ min(100, $commentaire->note * 20) }}%"></div>
                                                                                    </div>
                                                                                @endif
                                                                            </div>
                                                                            <p class="mb-10">{{ $commentaire->commentaire }}</p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @empty
                                                                <p class="text-muted">Aucun commentaire pour le moment. Soyez le premier à réagir.</p>
                                                            @endforelse
                                                        </div>
                                                    </div>

                                                    @if ($autresBlogs->count())
                                                        <div class="mt-50">
                                                            <h3 class="mb-30">À lire également</h3>
                                                            <div class="row">
                                                                @foreach ($autresBlogs as $autre)
                                                                    <div class="col-md-4 mb-20">
                                                                        <a href="{{ route('client.detailBlog', $autre->id) }}" class="text-decoration-none">
                                                                            <div style="height:120px;border-radius:10px;background-size:cover;background-position:center;{{ $autre->image ? 'background-image:url('.asset('storage/'.$autre->image).')' : 'background-color:#f3f4f6' }}"></div>
                                                                            <h6 class="mt-10">{{ Str::limit($autre->titre, 60) }}</h6>
                                                                        </a>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </main>

@endsection
