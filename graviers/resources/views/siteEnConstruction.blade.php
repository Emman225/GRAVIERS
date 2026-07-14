@include('layout.head')
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />

        <meta http-equiv="x-ua-compatible" content="ie=edge" />
        <meta name="description" content="" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta property="og:title" content="" />
        <meta property="og:type" content="" />
        <meta property="og:url" content="" />
        <meta property="og:image" content="" />

        <!-- Favicon -->
        <link rel="shortcut icon" type="image/x-icon" href="assets/imgs/theme/favicon.svg" />
        <!-- Template CSS -->
        <script src="assets/js/vendors/color-modes.js"></script>
        <link href="assets/css/main.css?v=6.0" rel="stylesheet" type="text/css" />
        <title>Warning</title>
    </head>

    <body>
            <section class="content-main">
                <div class="row mt-60">
                    <div class="col-sm-12">
                        <div class="w-50 mx-auto text-center">
                            {{-- <img src="{{asset('frontend/assets/imgs/theme/404.png')}}" width="350" alt="Page Not Found" /> --}}
                            <h3 class="mt-40 mb-15">{{ $titre ?? 'Site en construction' }}</h3>
                            <p>@if(isset($titre))Cette page est en cours de construction. @endif Revenez bientôt pour voir plus de contenu</p>
                            {{-- <a href="{{route('client.accueil')}}" class="btn btn-primary mt-4"><i class="material-icons md-keyboard_return"></i> Aller à la page d'accueil </a> --}}
                        </div>
                    </div>
                </div>
            </section>
            <!-- content-main end// -->



@include('layout.footer')

