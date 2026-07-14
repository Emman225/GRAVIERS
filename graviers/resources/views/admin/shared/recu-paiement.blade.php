@php
    // Variables attendues:
    // $titre, $sousTitre, $numeroRecu, $datePaiement, $beneficiaireNom, $beneficiaireRole
    // $beneficiaireContact, $modePaiement, $reference, $caissier, $libelle
    // $montant, $montantLabel
    // $contexteInfos (array de [label => valeur]), $resumeFinancier (array nullable)
    // $trancheNum, $trancheTotal
    // $retourUrl, $pdfUrl, $couleurPrincipale, $agenceLabel
    // $signatureGauche, $signatureDroite
    $couleur = $couleurPrincipale ?? '#1c57a3';
    $isPdf = false;
@endphp

@extends('layout.main')
@section('title', $titre . ' - ' . $numeroRecu)

@section('contenu')
    <div class="content-header d-print-none">
        <h2 class="content-title">{{ $titre }} {{ $numeroRecu }}</h2>
        <div>
            <a href="{{ $retourUrl }}" class="btn btn-light">
                <i class="material-icons md-arrow_back"></i> Retour
            </a>
            <button onclick="window.print()" class="btn btn-info">
                <i class="material-icons md-print"></i> Imprimer
            </button>
            <a href="{{ $pdfUrl }}" class="btn btn-primary">
                <i class="material-icons md-picture_as_pdf"></i> Télécharger PDF
            </a>
        </div>
    </div>

    @include('admin.shared._recu_paiement_styles')
    @include('admin.shared._recu_paiement_body')
@endsection
