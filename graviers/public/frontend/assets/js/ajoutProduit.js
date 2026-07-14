// document.getElementById('maj').addEventListener('click', function(e) {
//     e.preventDefault();

//     const xhr = new XMLHttpRequest();
//     const _token = document.querySelector('meta[name="csrf-token"]').content;

//      // Retrieving the form data
//      var myForm = document.getElementById("formulaire");
//      var formData = new FormData(myForm);

//     xhr.open('POST', '/mis-a-jour', true);
//     xhr.setRequestHeader('Content-Type', 'application/json');
//     xhr.setRequestHeader('X-CSRF-TOKEN', _token);

//     xhr.onload = function() {
//         if (xhr.status === 200) {
//             alert(xhr.responseText);
//         }
//     };

//     xhr.onerror = function() {
//         console.error('Erreur de requête');
//     };

//     xhr.send(JSON.stringify({ _token: _token }));
// });

/*$('#maj').on('click', function(e) {
    e.preventDefault();



    let _token = $('input[name="_token"]').val();
    let myForm = document.getElementById("formulaire");
    let formData = new FormData(myForm);
    /*const data = {
        _token: _token,
        frm : $('#formulaire').serialize(),
      };*/

     // let frm =  $('#formulaire').serialize();

   // $.ajax({
     //   url: '/mis-a-jour',
       /* data: {
            _token: _token,
            frm : JSON.stringify(frm),
        },
        //data: JSON.stringify(data),
        data: formData,
        method: "POST",
        success: function(data) {
            alert(data);
        },
        error: function(xhr, status, error) {
            console.error('Erreur:', error);
        }
    });
});*/
/*
²   function plusDePrduit(){

    $.ajax({
        url: '/recuperer-les-unites',
        method: 'GET',
        success: function(data){

            console.log(data);


                // const element = array[index];
                $('#info').append(`<div class="container" >
                    <input type="text" style="margin-top: 3rem;" name="produit[]" class="form-control" placeholder="Nom du produit">
                    <textarea name="description" class="form-control " style="margin-top: 3rem; height: 200px" id="" placeholder="Description" cols="30" rows="10"></textarea>
                    {{-- <input type="text" style="margin-top: 3rem;" name="description[]" class="form-control" placeholder="Description"> --}}
                </div>
                <div class="mb-3">
                    <div class="row gx-2 mt-3">
                        <div class="col-8"><input class="form-control" name="qte" placeholder="Quantité" type="text" /></div>
                        <select class="form-control select-active" name="unite">
                            <option value="">Unité</option>
                        `+
                        for(let index = 0; index < data.length; index++) {

                        }+
                            `
                        </select>
                    </div>
                </div>)`);




        },
        error: function(response){
            console.log('erreur'.$response)
        }
    })





}*/

