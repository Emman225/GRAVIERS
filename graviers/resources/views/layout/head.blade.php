<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title>@yield('title')</title><!--zefzfzrfzf-->
        <meta http-equiv="x-ua-compatible" content="ie=edge" />
        <meta name="description" content="" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta property="og:title" content="" />
        <meta property="og:type" content="" />
        <meta property="og:url" content="" />
        <meta property="og:image" content="" />
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

        <script defer src="https://cdn.jsdelivr.net/npm/@coreui/coreui-pro@5.15.0/dist/js/coreui.bundle.min.js"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />

        {{-- css for datatable --}}
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" />
        <!-- Favicon -->
        <link rel="shortcut icon" type="image/x-icon" href="{{ asset(config("constantes.logo")) }}" />
        <!-- Template CSS -->
        <script src="{{asset('backend/assets/js/vendors/color-modes.js')}} "></script>
        <script src="https://code.jquery.com/jquery-3.2.1.min.js"></script>
        {{-- <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script> --}}
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <link href="{{ asset('backend/assets/css/main.css?v=6.0') }}  " rel="stylesheet" type="text/css" />
        <link rel="stylesheet" href="{{ asset('backend/assets/css/myStyle.css?v=1.1') }}">
        <link rel="stylesheet" href="{{ asset('backend/assets/css/premium-admin.css?v=1.1') }}">
        <link rel="stylesheet" href="{{ asset('backend/assets/css/premium-dashboard.css?v=1.0') }}">
        <link rel="stylesheet" href="{{ asset('backend/assets/css/premium-auth.css?v=4.0') }}">
        <script defer src="{{ asset('backend/assets/js/table-dropdown-fix.js?v=8.2') }}"></script>
        <script defer src="{{ asset('backend/assets/js/delete-confirm.js?v=4.0') }}"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
        {{-- <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" /> --}}

        {{-- <link href="https://cdn.jsdelivr.net/npm/@coreui/coreui-pro@5.15.0/dist/css/coreui.min.css" rel="stylesheet"> --}}

        {{-- Carte (Leaflet hébergé en local) --}}
        <link rel="stylesheet" href="{{ asset('frontend/assets/leaflet/leaflet.css') }}"/>
        <link rel="stylesheet" href="{{ asset('frontend/assets/leaflet/Control.Geocoder.css') }}" />
        {{-- @notifyCss --}}
        @yield('cssParts')
    </head>
    <body>
