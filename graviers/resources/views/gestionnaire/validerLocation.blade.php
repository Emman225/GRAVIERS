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
                    <p class="mb-1"><strong>Client :</strong> {{ $location->client?->display_name }}</p>
                    <p class="mb-1"><strong>Date location :</strong> {{ $location->date_location ? \Help::dateHeure($location->date_location) : '-' }}</p>
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
                                <td class="text-center">{{ $d->debut ? \Help::dateHeure($d->debut) : '-' }}</td>
                                <td class="text-center">{{ $d->fin ? \Help::dateHeure($d->fin) : '-' }}</td>
                                <td class="text-center">{{ $d->nombre_jour }}</td>
                                <td class="text-end">{{ number_format($d->prix, 0, ',', ' ') }} fcfa</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @php
                // LE MODE VIENT DU CLIENT, IL NE SE CHOISIT PLUS ICI.
                //
                // L'écran proposait deux cases à cocher : le gestionnaire pouvait
                // contredire son client sans le voir. Il CONSTATE désormais.
                //
                // La règle vit sur le modèle — l'écran et le contrôleur la lisent
                // au même endroit, ils ne peuvent plus diverger.
                $estRetrait = $location->estRetraitSurPlace();
            @endphp

            <form method="POST" action="{{ route('show.validerLocation', $location) }}" class="row gx-3" id="formValiderLocation"
                  data-retrait="{{ $estRetrait ? '1' : '0' }}">
                @csrf

                <div class="col-12 mb-3">
                    <label class="form-label">Mode de récupération</label>
                    <div class="alert alert-light border mb-0 py-2 d-flex align-items-center">
                        <i class="fi-rs-{{ $estRetrait ? 'shop' : 'truck-side' }} me-2"></i>
                        <span>
                            <strong>{{ $location->libelleModeRecuperation() }}</strong>
                            <small class="text-muted">
                                — {{ $estRetrait
                                    ? 'le client vient chercher le matériel'
                                    : 'un livreur apporte le matériel' }},
                                choisi par le client à la commande.
                            </small>
                        </span>
                    </div>
                </div>

                {{-- OÙ LIVRER — DIT AVANT, ET NON APRÈS.
                     Le gestionnaire ne voyait nulle part l'adresse de la
                     location. Il affectait un livreur à une location qui n'en
                     portait aucune, et le livreur lisait « Lieu : null » sur
                     son mobile — sans savoir où aller, ni qui appeler. --}}
                @php
                    $adresseLocation = \App\Models\AdresseLivraison::find($location->adresse_livraison_id);
                @endphp
                <div class="col-12 mb-3 champ-livraison">
                    @if ($adresseLocation)
                        <label class="form-label">Adresse de livraison</label>
                        <div class="alert alert-light border mb-0 py-2">
                            <i class="fi-rs-marker me-1"></i>
                            {{ $adresseLocation->affichage ?: $adresseLocation->complement_adresse ?: 'Adresse enregistrée sans libellé' }}
                            @if ($adresseLocation->ville)
                                <small class="text-muted">— {{ $adresseLocation->ville->libelle ?? '' }}</small>
                            @endif
                        </div>
                    @else
                        <div class="alert alert-warning mb-0 py-2">
                            <strong>Cette location est à livrer, mais ne porte aucune adresse.</strong>
                            Faites enregistrer l'adresse de livraison par le client avant de lui
                            affecter un livreur : un livreur envoyé sans adresse ne sait pas où
                            aller, et sa rémunération se calcule sur une distance nulle.
                        </div>
                    @endif
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

