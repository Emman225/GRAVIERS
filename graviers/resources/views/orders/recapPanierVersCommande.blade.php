@php
    use Illuminate\Support\Carbon;
    $config = App\Models\Configuration::first();

    $fne_config = [
        'ncc' => $config->ncc ?? '',
        'regime_imposition' => $config->regime_imposition ?? '',
        'centre_impots' => $config->centre_impots ?? '',
        'rccm' => $config->rccm ?? '',
        'ref_bancaires' => $config->ref_bancaires ?? '',
        'nom_etablissement' => $config->nom_etablissement ?? '',
        'adresse_siege' => $config->adresse_siege ?? '',
        'telephone' => $config->telephone ?? '',
        'email_entreprise' => $config->email_entreprise ?? '',
        'nom_pdv' => $config->nom_pdv ?? '',
        'capital_social' => $config->capital_social ?? '',
        'cnps' => $config->cnps ?? '',
    ];

    $fne_numero = 'PRO-' . date('YmdHis');
    $fne_date = now()->format('d/m/Y H:i:s');

    $fne_client = [
        'nom' => ucfirst($client->nom ?? '') . ' ' . ucfirst($client->prenom ?? ''),
        'adresse' => $lieu ?? '',
        'ncc' => $client->ncc_clt ?? '',
        'regime_imposition' => '',
    ];

@endphp

@session('mode')
@php
    $modeObj = App\Models\ModePaiement::find(session('mode'));
@endphp
@endsession

@extends('document.layouts.fne_base')

@section('titre', 'Récapitulatif de commande')
@section('type_document', 'Proforma')

@if(isset($mode) && $mode)
    @section('mode_paiement', $mode->libelle ?? 'N/A')
@endif

@if($lieu)
    @section('adresse_livraison', ucwords($lieu))
@endif

@section('articles')
    <table class="fne-articles">
        <thead>
            <tr>
                <th class="col-ref">Réf</th>
                <th class="col-designation">Désignation</th>
                <th class="col-pu">P.U HT</th>
                <th class="col-qte">Qté</th>
                <th class="col-unite">Unité</th>
                <th class="col-taxes">Taxes (%)</th>
                <th class="col-rem">Rem. (%)</th>
                <th class="col-montant">Montant HT</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalHT = 0;
                $totalTVA_montant = 0;
                $livraison = 0;
                $remise = session('remise') ?? 0;
                $index = 0;
            @endphp

            @foreach (Cart::content() as $produit)
                @php
                    $prixUnitaire = $produit->price;
                    $montantLigne = $prixUnitaire * $produit->qty;
                    $tvaLigne = $montantLigne * (($config->tva ?? 0) / 100) * ($client->applique_tva ?? 0);
                    $totalHT += $montantLigne;
                    $totalTVA_montant += $tvaLigne;
                    $index++;
                @endphp
                <tr>
                    <td class="col-ref">{{ str_pad($index, 2, '0', STR_PAD_LEFT) }}</td>
                    <td class="col-designation">{{ ucwords($produit->model->nom) }}</td>
                    <td class="col-pu">{{ number_format($prixUnitaire, 0, '', ' ') }}</td>
                    <td class="col-qte">{{ $produit->qty }}</td>
                    <td class="col-unite">{{ $produit->model->uniteProduit->abreviation ?? 'U' }}</td>
                    <td class="col-taxes">TVA ({{ $config->tva ?? 0 }}%)</td>
                    <td class="col-rem">0</td>
                    <td class="col-montant">{{ number_format($montantLigne, 0, '', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection

@section('totaux')
    @php
        if (session('0') && isset(session('0')['cout_livraison'])) {
            $livraison = session('0')['cout_livraison'];
        }
        if (session('0') && isset(session('0')['tva']) && session('0')['tva']) {
            $totalTVA_montant = session('0')['tva'];
        }

        $totalTTC = $totalHT + $totalTVA_montant;
        $totalAPayer = $totalTTC + $livraison - $remise;

        session()->put([
            'totalCommande' => intVal($totalAPayer)
        ]);
    @endphp

    <table class="fne-totaux-outer"><tr><td class="fne-totaux-spacer"></td><td class="fne-totaux-content"><table class="fne-totaux">
        <tr>
            <td class="label">TOTAL HT</td>
            <td class="valeur">{{ number_format($totalHT, 0, '', ' ') }}</td>
        </tr>
        <tr>
            <td class="label">TVA ({{ $config->tva ?? 0 }}%)</td>
            <td class="valeur">{{ number_format($totalTVA_montant, 0, '', ' ') }}</td>
        </tr>
        @if($livraison > 0)
        <tr>
            <td class="label">Coût livraison @if(session('0') && isset(session('0')['km']))({{ session('0')['km'] }} km)@endif</td>
            <td class="valeur">{{ number_format($livraison, 0, '', ' ') }}</td>
        </tr>
        @endif
        @if($remise > 0)
        <tr>
            <td class="label">Remise</td>
            <td class="valeur">-{{ number_format($remise, 0, '', ' ') }}</td>
        </tr>
        @endif
        <tr>
            <td class="label">TOTAL TTC</td>
            <td class="valeur">{{ number_format($totalTTC, 0, '', ' ') }}</td>
        </tr>
        <tr>
            <td class="label">AUTRES TAXES</td>
            <td class="valeur">0</td>
        </tr>
        <tr>
            <td class="label" style="font-size:10pt;">TOTAL A PAYER</td>
            <td class="valeur" style="font-size:10pt; font-weight:bold;">{{ number_format($totalAPayer, 0, '', ' ') }}</td>
        </tr>
    </table></td></tr></table>
@endsection

@section('resume_fiscal')
    <div class="fne-resume-titre">RESUME DE LA FACTURE</div>
    <table class="fne-resume">
        <thead>
            <tr>
                <th>CATEGORIE</th>
                <th>SOUS-TOTAL</th>
                <th>TAUX (%)</th>
                <th>TOTAL TAXES</th>
            </tr>
        </thead>
        <tbody>
            @if(($config->tva ?? 0) > 0 && ($client->applique_tva ?? 0))
                <tr>
                    <td>TVA {{ $config->tva }}% sur HT</td>
                    <td class="text-right">{{ number_format($totalHT, 0, '', ' ') }}</td>
                    <td class="text-center">{{ $config->tva }}%</td>
                    <td class="text-right">{{ number_format($totalTVA_montant, 0, '', ' ') }}</td>
                </tr>
            @else
                <tr>
                    <td>TVA exo.lég - Pas de TVA sur HT 00,00% - D</td>
                    <td class="text-right">{{ number_format($totalHT, 0, '', ' ') }}</td>
                    <td class="text-center">0%</td>
                    <td class="text-right">0</td>
                </tr>
            @endif
        </tbody>
    </table>

    <br>
    <div style="text-align:center; margin-top:20px;">
        <a href="{{ route('client.panierCommande') }}" id="btnValiderCommande" class="btn btn-primary"
           style="display:inline-block; padding:12px 40px; background-color:#1c57a3; color:#fff; text-decoration:none; border-radius:5px; font-size:14px; font-weight:bold;"
           onclick="if(this.dataset.clicked){return false;} this.dataset.clicked='1'; this.style.pointerEvents='none'; this.style.opacity='0.6'; this.textContent='Traitement en cours…';">
            Valider la commande
        </a>
    </div>
@endsection
