
        <footer class="main-footer font-xs">
            <div class="row pb-30 pt-15">
                <div class="col-sm-6">
                    &copy; Immobilier - Location - Distribution. |
                    <script>
                        document.write(new Date().getFullYear());
                    </script>
                </div>
                <div class="col-sm-6">
                    <div class="text-sm-end">Tous droits reservés</div>
                </div>
            </div>
        </footer>
        </main>
        {{-- <div id="preloader-active">
        <div class="preloader d-flex align-items-center justify-content-center">
            <div class="preloader-inner position-relative">
                <div class="text-center">
                    <img src="{{asset('frontend/assets/imgs/theme/loading.gif')}}" alt="" />
                </div>
            </div>
        </div> --}}
    </div>
        {{-- @if (Session::has('success'))
            <script>
                toastr.success("{{ Session::get('success')}}");

            </script>
        @endif --}}
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script src="{{asset('frontend/assets/js/jquery-3.7.1.min.js')}}"></script>
            <script src="{{asset('frontend/assets/js/ajoutProduit.js?v=3.8')}}"></script>
            <script src="{{ asset('backend/assets/js/vendors/jquery-3.6.0.min.js') }}"></script>
            <script src="{{ asset('backend/assets/js/vendors/bootstrap.bundle.min.js') }}"></script>
            <script src="{{ asset('backend/assets/js/vendors/select2.min.js') }}"></script>
            <script src="{{ asset('backend/assets/js/vendors/perfect-scrollbar.js') }}"></script>
            <script src="{{ asset('backend/assets/js/vendors/jquery.fullscreen.min.js') }}"></script>
            <script src="{{ asset('backend/assets/js/vendors/chart.js') }}"></script>
            <!-- Main Script -->
            <script src="{{ asset('backend/assets/js/main.js?v=6.0" type="text/javascript') }}"></script>
            <script>
                let notification = document.getElementById('notify');

                setTimeout(() => {
                    if (notification) {
                        notification.classList.add("off")
                    }
                },5000)
            </script>
        {{-- <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
        <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script> --}}
        <!-- Leaflet 1.9.4 + Geocoder (hébergés en local pour éviter la dépendance au CDN unpkg) -->
        <link rel="stylesheet" href="{{ asset('frontend/assets/leaflet/leaflet.css') }}" />
        <link rel="stylesheet" href="{{ asset('frontend/assets/leaflet/Control.Geocoder.css') }}" />
        <script defer src="{{ asset('frontend/assets/leaflet/leaflet.js') }}"></script>
        <script defer src="{{ asset('frontend/assets/leaflet/Control.Geocoder.js') }}"></script>
            {{-- <script src="{{ asset('backend/assets/js/custom-chart.js" type="text/javascript') }}"></script> --}}

        {{-- <script src="{{ asset('backend/assets/js/vendors/jquery-3.6.0.min.js') }}  "></script>
        <script src="{{ asset('backend/assets/js/vendors/bootstrap.bundle.min.js') }}  "></script>
        <script src="{{ asset('backend/assets/js/vendors/select2.min.js') }}  "></script>
        <script src="{{ asset('backend/assets/js/vendors/perfect-scrollbar.js') }} "></script>
        <script src="{{ asset('backend/assets/js/vendors/jquery.fullscreen.min.js') }}  "></script>
        <script src="{{ asset('backend/assets/js/vendors/chart.js') }}  "></script> --}}

        {{-- using datatable --}}
        {{-- <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script> --}}




        <!-- Main Script -->
        {{-- <script src="{{ asset('backend/assets/js/main.js?v=6.0') }}" type="text/javascript"></script>
        <script src="{{ asset('backend/assets/js/custom-chart.js') }}" type="text/javascript"></script> --}}
        @yield('jsParts')
        {{-- @include('notify::components.notify') --}}

        <script>
            function togglePassword(id='') {
                const input = document.getElementById("password"+id);
               
                if (input.type === "password") {
                    input.type = "text";
                    document.getElementById("oeil"+id).innerHTML = '<i class="fa-solid fa-eye"></i>';
                } else {
                    input.type = "password";
                    document.getElementById("oeil"+id).innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
                }
            }
        </script>

        {{-- Empêche que le menu d'actions (dropdown Bootstrap) soit rogné par le conteneur
             .table-responsive (overflow). On lève l'overflow le temps que le menu est ouvert,
             puis on le restaure. Délégué sur document => fonctionne aussi après un redraw DataTables. --}}
        <script>
            (function () {
                document.addEventListener('show.bs.dropdown', function (e) {
                    var c = e.target && e.target.closest ? e.target.closest('.table-responsive') : null;
                    if (c) { c.dataset.prevOverflow = c.style.overflow; c.style.overflow = 'visible'; }
                });
                document.addEventListener('hide.bs.dropdown', function (e) {
                    var c = e.target && e.target.closest ? e.target.closest('.table-responsive') : null;
                    if (c) { c.style.overflow = c.dataset.prevOverflow || ''; }
                });
            })();
        </script>

        <x-notify::notify />
        @notifyJs
</body>

</html>
