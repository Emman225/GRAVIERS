
@include('layout.head')

                <div class="card mt-200 mx-auto" style="margin: auto; width: 30rem; height: 30rem">

                    <div class="card-body" style="">
                        <h4 class="card-title mb-4 text-center ">

                            Bon de produit : Gravier <br>
                            @if (session('modified'))
                                <div class="alert alert-success">
                                    {{session('modified')}}
                                </div>
                            @endif

                        </h4>
                        @if (session('fail'))
                            <div class="alert alert-danger">
                                {{session('fail')}}
                            </div>
                        @endif

                        <form method="post" action="">
                            @csrf
                            <div class="mb-3">
                                <select class="form-control" name="fournisseur">
                                    @foreach($produits->fournisseurs as $fournisseur)
                                        <option value="{{$fournisseur->id}}"> {{$fournisseur->nom_prenoms}} </option>
                                        @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <select class="form-control" name="livreur">
                                    @foreach ($livreurs as $livreur )
                                    <option value="{{$livreur->id}}"> {{$livreur->user->nom_prenoms}} </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- form-group// -->
                            <!-- form-group form-check .// -->
                            <div class="mb-4">
                                <button type="submit" class="btn btn-primary w-100">Valider le bon</button>
                            </div>
                            <!-- form-group// -->
                        </form>


                    </div>
                </div>

@include('layout.footer')
