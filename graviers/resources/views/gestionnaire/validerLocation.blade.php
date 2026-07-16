@php use Illuminate\Support\Carbon; @endphp
@extends('layout.main')
@section('title','Valider une location')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Valider la location {{ $location->numero }}</h2>
        <a href="{{ route('show.listeLocationEnAttente') }}" class="btn btn-outline-secondary btn-sm">Retour à la liste</a>
    </div>

    @if (isset($errors) && $errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <p class="mb-1"><strong>Client :</strong> {{ $location->client?->nom }} {{ $location->client?->prenom }}</p>
                    <p class="mb-1"><strong>Date location :</strong> {{ $location->date_location ? Carbon::parse($location->date_location)->format('d/m/Y') : '-' }}</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-1"><strong>Montant (HT) :</strong> {{ number_format($location->montant_total, 0, ',', ' ') }} fcfa</p>
                    <p class="mb-1"><strong>État :</strong> <span class="badge bg-warning text-dark">{{ $location->etatLibelle() }}</span></p>
                </div>
            </div>

            <div class="table-responsive mb-4">
                <table class="table table-bordered">
                    <thead style="background-color:#1c57a3;color:#fff;">
                        <tr>
                            <th>Matériel</th><th class="text-center">Qté</th>
                            <th class="text-center">Du</th><th class="text-center">Au</th>
                            <th class="text-center">Nb jours</th><th class="text-end">Prix</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($location->detailLocation as $d)
                            <tr>
                                <td>{{ $d->produit?->nom ?? '-' }}</td>
                                <td class="text-center">{{ $d->qte }}</td>
                                <td class="text-center">{{ $d->debut ? Carbon::parse($d->debut)->format('d/m/Y') : '-' }}</td>
                                <td class="text-center">{{ $d->fin ? Carbon::parse($d->fin)->format('d/m/Y') : '-' }}</td>
                                <td class="text-center">{{ $d->nombre_jour }}</td>
                                <td class="text-end">{{ number_format($d->prix, 0, ',', ' ') }} fcfa</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @php
                // Mode pré-coché depuis le choix fait par le client au checkout.
                $modeCourant = old('mode_livraison', $location->est_livrable ? 'livraison' : 'retrait');
            @endphp

            <form method="POST" action="{{ route('show.validerLocation', $location) }}" class="row gx-3">
                @csrf

                <div class="col-12 mb-3">
                    <label class="form-label">Mode de récupération</label>
                    <div class="d-flex flex-wrap" style="gap:1.5rem">
                        <div class="form-check">
                            <input class="form-check-input mode-livraison-radio" type="radio" name="mode_livraison"
                                   value="livraison" id="modeLivraison" {{ $modeCourant === 'livraison' ? 'checked' : '' }}>
                            <label class="form-check-label" for="modeLivraison">
                                Livraison <small class="text-muted">— un livreur apporte le matériel</small>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input mode-livraison-radio" type="radio" name="mode_livraison"
                                   value="retrait" id="modeRetrait" {{ $modeCourant === 'retrait' ? 'checked' : '' }}>
                            <label class="form-check-label" for="modeRetrait">
                                Retrait sur place <small class="text-muted">— le client vient chercher le matériel</small>
                            </label>
                        </div>
                    </div>
                    <small class="text-muted">
                        Choix du client à la commande :
                        <strong>{{ $location->est_livrable ? 'Me faire livrer' : 'Retrait sur place' }}</strong>.
                        Modifiable ici si besoin.
                    </small>
                </div>

                <div class="col-md-4 mb-3 champ-livraison">
                    <label class="form-label">Livreur <span class="text-danger">*</span></label>
                    <select name="livreur" class="form-control">
                        <option value="">-- Sélectionner --</option>
                        @foreach ($livreurs as $l)
                            <option value="{{ $l->id }}" {{ old('livreur') == $l->id ? 'selected' : '' }}>{{ $l->user?->nom_prenoms ?? ('Livreur #'.$l->id) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3 champ-livraison">
                    <label class="form-label">Véhicule <span class="text-danger">*</span></label>
                    <select name="vehicule" class="form-control">
                        <option value="">-- Sélectionner --</option>
                        @foreach ($vehicules as $v)
                            <option value="{{ $v->id }}" {{ old('vehicule') == $v->id ? 'selected' : '' }}>{{ $v->nom }} @if($v->immatriculation) - {{ $v->immatriculation }} @endif</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Caution (fcfa)</label>
                    <input type="number" name="caution" min="0" step="1" class="form-control" value="{{ old('caution', $cautionSuggeree ?? 0) }}">
                    @if (($cautionSuggeree ?? 0) > 0)
                        <small class="text-muted">Suggérée d'après les produits : {{ number_format($cautionSuggeree, 0, ',', ' ') }} fcfa (modifiable).</small>
                    @endif
                </div>

                <div class="col-12">
                    <div class="alert alert-info py-2 d-none" id="infoRetrait">
                        <i class="material-icons md-store align-middle"></i>
                        Retrait sur place : aucun livreur ni véhicule ne sera affecté, aucune livraison ne sera créée.
                        Remettez le matériel au client à l'agence, puis clôturez via « Retour du matériel ».
                    </div>
                    <button type="submit" class="btn btn-primary" id="btnValiderLocation">
                        <i class="material-icons md-check align-middle"></i> <span id="libelleBtnValider">Valider &amp; affecter</span>
                    </button>
                </div>
            </form>

            <script>
                (function () {
                    var radios   = document.querySelectorAll('.mode-livraison-radio');
                    var champs   = document.querySelectorAll('.champ-livraison');
                    var selects  = document.querySelectorAll('.champ-livraison select');
                    var radioRet = document.getElementById('modeRetrait');
                    var info     = document.getElementById('infoRetrait');
                    var libelle  = document.getElementById('libelleBtnValider');
                    var bouton   = document.getElementById('btnValiderLocation');

                    function appliquerMode() {
                        var retrait = radioRet.checked;
                        champs.forEach(function (c) { c.classList.toggle('d-none', retrait); });
                        info.classList.toggle('d-none', !retrait);
                        libelle.textContent = retrait ? 'Valider le retrait' : 'Valider & affecter';
                        // On retire aussi l'attribut required : un champ « required » masqué
                        // bloque l'envoi du formulaire sans afficher de message au gestionnaire.
                        selects.forEach(function (s) {
                            if (retrait) { s.value = ''; s.removeAttribute('required'); }
                            else { s.setAttribute('required', 'required'); }
                        });
                    }

                    radios.forEach(function (r) { r.addEventListener('change', appliquerMode); });
                    appliquerMode();

                    bouton.addEventListener('click', function (e) {
                        var msg = radioRet.checked
                            ? 'Valider cette location en RETRAIT SUR PLACE ? Aucun livreur ne sera affecté.'
                            : 'Valider cette location, créer la livraison et affecter le livreur ?';
                        if (!confirm(msg)) e.preventDefault();
                    });
                })();
            </script>
        </div>
    </div>
@endsection