{{-- LE FOURNISSEUR DE CHAQUE MATÉRIEL — EXIGÉ DANS LES DEUX MODES.
                     Sans lui, aucun bon d'enlèvement ne peut être émis.
                     Ce bloc portait « champ-livraison » : le script le masquait
                     et VIDAIT ses sélecteurs dès qu'on choisissait le retrait.
                     C'était juste tant que le retrait ne produisait aucun bon.
                     Depuis qu'il en produit un, le fournisseur est indispensable
                     dans les deux cas — c'est CHEZ LUI que le client va retirer,
                     et c'est lui qui validera la quantité remise. --}}
                <div class="col-12 mb-3">
                    <label class="form-label">
                        Fournisseur de chaque matériel <span class="text-danger">*</span>
                    </label>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-1">
                            <thead>
                                <tr>
                                    <th>Matériel</th>
                                    <th class="text-center" style="min-width:9rem">
                                        Quantité remise <span class="text-danger">*</span>
                                    </th>
                                    <th class="text-center">Jours</th>
                                    <th style="min-width:16rem">Fournisseur qui remet le matériel</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($location->detailLocation as $ligne)
                                    @php $offres = $fournisseursParLigne[$ligne->id] ?? collect(); @endphp
                                    <tr>
                                        <td>{{ optional($ligne->produit)->nom ?? '—' }}</td>
                                        <td class="text-center">
                                            {{-- LA QUANTITÉ RÉELLEMENT REMISE.
                                                 Elle était figée sur la quantité commandée. Le
                                                 fournisseur ne remet pourtant pas toujours ce qui
                                                 a été demandé — l'écran de vente laisse déjà
                                                 l'ajuster. C'est cette valeur qui fait la dette du
                                                 fournisseur, elle doit donc être la vraie. --}}
                                            <input type="number" step="0.01" min="0.01"
                                                   name="qte[{{ $ligne->id }}]"
                                                   class="form-control text-center"
                                                   value="{{ old('qte.' . $ligne->id, (float) $ligne->qte) }}"
                                                   required />
                                            <small class="text-muted">
                                                commandé : {{ rtrim(rtrim(number_format((float) $ligne->qte, 2, ',', ' '), '0'), ',') }}
                                            </small>
                                        </td>
                                        <td class="text-center">{{ (int) ($ligne->nombre_jour ?? 1) }}</td>
                                        <td>
                                            @if ($offres->isEmpty())
                                                <span class="text-danger small">
                                                    Aucun fournisseur ne porte ce matériel avec un prix d'achat.
                                                    Renseignez-le dans le stock avant de valider.
                                                </span>
                                            @else
                                                <select name="fournisseur[{{ $ligne->id }}]"
                                                        class="form-control select-fournisseur">
                                                    <option value="">-- Sélectionner --</option>
                                                    @foreach ($offres as $offre)
                                                        <option value="{{ $offre->fournisseur_id }}"
                                                            {{ old('fournisseur.' . $ligne->id) == $offre->fournisseur_id ? 'selected' : '' }}>
                                                            {{ optional(optional($offre->fournisseur)->user)->nom_prenoms ?? ('Fournisseur #' . $offre->fournisseur_id) }}
                                                            — {{ number_format((float) $offre->prix, 0, ',', ' ') }} fcfa/jour
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <small class="text-muted">
                        Le bon d'enlèvement portera le prix d'achat de ce fournisseur,
                        multiplié par la quantité et par le nombre de jours.
                    </small>
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
                    var champs   = document.querySelectorAll('.champ-livraison');
                    // Ne concerne plus que le livreur et le véhicule : les
                    // sélecteurs de fournisseur ont quitté « champ-livraison ».
                    var selects  = document.querySelectorAll('.champ-livraison select');
                    // Le mode ne se choisit plus : il est rendu par le serveur.
                    var estRetrait = document.getElementById('formValiderLocation')
                        .dataset.retrait === '1';
                    var info     = document.getElementById('infoRetrait');
                    var libelle  = document.getElementById('libelleBtnValider');

                    function appliquerMode() {
                        var retrait = estRetrait;
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

                    appliquerMode();

                    // Confirmation SweetAlert2 (remplace le confirm() natif). Le submit est
                    // bloqué jusqu'à confirmation, puis relancé avec un drapeau anti-reboucle.
                    var form = document.getElementById('formValiderLocation');
                    form.addEventListener('submit', function (e) {
                        if (form.dataset.confirmed === '1') return;
                        e.preventDefault();
                        var retrait = estRetrait;
                        if (typeof Swal === 'undefined') { form.dataset.confirmed = '1'; form.submit(); return; }
                        Swal.fire({
                            title: retrait ? 'Valider en retrait sur place ?' : 'Valider et affecter le livreur ?',
                            html: retrait
                                ? 'Aucun livreur ni véhicule ne sera affecté, <b>aucune livraison</b> ne sera créée.<br>'
                                    + 'La location passera à l\'état <b>EN COURS</b>.'
                                : 'La livraison sera créée, le livreur affecté.<br>'
                                    + 'La location passera à l\'état <b>EN COURS</b>.',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: retrait ? 'Oui, valider le retrait' : 'Oui, valider & affecter',
                            cancelButtonText: 'Annuler',
                            confirmButtonColor: '#0d6efd',
                            cancelButtonColor: '#6c757d',
                        }).then(function (result) {
                            if (result.isConfirmed) {
                                form.dataset.confirmed = '1';
                                form.submit();
                            }
                        });
                    });
                })();
            </script>
        </div>
    </div>
@endsection
