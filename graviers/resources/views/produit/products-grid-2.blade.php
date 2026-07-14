@extends('layout.main')

@section('contenu')
<div class="screen-overlay"></div>

                <div class="content-header">
                    <div>
                        <h2 class="content-title card-title">Products grid</h2>
                        <p>Lorem ipsum dolor sit amet.</p>
                    </div>
                    <div>
                        <a href="#" class="btn btn-light rounded font-md">Export</a>
                        <a href="#" class="btn btn-light rounded font-md">Import</a>
                        <a href="#" class="btn btn-primary btn-sm rounded">Create new</a>
                    </div>
                </div>
                <header class="card card-body mb-4">
                    <div class="row gx-3">
                        <div class="col-lg-4 col-md-6 me-auto">
                            <input type="text" placeholder="Search..." class="form-control" />
                        </div>
                        <div class="col-lg-2 col-6 col-md-3">
                            <select class="form-select">
                                <option>All category</option>
                                <option>Electronics</option>
                                <option>Clothings</option>
                                <option>Something else</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-6 col-md-3">
                            <select class="form-select">
                                <option>Latest added</option>
                                <option>Cheap first</option>
                                <option>Most viewed</option>
                            </select>
                        </div>
                    </div>
                </header>
                <!-- card-header end// -->



                <div class="row">
                    @foreach ($infoProducts as $infoProduct)
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="card card-product-grid">
                            <a href="#" class="img-wrap"> <img src="storage/{{$infoProduct->image}}" alt="Product" /> </a>
                            <div class="info-wrap">
                                <div class="dropdown float-end">
                                    <a href="#" class="btn btn-sm btn-brand rounded"> <i class="material-icons md-edit mr-5"></i>Edit </a>
                                    <a href="#" class="btn btn-sm font-sm btn-light rounded"> <i class="material-icons md-delete_forever"></i> Delete </a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="#">Edit info</a>
                                        <a class="dropdown-item text-danger" href="#">Delete</a>
                                    </div>
                                </div>
                                <a href="#" class="title">{{$infoProduct->produit->nom}}</a>
                                <div class="price mt-1"> {{$infoProduct->produit->unite}} F </div>
                                <!-- price-wrap.// -->
                            </div>
                        </div> 
                        <!-- card-product  end// -->
                    </div>
                    @endforeach
                    {{-- <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="card card-product-grid">
                            <a href="#" class="img-wrap"> <img src="{{asset('backend/assets/imgs/items/1.jpg')}}" alt="Product" /> </a>
                            <div class="info-wrap">
                                <div class="dropdown float-end">
                                    <a href="#" class="btn btn-sm btn-brand rounded"> <i class="material-icons md-edit mr-5"></i>Edit </a>
                                    <a href="#" class="btn btn-sm font-sm btn-light rounded"> <i class="material-icons md-delete_forever"></i> Delete </a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="#">Edit info</a>
                                        <a class="dropdown-item text-danger" href="#">Delete</a>
                                    </div>
                                </div>
                                <a href="#" class="title">Haagen-Dazs Caramel Cone Ice</a>
                                <div class="price mt-1">$179.00</div>
                                <!-- price-wrap.// -->
                            </div>
                        </div>
                        <!-- card-product  end// -->
                    </div> --}}


                </div>
                <!-- row.// -->
                <div class="pagination-area mt-15 mb-50">
                    <nav aria-label="Page navigation example">
                        <ul class="pagination justify-content-start">
                            <li class="page-item active"><a class="page-link" href="#">01</a></li>
                            <li class="page-item"><a class="page-link" href="#">02</a></li>
                            <li class="page-item"><a class="page-link" href="#">03</a></li>
                            <li class="page-item"><a class="page-link dot" href="#">...</a></li>
                            <li class="page-item"><a class="page-link" href="#">16</a></li>
                            <li class="page-item">
                                <a class="page-link" href="#"><i class="material-icons md-chevron_right"></i></a>
                            </li>
                        </ul>
                    </nav>
                </div>


  @endsection
