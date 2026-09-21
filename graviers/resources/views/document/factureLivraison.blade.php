@php
    use Illuminate\Support\Carbon;
    // Le mode de paiement RÉEL : celui des règlements validés de la demande (lot 86, 15/09/2026).
    $modeLibelle = \Help::modePaiementDeLAffaire(\Help::$LIVRAISON, (int) ($demande->id ?? 0),
        optional(\App\Models\ModePaiement::find($demande->mode_paiement_id))->libelle);

    // Le HT du transport : ce que le client doit avant taxes, remise déduite.
    $remise  = (float) ($demande->remise ?? 0);
    $totalHT = max(0, (float) ($demande->montantTotal ?? 0) - $remise);

    // La TVA du TRANSPORT, et d'elle seule : une demande, une commande et une
    // location peuvent porter le même identifiant dans tva_commande.
    $totalTVA = (float) \App\Models\TvaCommande::where('commande_id', $demande->id)
        ->where('type_affaire', \Help::$LIVRAISON)
        ->whereNull('deleted_at')
        ->sum('montant');
@endphp

@extends('document.layouts.fne_base')

{{-- Le même gabarit sert à la proforma de la demande (lot 85, 15/09/2026). --}}
@section('titre', $typeDocument ?? 'Facture de transport')
@section('type_document', $typeDocument ?? 'Facture de transport')

@section('vendeur', \Help::nomDuVendeur($facture->user ?? null))
@section('mode_paiement', $modeLibelle)

@if($demande->destination)
    @section('adresse_livraison', \Help::phrase($demande->destination->affichage ?? ''))
@endif

@section('articles')
    <table class="fne-articles">
        <thead>
            <tr>
                <th class="col-ref">Réf</th>
                <th class="col-designation">Désignation</th>
                <th class="col-qte">Qté</th>
                <th class="col-unite">Unité</th>
                <th class="col-taxes">Taxes (%)</th>
                <th class="col-montant">Montant HT</th>
            </tr>
        </thead>
        <tbody>
            {{-- LA PRESTATION FACTURÉE EST LE TRANSPORT, pas la marchandise.
                 Le client n'achète pas les produits transportés — ils sont à lui
                 — il achète leur acheminement. Les lister comme des articles
                 facturés laisserait croire à une vente. --}}
            <tr>
                <td class="col-ref">{{ $demande->numero }}</td>
                <td class="col-designation">
                    Prestation de transport
                    @if($demande->priseEnCharge || $demande->destination)
                        <br>
                        <span style="font-size:8pt;">
                            De {{ $demande->priseEnCharge->affichage ?? '—' }}
                            à {{ $demande->destination->affichage ?? '—' }}
                        </span>
                    @endif
                </td>
                <td class="col-qte">1</td>
                <td class="col-unite">Course</td>
                {{-- La colonne annonce la taxe REELLEMENT portee par la course :
                     la TVA sur le transport est une option, et une course
                     chiffree hors taxe garde son montant meme si l option est
                     activee ensuite. --}}
                <td class="col-taxes">
                    @if($totalTVA > 0)
                        TVA ({{ $config->tva ?? 0 }}%)
                    @else
                        Exonéré
                    @endif
                </td>
                <td class="col-montant">{{ number_format($totalHT, 0, '', ' ') }}</td>
            </tr>

            {{-- La marchandise acheminée, pour mémoire : elle justifie le tarif
                 (unité et quantité font la tranche) sans être facturée. --}}
            @foreach ($demande->detailLivraison as $detail)
                <tr>
                    <td class="col-ref"></td>
                    <td class="col-designation" style="font-size:8pt; font-style:italic;">
                        Marchandise transportée : {{ $detail->nom_produit }}
                    </td>
                    <td class="col-qte" style="font-size:8pt;">
                        {{ rtrim(rtrim(number_format((float) $detail->qte, 2, ',', ' '), '0'), ',') }}
                    </td>
                    <td class="col-unite" style="font-size:8pt;">{{ $detail->unite }}</td>
                    <td class="col-taxes"></td>
                    <td class="col-montant"></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection

@section('totaux')
    @php
        $totalTTC    = $totalHT + $totalTVA;
        $airsi       = \Help::arrondiFranc((float) ($facture->airsi_applique ?? $demande->airsi ?? 0));
        $totalAPayer = $facture->montant ?? ($totalTTC + $airsi);
    @endphp

    <table class="fne-totaux-outer"><tr><td class="fne-totaux-spacer"></td><td class="fne-totaux-content"><table class="fne-totaux">
        <tr>
            <td class="label">TOTAL HT</td>
            <td class="valeur">{{ number_format($totalHT, 0, '', ' ') }}</td>
        </tr>
        @if($remise > 0)
        <tr>
            <td class="label">Remise</td>
            <td class="valeur">-{{ number_format($remise, 0, '', ' ') }}</td>
        </tr>
        @endif
        @if($totalTVA > 0)
        <tr>
            <td class="label">TVA</td>
            <td class="valeur">{{ number_format($totalTVA, 0, '', ' ') }}</td>
        </tr>
        @endif
        <tr>
            <td class="label"><strong>NET À PAYER</strong></td>
            <td class="valeur"><strong>{{ number_format($totalAPayer, 0, '', ' ') }}</strong></td>
        </tr>
    </table></td></tr></table>
@endsection

@section('resume_fiscal')
    {{-- L'état de certification, écrit sur le document lui-même : produire une
         facture non encore certifiée en la croyant normalisée exposerait
         l'entreprise devant l'administration. --}}
    @if (!empty($fne_certified))
        <p style="font-size:8pt; margin-top:10px;">
            Facture certifiée — référence {{ $fne_reference ?? '' }}
        </p>
    @else
        <p style="font-size:8pt; font-style:italic; margin-top:10px;">
            Facture en attente de certification auprès de la DGI.
        </p>
    @endif
@endsection
