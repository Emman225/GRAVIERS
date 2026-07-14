<div>
    <!-- I have not failed. I've just found 10,000 ways that won't work. - Thomas Edison -->
</div>

@php
    use Illuminate\Support\Carbon;
@endphp

@extends('client.main')
@section('title', 'Produit à retourner')
@section('content')
    <main class="main">
        @if (session('success'))
            <div class="alert alert-success text-center" id="notify">
                {{session('success')}}
            </div>
        @endif
        <div class="page-header breadcrumb-wrap">
            <div class="container">
                <div class="breadcrumb">
                    <a href="{{ route('client.index') }}" rel="nofollow"><i class="fi-rs-home mr-5"></i>Accueil</a>
                    <span></span> Boutique
                    <span></span> Adresse
                </div>
            </div>
        </div>

        <div class="container mb-80 mt-50">
            <div class="row">
                <div class="col-lg-8 mb-40">
                    <h1 class="heading-2 mb-10">Retour de produit</h1>
                    <div class="d-flex justify-content-between">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-7">
                    <div class="row mb-50">

                        <div class="col-lg-6">

                        </div>
                    </div>
                    <div class="row">
                        <h4 class="mb-30">Motif de retour</h4>
                        <form method="post" action="{{route('client.motif',$detail)}}">
                            @csrf
                            <div class="row shipping_calculator">
                                <p>Veuillez renseigner le motif de retour de votre produit </p><br><br>

                                <textarea required name="motif" id="" cols="30" class="mt-5" placeholder="" rows="10"></textarea>

                                <button type="submit" class="btn btn-fill-out btn-block mt-30">Envoyer la demande</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
@section('jspart')
<script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script>
        let notification = document.getElementById('notification');

        setTimeout(() => {
            if (notification) {
                notification.classList.add("off")
            }
        },5000)
    </script>
@endsection



