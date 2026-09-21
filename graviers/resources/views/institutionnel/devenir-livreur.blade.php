@extends('client.main')
@section('title','Devenir livreur')

@section('cssPart')
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/pages-institutionnelles.css') }}">
@endsection

@section('content')
@include('client.navMobile')

<main class="page-inst">

    @include('institutionnel._entete', [
        'icone'    => 'local_shipping',
        'chip'     => 'Partenariat',
        'titre'    => 'Devenir <em>livreur</em>',
        'accroche' => 'Vous disposez d\'un camion-benne ou d\'un porteur ? Rejoignez notre réseau
                       de transporteurs et recevez des courses régulières sur Abidjan et ses environs.',
    ])

    <div class="page-inst__corps">

        <div class="page-inst__carte">
            <h2><i class="material-icons md-star"></i> Ce que vous y gagnez</h2>
            <div class="page-inst__grille">
                <div class="page-inst__tuile">
                    <i class="material-icons md-event"></i>
                    <strong>Des courses régulières</strong>
                    <span>Les livraisons vous sont affectées depuis notre plateforme, sans démarchage de votre part.</span>
                </div>
                <div class="page-inst__tuile">
                    <i class="material-icons md-payments"></i>
                    <strong>Un paiement à échéance connue</strong>
                    <span>Vos courses sont cumulées et réglées selon une fréquence convenue à l'avance.</span>
                </div>
                <div class="page-inst__tuile">
                    <i class="material-icons md-phone_iphone"></i>
                    <strong>Une application dédiée</strong>
                    <span>Vos courses, vos itinéraires et vos gains, suivis depuis votre téléphone.</span>
                </div>
                <div class="page-inst__tuile">
                    <i class="material-icons md-receipt_long"></i>
                    <strong>Des comptes clairs</strong>
                    <span>Chaque course livrée est tracée, et votre solde est consultable à tout moment.</span>
                </div>
            </div>
        </div>

        <div class="page-inst__carte">
            <h2><i class="material-icons md-playlist_add_check"></i> Ce qu'il faut réunir</h2>
            <ul>
                <li>Un véhicule adapté au transport de matériaux : camion-benne, porteur ou utilitaire.</li>
                <li>Les documents du véhicule à jour : carte grise, visite technique, assurance.</li>
                <li>Un permis de conduire valide et l'expérience de la conduite en charge.</li>
                <li>Un téléphone permettant d'utiliser notre application de livraison.</li>
                <li>Une pièce d'identité et vos coordonnées de règlement.</li>
            </ul>
        </div>

        <div class="page-inst__carte">
            <h2><i class="material-icons md-timeline"></i> Comment ça se passe</h2>
            <ol class="page-inst__etapes">
                <li>
                    <strong>Vous nous écrivez</strong>
                    Présentez votre véhicule, votre zone d'intervention et vos disponibilités.
                </li>
                <li>
                    <strong>Nous étudions votre dossier</strong>
                    Nous vérifions les documents du véhicule et convenons d'un rendez-vous.
                </li>
                <li>
                    <strong>Votre compte est ouvert</strong>
                    Nous créons votre accès et vous transmettons l'application de livraison.
                </li>
                <li>
                    <strong>Vous recevez vos premières courses</strong>
                    Les livraisons vous sont affectées ; vous confirmez chaque réception par le code
                    remis par le client.
                </li>
            </ol>
        </div>

        <div class="page-inst__cta">
            <h2><i class="material-icons md-send"></i> Rejoindre le réseau</h2>
            <p>
                Écrivez-nous en précisant le type de véhicule, sa capacité en tonnes et votre zone
                d'intervention. Nous revenons vers vous pour la suite.
            </p>
            <a class="page-inst__bouton" href="{{ route('contact', ['sujet' => 'Candidature livreur']) }}">Déposer ma candidature</a>
            <a class="page-inst__bouton page-inst__bouton--clair" href="{{ route('devenirFournisseur') }}">Devenir fournisseur</a>
        </div>

    </div>
</main>

@endsection
