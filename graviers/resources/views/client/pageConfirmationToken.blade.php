@extends('client.main')
@section('title','Confirmation de compte')
@section('content')

{{-- @dd(Cart::content()) --}}
    <div class="page-content pt-30 pb-150">
        @if(session('failToken'))
            <div class="alert alert-info text-center">
                {{session('failToken')}}
            </div>
        @endif
        @if(session('failInfo'))
            <div class="alert alert-danger text-center">
                {{session('failInfo')}}
            </div>
        @endif

        @if(session('success'))
            <div class="alert alert-success">
                {{session('success')}}
            </div>
        @endif
        @include('client.navMobile')

        <div class="container">
            <div class="row">
                <div class="col-xl-8 col-lg-10 col-md-12 m-auto">
                    <div class="row">
                        {{-- <div class="col-lg-6 pr-30 d-none d-lg-block">
                            <img class="border-radius-15" src="{{asset('frontend/assets/imgs/page/login-11.png')}}" alt="" />
                        </div> --}}
                        <div class="col-lg-12 col-md-12">
                            <div class="login_wrap widget-taber-content background-white">
                                <div class="padding_eight_all bg-white">
                                    <div class="heading_s1">
                                        <h1 class="mb-5">Confirmer votre compte !</h1>
                                        <p>Un email a été envoyé à <b>{{$email}}</b></p>
                                    </div>
                                    @if(session('error'))
                                        <div class="alert alert-danger"> {{session('error')}} </div>
                                    @endif
                                    @if(session('info'))
                                        <div class="alert alert-info"> {{session('info')}} </div>
                                    @endif
                                    <form method="post" action="">
                                        @csrf
                                        <div class="form-group">
                                            <input style="border: 1px solid black" type="text" required="" name="token" placeholder="Code" />
                                            <input type="hidden" name="email" value="{{$email}}">
                                        </div>


                                        <input type="hidden" name="attente" value="{{session('attente')}}">
                                        <div class="login_footer form-group mb-50">
                                            {{-- <div class="chek-form">
                                                <div class="custome-checkbox">
                                                    <input class="form-check-input" type="checkbox" name="checkbox" id="exampleCheckbox1" value="" />
                                                    <label class="form-check-label" for="exampleCheckbox1"><span>Reme</span></label>
                                                </div>
                                            </div> --}}
                                        </div>
                                        <div class="form-group">
                                            <button type="submit" class="btn btn-heading btn-block hover-up" name="login">Je confirme</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
