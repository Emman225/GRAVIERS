import './bootstrap';
import Swal from 'sweetalert2';
import Swal from 'sweetalert2/dist/sweetalert2.js';
import toastr from 'toastr';

window.toastr = toastr;
toastr.options = {
    "closeButton": true,
    "progressBar": true,
    "positionClass": "toast-top-right"
};

window.Swal = Swal;
// Pour un accès global si besoin :
window.Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000
});