function formatNumber(number) {
    return number.toLocaleString('fr-FR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    });
}



// $('#formulaire').on('submit',function(e) {
//     e.preventDefault();

//     let formData = new FormData(this);

//     $.ajax({
//             type:'POST',
//             url: "/mis-a-jour",
//             data: formData,
//             contentType: false,
//             processData: false,
//             success: (data) => {
//                 console.log('data');

//                 $('#montant_total').html('')
//                 $('#montant_total').html(data)
//                 console.log(data)
//                 console.log(data.length)

//                 $('#listProduit').html('')
//                 for (let index = 0; index < data.length; index++) {
//                     console.log(data[index].options.image)

//                     $('#listProduit').append(`
//                         <tr class="pt-30">
//                             <td class="custome-checkbox pl-30">
//                             </td>

//                             <td class="image product-thumbnail pt-40">
//                                 <img src="/storage/${data[index].options.image}" alt="#">
//                             </td>

//                             <td class="product-des product-name">
//                                 <h6 class="mb-5"><a class="product-name mb-10 text-heading" href="shop-product-right.html"> ${data[index].name} </a></h6>
//                                 <div class="product-rate-cover">
//                                     <div class="product-rate d-inline-block">
//                                         <div class="product-rating"
//                                             style="width:${data[index].options.note}%">
//                                         </div>
//                                     </div>
//                                     <span class="font-small ml-5 text-muted">
//                                         (${(data[index].options.note)/10})
//                                     </span>
//                                 </div>
//                             </td>

//                             <td class="price" data-title="Prix">
//                                 <div class=" mr-15">
//                                     <div class="detail-qty">
//                                         ${data[index].price} fcfa /
//                                         ${data[index].options.unite}
//                                     </div>
//                                 </div>
//                             </td>

//                             <td class="text-center detail-info" data-title="Quantité">
//                                 <div class="detail-extralink mr-15">
//                                     <div class="detail-qty border radius">
//                                         <a href="#" class="qty-down"><i
//                                                 class="fi-rs-angle-small-down"></i></a>
//                                         <input type="text" name="qte[]" class="qty-val"
//                                             value="${data[index].qty}" min="1">
//                                         <a href="#" class="qty-up"><i
//                                                 class="fi-rs-angle-small-up"></i></a>
//                                     </div>
//                                 </div>
//                             </td>

//                             <td class="price" data-title="Sous-total">
//                                 <div class="detail-extralink mr-15">
//                                     <div class="radius w-100 border-bleu">

//                                         <input type="text" id="montant"
//                                             min="${data[index].price}" name="montant[]"
//                                             class="qty-val"
//                                             value="${data[index].price * data[index].qty}"
//                                             min="1">

//                                     </div>
//                                 </div>
//                                 <h4 class="text-brand"> fcfa
//                                 </h4>
//                             </td>
//                                     @php $total += $produit->model->prix_moyen * $produit->qty @endphp
//                             <td class="action text-center" data-title="Supprimer">
//                                 <a onclick="return confirm('Voulez vous supprimer ce produit?')" href="" class="text-body">
//                                     <i class="fi-rs-trash"></i>
//                                 </a>
//                             </td>
//                             <input type="hidden" name="rowId[]" value="${data[index].rowId}">
//                         </tr>
//                         `)

//                     }

//                     $("#contenuPanier").load(location.href + " #contenuPanier");

//             },
//             error: function(data){
//               console.log(data)
//             }
//        });

// });

$(document).ready(function() {
    $('#maj').click(function() {
        console.log('ok')
        $('#shopping-summery').load('{{ route("shopping-summery") }}' + '?_=' + new Date().getTime());
    });
});

/**
 * Toast premium 100% custom (sans dépendance externe).
 * Injection unique des styles + container au chargement.
 */
(function initCartToast() {
    if (document.getElementById('cart-toast-styles')) return;

    var style = document.createElement('style');
    style.id = 'cart-toast-styles';
    style.textContent = ''
        + '#cart-toast-container{position:fixed;top:24px;right:24px;z-index:99999;display:flex;flex-direction:column;gap:12px;pointer-events:none;}'
        + '.cart-toast{display:flex;align-items:center;gap:14px;min-width:320px;max-width:420px;padding:16px 20px;background:#ffffff;border-radius:14px;box-shadow:0 12px 32px rgba(0,0,0,0.15),0 4px 12px rgba(0,0,0,0.06);border-left:4px solid #10b981;transform:translateX(120%);opacity:0;transition:transform .4s cubic-bezier(.4,0,.2,1),opacity .3s ease;pointer-events:auto;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;overflow:hidden;position:relative;}'
        + '.cart-toast.show{transform:translateX(0);opacity:1;}'
        + '.cart-toast-icon{flex-shrink:0;width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:22px;}'
        + '.cart-toast--success{border-left-color:#10b981;}'
        + '.cart-toast--success .cart-toast-icon{background:#d1fae5;color:#065f46;}'
        + '.cart-toast--warning{border-left-color:#f59e0b;}'
        + '.cart-toast--warning .cart-toast-icon{background:#fef3c7;color:#92400e;}'
        + '.cart-toast--error{border-left-color:#ef4444;}'
        + '.cart-toast--error .cart-toast-icon{background:#fee2e2;color:#991b1b;}'
        + '.cart-toast-body{flex-grow:1;}'
        + '.cart-toast-title{margin:0;font-size:0.95rem;font-weight:700;color:#1e293b;line-height:1.25;}'
        + '.cart-toast-text{margin:3px 0 0;font-size:0.82rem;color:#64748b;line-height:1.3;}'
        + '.cart-toast-close{flex-shrink:0;background:transparent;border:0;cursor:pointer;color:#9ca3af;font-size:20px;line-height:1;padding:0 4px;transition:color .15s;}'
        + '.cart-toast-close:hover{color:#1e293b;}'
        + '.cart-toast-progress{position:absolute;bottom:0;left:0;height:3px;background:rgba(16,185,129,0.4);width:100%;transform-origin:left;animation:cartToastProgress 3s linear forwards;}'
        + '.cart-toast--warning .cart-toast-progress{background:rgba(245,158,11,0.4);}'
        + '.cart-toast--error .cart-toast-progress{background:rgba(239,68,68,0.4);}'
        + '@keyframes cartToastProgress{from{transform:scaleX(1);}to{transform:scaleX(0);}}'
        + '@media (max-width:480px){#cart-toast-container{top:12px;right:12px;left:12px;}.cart-toast{min-width:0;max-width:none;}}';
    document.head.appendChild(style);

    var container = document.createElement('div');
    container.id = 'cart-toast-container';
    document.body.appendChild(container);
})();

/**
 * Affiche un toast moderne premium.
 * @param {'success'|'warning'|'error'} type
 * @param {string} title
 * @param {string} [text]
 */
function showCartToast(type, title, text) {
    var container = document.getElementById('cart-toast-container');
    if (!container) {
        // Sécurité : si l'init n'a pas pu créer le container, on l'ajoute maintenant
        container = document.createElement('div');
        container.id = 'cart-toast-container';
        document.body.appendChild(container);
    }

    var icons = { success: '✓', warning: '!', error: '✗' };

    var toast = document.createElement('div');
    toast.className = 'cart-toast cart-toast--' + type;
    toast.innerHTML =
        '<div class="cart-toast-icon">' + (icons[type] || '✓') + '</div>'
      + '<div class="cart-toast-body">'
      +     '<p class="cart-toast-title">' + title + '</p>'
      +     (text ? '<p class="cart-toast-text">' + text + '</p>' : '')
      + '</div>'
      + '<button class="cart-toast-close" type="button" aria-label="Fermer">&times;</button>'
      + '<div class="cart-toast-progress"></div>';

    container.appendChild(toast);

    // Animation d'apparition
    setTimeout(function () { toast.classList.add('show'); }, 10);

    // Fermeture
    function close() {
        toast.classList.remove('show');
        setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 400);
    }

    toast.querySelector('.cart-toast-close').addEventListener('click', close);
    setTimeout(close, 3000);
}

