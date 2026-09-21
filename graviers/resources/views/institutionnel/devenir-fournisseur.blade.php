@extends('client.main')
@section('title','Devenir fournisseur')

@section('cssPart')
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/pages-institutionnelles.css') }}">
@endsection

@section('content')
@include('client.navMobile')

<main class="page-inst">

    @include('institutionnel._entete', [
        'icone'    => 'store',
        'chip'     => 'Partenariat',
        'titre'    => 'Devenir <em>fournisseur</em>',
        'accroche' => 'Carrière, cimenterie, briqueterie, aciérie ou négociant : proposez vos
                       matériaux sur la plateforme et accédez à une clientèle de chantiers.',
    ])

    <div class="page-inst__corps">

        <div class="page-inst__carte">
            <h2><i class="material-icons md-star"></i> Ce que vous y gagnez</h2>
            <div class="page-inst__grille">
                <div class="page-inst__tuile">
                    <i class="material-icons md-groups"></i>
                    <strong>Une clientèle qualifiée</strong>
                    <span>Particuliers et entreprises du bâtiment, déjà en recherche de matériaux.</span>
                </div>
                <div class="page-inst__tuile">
                    <i class="material-icons md-assignment"></i>
                    <strong>Vos stocks maîtrisés</strong>
                    <span>Vous déclarez vos quantités et vos prix ; le catalogue se met à jour.</span>
                </div>
                <div class="page-inst__tuile">
                    <i class="material-icons md-local_shipping"></i>
                    <strong>La logistique prise en charge</strong>
                    <span>Le transport jusqu'au chantier est organisé par notre réseau de livreurs.</span>
                </div>
                <div class="page-inst__tuile">
                    <i class="material-icons md-account_balance_wallet"></i>
                    <strong>Un suivi de compte transparent</strong>
                    <span>Enlèvements, montants dus et règlements sont tracés et consultables.</span>
                </div>
            </div>
        </div>

        <div class="page-inst__carte">
            <h2><i class="material-icons md-playlist_add_check"></i> Ce qu'il faut réunir</h2>
            <ul>
                <li>Une entreprise enregistrée : registre du commerce (RCCM) et numéro de compte contribuable (NCC).</li>
                <li>Une capacité d'approvisionnement régulière sur au moins un matériau : sable, gravier, ciment, fer, briques…</li>
                <li>Un site d'enlèvement accessible aux camions.</li>
                <li>Vos coordonnées bancaires ou mobile money pour le règlement.</li>
                <li>Une personne de contact joignable pendant les heures ouvrables.</li>
            </ul>
        </div>

        <div class="page-inst__carte">
            <h2><i class="material-icons md-timeline"></i> Comment ça se passe</h2>
            <ol class="page-inst__etapes">
                <li>
                    <strong>Vous nous écrivez</strong>
                    Présentez votre activité, vos matériaux et vos capacités mensuelles.
                </li>
                <li>
                    <strong>Nous étudions votre dossier</strong>
                    Vérification des pièces de l'entreprise et visite du site d'enlèvement.
                </li>
                <li>
                    <strong>Vos conditions sont fixées</strong>
                    Prix d'achat, volumes et modalités de règlement sont convenus ensemble.
                </li>
                <li>
                    <strong>Vos produits sont en ligne</strong>
                    Votre espace fournisseur vous permet de suivre les enlèvements, les bons de
                    commande et votre solde.
                </li>
            </ol>
        </div>

        <div class="page-inst__cta">
            <h2><i class="material-icons md-send"></i> Proposer mes matériaux</h2>
            <p>
                Écrivez-nous en précisant vos matériaux, vos capacités mensuelles et la localisation
                de votre site. Nous revenons vers vous pour la suite.
            </p>
            <a class="page-inst__bouton" href="{{ route('contact', ['sujet' => 'Candidature fournisseur']) }}">Déposer ma candidature</a>
            <a class="page-inst__bouton page-inst__bouton--clair" href="{{ route('devenirLivreur') }}">Devenir livreur</a>
        </div>

    </div>
</main>

@endsection
