<!DOCTYPE html>
<html class="no-js" lang="en">

<head>
    <meta charset="utf-8" />
    <title>@yield('title')</title>
    <meta http-equiv="x-ua-compatible" content="ie=edge" />
    <meta name="description" content="" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta property="og:title" content="" />
    <meta property="og:type" content="" />
    <meta property="og:url" content="" />
    <meta property="og:image" content="" />

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Favicon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset(config("constantes.logo")) }}" />
    <!-- Material Icons (Round) -->
    <link rel="stylesheet" href="{{ asset('backend/assets/css/vendors/material-icon-round.css') }}" />

    <!-- Template CSS -->
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/plugins/slider-range.css') }}" />
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/main.css?v=6.0') }}" />
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/myStyle.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/premium-client.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/premium-client-account.css?v=1.0') }}">
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/premium-auth.css?v=4.0') }}">
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/premium-product-detail.css?v=1.3') }}">
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/premium-responsive.css?v=1.0') }}">
    <script defer src="{{ asset('frontend/assets/js/table-dropdown-fix.js?v=6.0') }}"></script>
    <script defer src="{{ asset('frontend/assets/js/delete-confirm.js?v=2.0') }}"></script>

    {{-- carte (Leaflet hébergé en local pour éviter la dépendance au CDN unpkg) --}}
    <link rel="stylesheet" href="{{ asset('frontend/assets/leaflet/leaflet.css') }}" />
    <link rel="stylesheet" href="{{ asset('frontend/assets/leaflet/Control.Geocoder.css') }}" />

    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js" integrity="sha512-VEd+nq25CkR676O+pLBnDW09R7VQX9Mdiij052gVCp5yVH3jGtH70Ho/UUv4mJDsEdTvqRCFZg0NKGiojGnUCw==" crossorigin="anonymous" referrerpolicy="no-referrer" ></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    {{-- SweetAlert2 (popups premium) --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="client-layout">