// Gère les boutons +/- de quantité dans les modals "Vue rapide" (quickView).
// La quantité est un <input class="qty-val"> voisin du bouton cliqué (dans .detail-qty)
// et reste saisissable au clavier ; on borne simplement à un minimum de 1.
function changeQtyQuickView(btn, delta){
    var field = btn.parentElement.querySelector('.qty-val');
    if (!field) return;
    var val = parseInt(field.value, 10);
    if (isNaN(val) || val < 1) val = 1;
    field.value = Math.max(1, val + delta);
}

function ajouter(id, qty){
    var quantite = parseInt(qty, 10);
    if (isNaN(quantite) || quantite < 1) quantite = 1;
    $(function(){
        $.ajax({
            url : '/client/ajouter/'+id,
            data: { quantite: quantite },
            dataType: 'json',
            method: 'GET',
            success: function(data){
                if (data < 0) {
                    showCartToast('warning', 'Produit déjà dans le panier', 'Vous avez déjà sélectionné ce produit.');
                } else {
                    showCartToast('success', 'Produit ajouté au panier', 'Votre sélection a bien été enregistrée.');
                    $('#panier').addClass('blue');
                    $('#panier').html('');
                    $('#panier').append(data);
                }
            },
            error: function(){
                showCartToast('error', 'Erreur', 'Impossible d\'ajouter ce produit au panier.');
            }
        });
    });
}





function jaime(id){


        $.ajax({
            url : '/like/'+id,
            dataType: 'json',
            method: 'GET',
            success: function(data){

                console.log(data);

                if(data.auth){
                    $('#like').addClass('blue')
                    $('#like').html('')
                    $('#like').append(data.count)


                    $('.rep').html(data.rep);
                    $('.like').show();
                    setTimeout(() => {
                        $('.like').hide();
                    },3000)

                    console.log(data);
                }else{
                    $('.auth').html(data.rep);
                    $('.authBar').show();
                    setTimeout(() => {
                        $('.authBar').hide();
                    },4000)
                }

            }
        })


}



