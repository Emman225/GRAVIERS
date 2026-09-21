{{-- @dd($vehicules) --}}

@extends('layout.main')
@section('contenu')
@section('title', 'Modification de produit')


{{-- @dd($vehicules) --}}
<div class="container mt-60">
    <h1>Demandes de livraison</h1>

    {{-- L'ÉCHEC D'ENVOI DU CODE, ANNONCÉ.
         Clé propre : Flasher capte success/error/warning/info et les rejoue en
         bulle éphémère. Cet avertissement doit rester tant que le gestionnaire
         n'a pas agi. --}}
    @if (session('code_non_envoye'))
        <div class="alert alert-danger">
            <strong>Code de validation non transmis.</strong><br>
            {{ session('code_non_envoye') }}
        </div>
    @endif

    @if (session('code_renvoye'))
        <div class="alert alert-success">{{ session('code_renvoye') }}</div>
    @endif

    {{-- LES CODES DES COURSES EN COURS.
         Le code est le numéro de la course : le client le donne au livreur, qui
         le saisit pour clore la livraison. Il n'apparaissait nulle part au
         back-office — un courriel perdu et plus personne ne pouvait le
         retrouver, ni le dicter au téléphone, ni le renvoyer. --}}
    @php
        $coursesEnCours = collect();
        foreach ($livraisons->detailLivraison as $uneLigne) {
            foreach ($uneLigne->livraisons as $uneCourse) {
                if ((int) $uneCourse->accepte !== 3) {
                    $coursesEnCours->push([$uneLigne, $uneCourse]);
                }
            }
        }
    @endphp

    @if ($coursesEnCours->isNotEmpty())
        <div class="card border-secondary mb-4">
            {{-- PLUS DE CODE À L'ÉCRAN (10/09/2026) : le code de livraison est
                 envoyé au client, qui le lit sur Mon compte et dans l'application ;
                 le gestionnaire peut le lui renvoyer sans le voir. --}}
            <div class="card-header">Courses affectées — code de livraison envoyé au client</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Marchandise</th>
                            <th>Livreur</th>
                            <th class="text-end">&nbsp;</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($coursesEnCours as [$uneLigne, $uneCourse])
                            <tr>
                                <td>{{ ucfirst($uneLigne->nom_produit) }}</td>
                                <td>{{ $uneCourse->livreur?->user?->nom_prenoms ?? '—' }}</td>
                                <td class="text-end">
                                    <form action="{{ route('show.renvoyerCodeDemandeLivraison', $uneCourse) }}"
                                          method="post" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            Renvoyer le code
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ============================================================
         Bon de commande joint par le client à sa demande.

         Il était téléversé sans jamais être présenté ici : le
         gestionnaire traitait la demande sans pouvoir consulter la
         pièce que le client avait pourtant fournie — et que son
         compte à terme lui rendait obligatoire.

         Le fichier est servi par la route orders.fichierBlClient, déjà
         réservée aux profils Admin et Gestionnaire.
         ============================================================ --}}
    @if ($livraisons->blClient && $livraisons->blClient->fichier)
        @php
            $bonClient = $livraisons->blClient;
            $urlBon = route('orders.fichierBlClient', ['bl' => $bonClient->id, 'mode' => 'inline']);
            $urlBonTelecharger = route('orders.fichierBlClient', ['bl' => $bonClient->id, 'mode' => 'download']);
        @endphp

        <div class="card border-info mb-4">
            <div class="card-header bg-info text-dark d-flex justify-content-between align-items-center flex-wrap">
                <span>
                    <i class="material-icons md-attach_file align-middle"></i>
                    Bon de commande joint par le client
                    @if ($bonClient->numero)
                        — N° {{ $bonClient->numero }}
                    @endif
                </span>
                <div>
                    <a href="{{ $urlBon }}" target="_blank" class="btn btn-sm btn-light">
                        <i class="material-icons md-visibility align-middle"></i> Consulter
                    </a>
                    <a href="{{ $urlBonTelecharger }}" class="btn btn-sm btn-light">
                        <i class="material-icons md-cloud_download align-middle"></i> Télécharger
                    </a>
                </div>
            </div>
            {{-- PLUS D'APERÇU EN GRAND (10/09/2026) : le fichier occupait 520 px
                 au milieu de l'écran de traitement ; la ligne « Consulter /
                 Télécharger » suffit, le fichier s'ouvre dans un autre onglet. --}}
        </div>
    @endif

    @foreach ($livraisons->detailLivraison as $detail )
        {{-- TROIS CHIFFRES, TROIS RÉALITÉS DIFFÉRENTES.
             L'écran n'affichait que le reste à confier. Un article livré à 20
             sur 25 y ressemblait donc à un article dont on n'avait rien fait :
             le gestionnaire ne pouvait pas voir que la marchandise était partie
             et que le client l'avait reçue. --}}
        <div class="card border-secondary mb-2">
            <div class="card-body py-2 d-flex flex-wrap justify-content-between align-items-center">
                <span class="fw-bold">{{ ucfirst($detail->nom_produit) }}</span>
                <span>Demandé : <span class="fw-bold">{{ $detail->qte }}</span> {{ $detail->unite }}</span>
                <span>Livré :
                    <span class="fw-bold {{ $detail->qteLivree() > 0 ? 'text-success' : 'text-muted' }}">
                        {{ $detail->qteLivree() }}
                    </span>
                </span>
                <span>Confié aux camions : <span class="fw-bold">{{ $detail->qteAffectee() }}</span></span>
                <span>Reste à confier : <span class="fw-bold">{{ $detail->qteRestanteAAffecter() }}</span></span>
                @if ($detail->estEntierementLivree())
                    <span class="badge bg-success">Livré au client</span>
                @elseif ($detail->qteLivree() > 0)
                    <span class="badge bg-info text-dark">Livraison partielle</span>
                @endif
            </div>
        </div>

        @if ($detail->estEntierementAffectee())
            {{-- « Déjà traité » disait seulement que tout est CONFIÉ à des
                 camions — pas que le client a reçu quoi que ce soit. Le libellé
                 le dit maintenant. --}}
            <span class="text-white col-12 text-center bg-success h4">
                {{ ucfirst($detail->nom_produit) . ' : entièrement confié aux camions' }}
            </span><br><br>
        @else

        <h5 class="text-danger"> {{ucfirst($detail->nom_produit)}} </h5>
            <form action="{{route('show.traitementLivraison',['demandeLivraison' => $livraisons, 'detail' => $detail])}}" method="post" class="d-flex">
                @csrf
                <div class="card mx-auto me-5" style=" width: 40rem; ">
                    <div class="card-body">
                        @if (session('success'))
                            <div class="alert alert-success" id="notify">
                                {{ session('success') }}
                            </div>
                        @endif
                        <div>
                            {{-- data-demande : la quantite a confier au chargement.
                                 Le restant se recalcule a partir d'elle et de la
                                 somme des quantites saisies, pour rester juste
                                 meme quand le gestionnaire les modifie. --}}
                            <h4 class="card-title mb-4">Quantité restant: <span
                                    id="qte{{$detail->id}}"
                                    data-demande="{{ Help::qteDetaillivraisonRestante($detail) }}">{{ Help::qteDetaillivraisonRestante($detail) }}</span>
                            </h4>
                        </div>

                            {{-- PLUS DE DATE À SAISIR (10/09/2026). Le client choisit sa date de
                                 livraison en passant sa demande, et la course la reprend telle
                                 quelle ; la date que le gestionnaire tapait ici n'était jamais
                                 utilisée. Elle se lit, elle ne se ressaisit pas. --}}
                            <div class="mb-3">
                                <label class="form-label">Date de livraison demandée par le client</label>
                                <div class="form-control bg-light" id="dateLivraisonClient">
                                    {{ $livraisons->date_livraison ? \Help::dateHeure($livraisons->date_livraison) : '—' }}
                                </div>
                                <span class="text-danger">
                                    @error('prix')
                                        {{ $message }}
                                    @enderror
                                </span>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Vehicule : <span class="text-danger">*</span></label>
                                {{-- UNE LISTE VIDE NE DIT RIEN.
                                     Quand tous les camions sont pris, le champ
                                     s'affichait vide, sans un mot : impossible de
                                     savoir si c'etait une panne, un droit manquant
                                     ou simplement des camions occupes. --}}
                                @php $vehiculesLibres = $vehicules->where('disponible', true); @endphp

                                @if ($vehiculesLibres->isEmpty())
                                    <div class="alert alert-warning mb-0">
                                        <strong>Aucun véhicule disponible.</strong><br>
                                        Tous les camions sont engagés sur une course en cours. Un camion
                                        redevient disponible dès que sa course est clôturée par le livreur.
                                    </div>
                                @else
                                <select class="form-control" name="matricule" multiple style="height: 150px" id="">
                                    {{-- <option value="">Selectionner un vehicule</option> --}}
                                    @foreach ($vehicules as $vehicule)
                                    @if ($vehicule->disponible == true )
                                        <option onclick="vehiculeSelected({{ $vehicule->id }},{{$detail->id}})" value="{{ $vehicule->matricule }}">
                                            {{ $vehicule->marque .
                                                ' | ' .
                                                $vehicule->capacite .
                                                ' | ' .
                                                $vehicule->livreur?->user?->nom_prenoms .
                                                ' | ' .
                                                $vehicule->livreur?->user?->contact }}
                                        </option>
                                    @endif
                                    @endforeach


                                </select>
                                @endif
                                <span class="text-danger">
                                    @error('prix')
                                        {{ $message }}
                                    @enderror
                                </span>
                            </div>

                            {{-- <div class="mb-4">
                                <button type="submit" class="btn btn-primary">Valider</button>
                            </div> --}}

                            <!-- form-group// -->
                            <div class="erreur text-danger text-center fw-bold" id="error{{$detail->id}}" ></div>
                    </div>
                </div>
                <div class="card mx-auto ms-5" style=" width: 40rem; ">
                    <div class="card-body">
                        @if (session('success'))
                            <div class="alert alert-success" id="notify">
                                {{ session('success') }}
                            </div>
                        @endif

                        <h4 class="card-title mb-4">Véhicule sélectionnés </h4>

                        <div class="table table-striped">
                            <table class="table table-striped" id="table">
                                <thead>
                                    <th class="text-center">marque</th>
                                    <th class="text-center">Capacité</th>
                                    <th class="text-center">Matricule</th>
                                    {{-- La quantite confiee a CE camion. Pre-remplie avec sa
                                         capacite : ne pas y toucher revient au comportement
                                         d'avant. Au-dela, le systeme compte les voyages. --}}
                                    <th class="text-center">Quantité</th>
                                    <th class="text-center">Action</th>
                                </thead>
                                <tbody id="listCar{{$detail->id}}">


                                </tbody>
                            </table>
                        </div>
                        <div class="container">
                            <button type="submit" id="button{{$detail->id}}" disabled class="btn btn-primary"> Valider </button>
                        </div>


                    </div>
                </div>
            </form>
        @endif

    @endforeach
</div>
@endsection

@section('jsParts')
<script type="text/javascript">

function supprimerUneLigne(capacite,detail,vehicule,qteEnleve){

    // console.log(capacite,detail,immatriculation)


    console.log(parseInt($('#qte'+detail).text())+capacite)

    let lesInputs = document.getElementById('listCar'+detail)

    let lignes = lesInputs.getElementsByTagName('tr');

    for(let i = 0; i<lignes.length; i++){
    let lesCellules = lignes[i].getElementsByTagName('td')
        // La cellule cachee portant l'identifiant du vehicule est passee en
        // 6e position : la colonne « Quantite » s'est intercalee avant elle.
        if(lesCellules[5].textContent == vehicule){
            lesInputs.deleteRow(i)
            // Le restant se relit sur les quantites encore saisies, plutot que
            // de se deviner : une ligne retiree, et il remonte de lui-meme.
            recalculerRestantLivraison(detail)
        }
    }

    // let lesInputs = document.getElementById('listCar'+detail)

    // let lignes = lesInputs.getElementsByTagName('tr');

    if(lignes.length == 0){
        $('#button'+detail).attr('disabled', true)
        console.log('cest le cas')
    }
    // let car = $('#id[]                                                                                                                                                                                                                                                                                                                                                                                                                                                   ').val()
    // console.log(matricule)

    //var nombreDeLignes = $('#table tbody tr').length;

    //var ligne = $(this).closest('tr');
    //if(nombreDeLignes == 1) return;

    // Supprimer la ligne
   // ligne.remove();:

}


</script>
@endsection
