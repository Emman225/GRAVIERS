@extends('layout.main')
@section('title','Traitement Ticket')

@section('contenu')

<div class="screen-overlay"></div>

<div class="row">
    <div class="col-9">
        <div class="content-header">
            <h2 class="content-title">
              Assigner un agent pour traiter le retour du produit
            </h2>
            <div>
                {{-- <button class="btn btn-light rounded font-sm mr-5 text-body hover-up">Save to draft</button>
                <button class="btn btn-md rounded font-sm hover-up">Publich</button> --}}
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <h4>-----</h4>
            </div>
            <form  method="post" enctype="multipart/form-data">
                @csrf
                <div class="card-body">
                        @if(session('succes'))
                            <div class="alert alert-success text-center">
                                {{session('succes')}}
                            </div>
                        @endif

                        <div class="mb-4">
                            <select name="agent" id="" class="form-control">
                                <option value="">Selectionner un agent</option>
                                @foreach ($agents as $agent)
                                    <option value="{{$agent->id}}"> {{$agent->nom_prenoms}} </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- <div class="mb-4">
                            <label for="product_name" class="form-label text-center w-100">Observation de la description</label>
                            <textarea class="form-control" name="observation" id="" cols="30" rows="10"></textarea>
                            <span class="text-danger">
                                @error('reference')
                                    {{$message}}
                                @enderror
                            </span>
                        </div> --}}

                        <div class="mb-4">
                            <button  type="submit" class="btn btn-primary bg-success" style="margin:auto">
                                Approuver
                            </button>
                            {{-- <button type="submit" formaction="{{route('show.retourRefuse',$ticket)}}" class="btn btn-primary float-end bg-danger">
                                Réfuser
                            </button> --}}


                        </div>
                        {{-- <label class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" value="" />
                            <span class="form-check-label"> Make a template </span>
                        </label> --}}
                    </div>
            </form>
        </div>
        <!-- card end// -->

    </div>


</div>

@endsection