function editer() {

    let form = $('#formulaire').val();

    console.log(form)
    // let _token =  $('input[name="_token"]').val();

    // console.log(_token)
    // $.ajax({
    //     url: '/mis-a-jour',
    //     data: {
    //         _token : $('input[name="_token"]').val()
    //     },
    //     method: 'POST',
    //     dataType : 'json',
    //     success: function(data){
    //         console.log(data)
    //     }
    // })
}

function soumettre(form){

    // alert('ok')

    // créer une instance d'Ajax
    let ajax = new XMLHttpRequest();

    // ouvrir la requete
    ajax.open("POST", form.getAttribute("action"), true)
    // ecoute de la réponse de la requete
    console.log(ajax);
    ajax.onreadystatechange = function(){
        alert('ok')
        // si la requete a bien fonctionné
        if(this.readyState == 4 && this.status == 200){
            // conversion en objet javascript
            let data = JSON.parse(this.responseText);

            alert(data.status + ' - ' + data.message)
        }

        if(this.status == 500){
            alert(this.responseText);
        }
    }

    // creation d'un objet à patir des données du formulaire
    let formData = new formData();

    // envoie de la requête
    ajax.send();

    // prevent default
    return false
}
// import axios from "axios";
function vehiculeSelected(id,detail){

    $.ajax({
        url: 'selecion-vehicule-'+id+'-'+detail,
        method : 'GET',
        dataType: 'json',

        success: function (data){


            console.log(data)
            let car = $('#listCar'+data.detail).text()
            console.log()

            let qte = parseInt($('#qte'+data.detail).text());
            let qteEnlevee = qte;
            //onclick="deleteRow(\''+data.immatriculation+'\','+data.capacite+'\)"
            if(qte != 0){
                let qte = parseInt($('#qte'+data.detail).text())-(data.capacite);
                // $('#listCar'+data.detail).append('<tr><td class="text-center"><input type="hidden" id="id[]" name="id[]" value="'+data.idCar+'">'+data.marque+'</td><td class="text-center">'+data.capacite+'</td><td class="text-center">'+data.immatriculation+'</td><td class="text-center"><a onclick="supprimerUneLigne('+data.capacite+','+data.detail+','+data.vehicule_id+','+qte+')"  class="btn btn-danger">x</a></td> <td style="display: none;"> '+data.vehicule_id+'</td></tr>')
                let table = []
                let lesInputs = document.getElementById('listCar'+data.detail)

                let lignes = lesInputs.getElementsByTagName('tr');

                // if(lignes.length == 0){
                //     $('#button'+data.detail).attr('disabled', true)
                //     console.log
                // }

                console.log(lignes)

                for(let i = 0; i<lignes.length; i++){
                    let lesCellules = lignes[i].getElementsByTagName('td')
                        table.push(lesCellules[2].textContent);
                }
                let cpt = 0;

                for (let index = 0; index < table.length; index++) {

                    if(table[index] == data.immatriculation)
                        cpt++
                }
                console.log(cpt)

                if(cpt >= 1){
                    console.log('existe deja')
                    $('.erreur').html('Vous avez déjà selectionné ce vehicule')
                    setTimeout(() => {
                        $('.erreur').html('')

                    }, 3000);
                    // lesInputs.deleteRow(-1)
                }else{
                    $('.erreur').html('')
                    if(qte < 0){
                        qteEnlevee = data.capacite + qte
                        console.log('moin')
                        $('#qte'+data.detail).html('')
                        $('#qte'+data.detail).html(0)
                        $('.qte'+data.detail).append('<h4 class="card-title mb-4">Quantité restant:'+0+'</h4>')
                    }else{
                        console.log('sup ou égale')
                        // $('#listCar'+data.detail).append('<tr><td class="text-center"><input type="hidden" id="id[]" name="id[]" value="'+data.idCar+'">'+data.marque+'</td><td class="text-center">'+data.capacite+'</td><td class="text-center">'+data.immatriculation+'</td><td class="text-center"><a onclick="supprimerUneLigne('+data.capacite+','+data.detail+','+data.vehicule_id+','+qte+')"  class="btn btn-danger">x</a></td> <td style="display: none;"> '+data.vehicule_id+'</td></tr>')
                        // $('#listCar'+data.detail).append('<tr><td class="text-center"><input type="hidden" id="id[]" name="id[]" value="'+data.idCar+'">'+data.marque+'</td><td class="text-center">'+data.capacite+'</td><td class="text-center">'+data.immatriculation+'</td><td class="text-center"><a onclick="supprimerUneLigne('+data.capacite+','+data.detail+','+data.vehicule_id+','+qte+')"  class="btn btn-danger">x</a></td> <td style="display: none;"> '+data.vehicule_id+'</td></tr>')
                        // = data.capacite;
                        $('#qte'+data.detail).html('')
                        $('#qte'+data.detail).html(qte)
                        $('.qte'+data.detail).append('<h4 class="card-title mb-4">Quantité restant:'+qte+'</h4>')
                    }
                    $('#listCar'+data.detail).append('<tr><td class="text-center"><input type="hidden" id="id[]" name="id[]" value="'+data.idCar+'">'+data.marque+'</td><td class="text-center">'+data.capacite+'</td><td class="text-center">'+data.immatriculation+'</td><td class="text-center"><a onclick="supprimerUneLigne('+data.capacite+','+data.detail+','+data.vehicule_id+','+qteEnlevee+')"  class="btn btn-danger">x</a></td> <td style="display: none;"> '+data.vehicule_id+'</td></tr>')
                    $('#button'+data.detail).attr('disabled', false);

                }
            }else{
                console.log('zero')
                $('#error'+data.detail).html('Il n\'y a plus produit disponible')
                setTimeout(() => {
                    $('#error'+data.detail).html('')

                }, 3000);
            }

        }
    })
}

