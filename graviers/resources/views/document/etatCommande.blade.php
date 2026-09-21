@php
    use Illuminate\Support\Carbon;
    $config = App\Models\Configuration::first();
    $fne_numero = 'ETAT-CMD-' . date('YmdHis');
    // Le bloc client est celui du titulaire du compte (08/09/2026) : l'état
    // sortait « Nom : Rapport » et des champs vides.
    $fne_client = \App\Services\FneService::blocClient($client ?? null);
    $fne_qr_code = '';
    $fne_date = now()->format('d/m/Y H:i:s');
@endphp
@include('document.layouts._fne_init')

@extends('document.layouts.fne_base')

@section('titre', 'État des commandes')
@section('type_document', 'État des commandes')

@section('articles')
    <table class="fne-articles">
        <thead>
            <tr>
                <th style="width:15%">N° Commande</th>
                <th style="width:30%">Produits commandés</th>
                <th style="width:15%; text-align:right">Total à payer</th>
                <th style="width:22%">Paiement</th>
                <th style="width:18%">Date de commande</th>
            </tr>
        </thead>
        <tbody>
            @foreach($commandes as $commande)
                <tr>
                    <td>{{ $commande->numero }}</td>
                    <td>
                        @foreach($commande->detailCommande as $detail)
                            - {{ ucfirst($detail->produit?->nom) }}<br>
                        @endforeach
                    </td>
                    @php
                        // LE PAIEMENT SE LIT SUR LES RÈGLEMENTS, pas sur `statut`.
                        // Cette colonne testait commande.statut — le drapeau actif /
                        // inactif, qui vaut 1 pour toute commande vivante — d'où
                        // « Aucun paiement effectué » sur chaque ligne, même soldée.
                        $totalDu  = $commande->montantAPayer();
                        $paye     = $commande->montantPayeComptant();
                        $reste    = $commande->montantRestantDu();
                    @endphp
                    <td style="text-align:right">{{ number_format($totalDu, 0, '', ' ') }} fcfa</td>
                    <td>
                        @if ($commande->etat_commande === \Help::$AFFAIRE_ANNULEE)
                            <span style="color:gray;">Commande annulée</span>
                        @elseif ($totalDu > 0 && $reste < 1)
                            <span style="color:green;">Paiement soldé</span>
                        @elseif ($paye >= 1)
                            <span style="color:orange;">Paiement en cours — {{ number_format($paye, 0, '', ' ') }} fcfa payés, reste {{ number_format($reste, 0, '', ' ') }} fcfa</span>
                        @else
                            <span style="color:red;">Aucun paiement effectué</span>
                        @endif
                    </td>
                    <td>{{ Carbon::parse($commande->created_at)->format('d-m-Y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection

@section('totaux')
    <table class="fne-totaux-outer"><tr><td class="fne-totaux-spacer"></td><td class="fne-totaux-content"><table class="fne-totaux">
        <tr>
            <td class="label" style="font-size:10pt;">TOTAL DE TOUTES LES COMMANDES</td>
            <td class="valeur" style="font-size:10pt; font-weight:bold;">{{ number_format($commandes->sum(fn ($c) => $c->montantAPayer()), 0, '', ' ') }} fcfa</td>
        </tr>
    </table></td></tr></table>
@endsection

@section('resume_fiscal')
@endsection
