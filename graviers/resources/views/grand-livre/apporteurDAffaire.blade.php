@php
    use Illuminate\Support\Carbon;
@endphp
@extends('layout.main')
{{-- @notifyCss --}}
@section('title','Liste des commandes')
<x-notify::notify />
@section('contenu')

<x-notify::notify />
    <div class="screen-overlay"></div>

    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Liste des commandes en cours de traitement</h2>

        </div>
    </div>

        @if(session('success'))
            <div class="alert alert-success text-center">
                {{session('success')}}
            </div>
        @endif 

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">

                <!-- card-header end// -->
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered tablee">
                            <thead>
                                <tr>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">N°</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Nom du client</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Type du client</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Montant</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Etat</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Date</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Réduction</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Enlevement</th>
                                    {{-- <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Action</th> --}}
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="texte-center" >  </td>
                                    <td class="texte-center"><b></b></td>
                                    <td class="text-center"> </td>
                                    <td class="texte-center">
                                        {{-- Afficher le prix avant la reduction --}}
                                        <span class="vieux-prix"></span> <br>
                                       
                                    </td>
                                    <td><span
                                            class="badge rounded-pill text-warning"></span>
                                    </td>
                                    <td class="texte-center"></td>
                                    
                                    <td class="text-end">
                                        <a  href=""  class="btn btn-md rounded font-sm">Faire une réduction</a>
                                    </td>
                                    
                                    <td class="text-end">
                                        <a  href=""  class="btn btn-md rounded font-sm">Les enlevements</a>
                                    </td>
                                </tr>

                            </tbody>
                        </table>
                    </div>
                    <!-- table-responsive //end -->
                </div>
                <!-- card-body end// --> 
            </div>
            <!-- card end// -->
        </div>
        <div class="col-md-3">

        </div>
    </div>
    <div class="pagination-area mt-15 mb-50">

    </div>

@endsection
@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection
@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            var $table = $('.tablee').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                order: [],
            });
        });
    </script>

@endsection