function deleteRow(matricule,capacite){

    alert('ok');

    console.log(parseInt($('#qte').text())+capacite)

    let lesInputs = document.getElementById('listCar'+data.detail)

    let lignes = lesInputs.getElementsByTagName('tr');

    for(let i = 0; i<lignes.length; i++){
        let lesCellules = lignes[i].getElementsByTagName('td')
            if(lesCellules[2].textContent == matricule){
                lesInputs.deleteRow(i)
                let qte = parseInt($('#qte').text())+(capacite);
                $('#qte').html(qte)
            }
    }
    let car = $('#id[]                                                                                                                                                                                                                                                                                                                                                                                                                                                   ').val()
    console.log(matricule)

    //var nombreDeLignes = $('#table tbody tr').length;

    var ligne = $(this).closest('tr');
    //if(nombreDeLignes == 1) return;

    // Supprimer la ligne
    ligne.remove();
}

// $('#formulaire').on('submit', function(e) {
//     e.preventDefault();

//     const form = $(this);
//     const submitButton = form.find('button[type="submit"]');

//     // Désactive le bouton pendant la soumission
//     submitButton.prop('disabled', true);

//     let formData = new FormData(this);

//     $.ajax({
//         type: 'POST',
//         url: "/mis-a-jour",
//         data: formData,
//         contentType: false,
//         processData: false,
//         success: (response) => {
//             if (!response || !Array.isArray(response)) {
//                 showError('Format de réponse invalide');
//                 return;
//             }

//             // Mise à jour du montant total
//             const montantTotal = calculateTotal(response);
//             $('#montant_total').html(montantTotal.toLocaleString() + ' FCFA');

//             // Mise à jour de la liste des produits
//             updateProductList(response);
//         },
//         error: function(xhr) {
//             showError('Une erreur est survenue lors de la mise à jour du panier');
//             console.error('Erreur:', xhr);
//         },
//         complete: function() {
//             submitButton.prop('disabled', false);
//         }
//     });
// });
$('#mpMontantTotal').hide()
$('#mpRemise').hide()

