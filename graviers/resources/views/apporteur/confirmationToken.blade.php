{{-- @extends('layout.main')
@section('title', 'Création de compte')
@section('contenu')
    @extends('layout.main')
@section('contenu') --}}
@include('layout.head')
@section('title','Création de compte')

    <div class="card mt-200 mx-auto "style=" width: 40rem; ">
        @if (session('succes'))
            <div class="alert alert-success text-center">
                <p>Un email a été envoyé à <b>{{$email}}</b></p>
            </div>
        @endif
        <div class="card-body">
            <div class="img" style="display:flex; align-item:center; justify-content: center">
                <img class="img-lg center" src="{{ asset('backend/assets/imgs/theme/logooBlanc.svg') }}" alt="User" />
            </div>
            <h4 class="card-title text-center mb-4">Confirmer votre compte !</h4>

            <form action="" method="post" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label">token <span class="text-danger">*</span></label>
                    <input class="form-control" value="" name="token"
                        type="text" />
                    <span class="text-danger">
                        @error('token')
                            {{ $message }}
                        @enderror
                    </span>
                </div>
                <!-- form-group// -->

                <!-- form-group  .// -->
                <div class="mb-4">
                    <button type="submit" class="btn btn-primary ">Je confirme</button>
                </div>
                <!-- form-group// -->
            </form>
        </div>
    </div>
    @include('layout.footer')