function updateProductList(products) {
    const productList = $('#listProduit');
    productList.empty();

    products.forEach(product => {
        // Échappement des données sensibles
        const name = $('<div>').text(product.name).html();
        const image = $('<div>').text(product.options.image).html();

        const productHtml = `
            <tr class="pt-30">
                <td class="custome-checkbox pl-30"></td>

                <td class="image product-thumbnail pt-40">
                    <img src="/storage/${image}" alt="${name}">
                </td>

                <td class="product-des product-name">
                    <h6 class="mb-5">
                        <a class="product-name mb-10 text-heading" href="shop-product-right.html">
                            ${name}
                        </a>
                    </h6>
                    <div class="product-rate-cover">
                        <div class="product-rate d-inline-block">
                            <div class="product-rating" style="width:${product.options.note}%"></div>
                        </div>
                        <span class="font-small ml-5 text-muted">(${(product.options.note/10)})</span>
                    </div>
                </td>

                <td class="price" data-title="Prix">
                    <div class="mr-15">
                        <div class="detail-qty">
                            ${product.price.toLocaleString()} FCFA / ${product.options.unite}
                        </div>
                    </div>
                </td>

                <td class="text-center detail-info" data-title="Quantité">
                    <div class="detail-extralink mr-15">
                        <div class="detail-qty border radius">
                            <a href="#" class="qty-down"><i class="fi-rs-angle-small-down"></i></a>
                            <input type="text" name="qte[]" class="qty-val" value="${product.qty}" min="1">
                            <a href="#" class="qty-up"><i class="fi-rs-angle-small-up"></i></a>
                        </div>
                    </div>
                </td>

                <td class="price" data-title="Sous-total">
                    <div class="detail-extralink mr-15">
                        <div class="radius w-100 border-bleu">
                            <input type="text" id="montant" min="${product.price}"
                                name="montant[]" class="qty-val"
                                value="${(product.price * product.qty).toLocaleString()}" min="1">
                        </div>
                    </div>
                    <h4 class="text-brand">FCFA</h4>
                </td>

                <td class="action text-center" data-title="Supprimer">
                    <a href="#" class="text-body delete-product" data-id="${product.rowId}">
                        <i class="fi-rs-trash"></i>
                    </a>
                </td>
                <input type="hidden" name="rowId[]" value="${product.rowId}">
            </tr>
        `;
        productList.append(productHtml);
    });

    // Attacher les gestionnaires d'événements pour la suppression
    $('.delete-product').on('click', function(e) {
        e.preventDefault();
        if (confirm('Voulez vous supprimer ce produit?')) {
            deleteProduct($(this).data('id'));
        }
    });
}

function calculateTotal(products) {
    return products.reduce((total, product) => total + (product.price * product.qty), 0);
}

function showError(message) {
    // Affichage d'erreur via SweetAlert2 (repli sur alert() si Swal indisponible).
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: message,
            confirmButtonText: 'OK',
            confirmButtonColor: '#ea580c',
        });
    } else {
        alert(message);
    }
}

function deleteProduct(productId) {
    // Implémenter la logique de suppression
    $.post('/supprimer-produit', { id: productId })
        .then(() => {
            // Recharger le panier
            location.reload();
        })
        .catch(() => {
            showError('Erreur lors de la suppression du produit');
        });
}



// Liaison déléguée : le handler reste attaché même si le formulaire est (re)rendu
// après le chargement du script (robuste quel que soit l'ordre de chargement).
$(document).on('submit', '#formPoint', function(e) {
    e.preventDefault();

    const form = $(this);
    const submitButton = form.find('button[type="submit"]');

    // Désactive le bouton pendant la soumission
    // submitButton.prop('disabled', true);

    let formData = new FormData(this);

    $.ajax({
        type: 'POST',
        url: "/appliquer-point-de-reduction",
        data: formData,
        contentType: false,
        processData: false,
        success: (response) => {

            // console.log(formatNumber(response.montantEnleve),response.montantEnleve)
            // if (!response || !Array.isArray(response)) {
            //     showError('Format de réponse invalide');
            //     return;
            // }
            console.log('point',response)
            // console.log(response)
            // Échec : ancien format (-1) ou nouveau format ({statut:-1, message:'...'}).
            if(response == -1 || (response && response.statut == -1)){
                const messagePoint = (response && response.message) ? response.message : "Vous n'avez pas suffisamment de points.";
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Points fidélité',
                        text: messagePoint,
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#ea580c',
                    });
                } else {
                    showError(messagePoint);
                }
            }else{

                // $('#montantReductionParPoint').html('')
                // $('#montantReductionParPoint').append(`Montant reduit par point: <span class="fw-bold text-danger">${formatNumber(response.montantEnleve)} fcfa</span>`)
                // $('.modifie').show();
                //     setTimeout(() => {
                //         $('.modifie').hide();
                //     },3000)

                // cacher l'ancien montant ttc
                $('#mpMontantTTC').hide()

                // mise à jour de la tva (recalculée sur le HT net côté serveur)
                $('#mpTVA').html('');
                $('#mpTVA').html(formatNumber(response.tva));

                $('#mpMontantTotal').show()
                $('#leMontantTotal').html('');
                $('#leMontantTotal').html(formatNumber(response.total));

                $('#mpRemise').show()
                $('#laRemise').html('')
                $('#laRemise').text(formatNumber(response.montantEnleve))
                $('.modifie').show();
                setTimeout(() => {
                    $('.modifie').hide();
                },3000)

                if(response.total > 2000000) {
                    console.log("Montant TTC depasse 2battons"+ response.total);
                    $('#messageAlert').html('');
                    $('#messageAlert').html('Pour tout montant supérieur à 2 000 000 fcfa le paiement doit se faire par virement bancaire, en agence ou en plusieurs commandes.');
                } else {
                    console.log("Montant TTC inferieur ou egal à 2battons"+ response.total);
                    $('#messageAlert').html('');
                }

            }


        },
        error: function(xhr) {
            const messageErreurPoint = 'Connectez-vous avant de pouvoir appliquer les points';
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Points fidélité',
                    text: messageErreurPoint,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#ea580c',
                });
            } else {
                showError(messageErreurPoint);
            }
            console.error('Erreur:', xhr);
        },

    });
});


$(document).on('submit', '#formPromo', function(e) {
    e.preventDefault();

    const form = $(this);
    const submitButton = form.find('button[type="submit"]');

    // Désactive le bouton pendant la soumission
    // submitButton.prop('disabled', true);

    let formData = new FormData(this);

    $.ajax({
        type: 'POST',
        url: "/appliquer-code-promo",
        data: formData,
        contentType: false,
        processData: false,
        success: (response) => {
            // if (!response || !Array.isArray(response)) {
            //     showError('Format de réponse invalide');
            //     return;
            // }
            console.log('promo',response)

            // Échec : ancien format (-1) ou nouveau format ({statut:-1, message:'...'}).
            if (response == -1 || (response && response.statut == -1)) {
                const messagePromo = (response && response.message) ? response.message : 'Ce code promo est invalide.';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Code promo non valide',
                        text: messagePromo,
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#ea580c',
                    });
                } else {
                    showError(messagePromo);
                }

            }else{

                // cacher l'ancien montant ttc
                $('#mpMontantTTC').hide()

                // mise à jour de la tva
                $('#mpTVA').html('');
                $('#mpTVA').html(formatNumber(response.tva));

                // affichage du nouveau montant total
                $('#mpMontantTotal').show()
                $('#leMontantTotal').html('');
                $('#leMontantTotal').html(formatNumber(response.total));

                // affichage de la remise
                $('#mpRemise').show()
                $('#laRemise').html('')
                $('#laRemise').text(formatNumber(response.montantEnleve))

                // alert de succès code appliqué
                $('.modifie').show();
                setTimeout(() => {
                    $('.modifie').hide();
                },3000)

                // Affichage du message si le montant dépasse 2 000 000 fcfa
                if(response.total > 2000000) {
                    console.log("Montant TTC depasse 2battons"+ response.total);
                    $('#messageAlert').html('');
                    $('#messageAlert').html('Pour tout montant supérieur à 2 000 000 fcfa le paiement doit se faire par virement bancaire, en agence ou en plusieurs commandes.');
                } else {
                    console.log("Montant TTC inferieur ou egal à 2battons"+ response.total);
                    $('#messageAlert').html('');
                }
            }




        },
        error: function(xhr) {
            showError('Une erreur est survenue lors de la mise à jour du panier');
            console.error('Erreur:', xhr);
        },

    });
});




