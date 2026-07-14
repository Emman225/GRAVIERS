@php
    use Illuminate\Support\Carbon;

    // Logo en base64
    $logoPath = public_path(config('constantes.logo'));
    $logoSrc = '';
    if (file_exists($logoPath)) {
        $logoData = base64_encode(file_get_contents($logoPath));
        $ext = pathinfo($logoPath, PATHINFO_EXTENSION);
        $logoSrc = "data:image/{$ext};base64,{$logoData}";
    }

    // Nom société (ignore valeurs vides/numériques)
    $candidatNom = function ($v) {
        $v = trim((string) ($v ?? ''));
        return ($v !== '' && !ctype_digit($v)) ? $v : null;
    };
    $entreprise = $candidatNom($config?->raison_sociale)
        ?? $candidatNom($config?->nom_etablissement)
        ?? 'GRAVIER.COM';

    // Total net depuis les LIGNES (montant_total : HT côté web, net final côté
    // mobile -> l'ancienne formule double-comptait pour les commandes mobiles).
    $totalCommande = $commande->montantAPayer();
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bon d'enlèvement — {{ $commande->numero }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #333; margin: 0; padding: 0; }
        .container { padding: 10px 14px; }

        /* En-tête */
        .header { border-bottom: 2px solid #1c57a3; padding-bottom: 8px; margin-bottom: 10px; }
        .head-table { width: 100%; border-collapse: collapse; }
        .head-logo { width: 90px; vertical-align: middle; padding-right: 10px; }
        .head-logo img { max-width: 80px; max-height: 60px; display: block; }
        .head-info { vertical-align: middle; }
        .head-info h1 { color: #1c57a3; margin: 0 0 3px; font-size: 16px; letter-spacing: 0.5px; line-height: 1.1; }
        .head-info .subtitle { color: #666; font-size: 9px; margin: 0; line-height: 1.4; }

        /* Bandeau titre */
        .doc-title { background: #1c57a3; color: white; padding: 7px 12px; text-align: center; font-size: 13px; font-weight: bold; letter-spacing: 0.8px; margin: 8px 0; border-radius: 3px; }

        /* Bloc info commande */
        .info-blocks { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .info-cell { width: 50%; vertical-align: top; padding: 6px 8px; background: #f8f9fa; border-left: 3px solid #1c57a3; border-radius: 3px; }
        .info-cell h6 { margin: 0 0 3px; font-size: 10px; color: #1c57a3; text-transform: uppercase; letter-spacing: 0.4px; }
        .info-cell p { margin: 0; font-size: 10px; line-height: 1.4; }

        /* Tableaux */
        .table { width: 100%; border-collapse: collapse; margin: 6px 0 10px; }
        .table th, .table td { border: 1px solid #e0e0e0; padding: 4px 6px; font-size: 9px; text-align: left; vertical-align: middle; }
        .table th { background: #1c57a3; color: white; font-weight: 600; font-size: 9px; }
        .table td.right, .table th.right { text-align: right; }
        .table td.center, .table th.center { text-align: center; }
        .table .total-row { background: #f0f4fa; font-weight: bold; }
        .table .total-row td { font-size: 10px; }

        /* Tableau enlèvements (très large) — taille minuscule */
        .table-enleve { font-size: 8px; }
        .table-enleve th, .table-enleve td { padding: 3px 4px; font-size: 8px; }
        .table-enleve th { font-size: 7.5px; }

        /* Récap */
        .recap { width: 50%; margin-left: 50%; border-collapse: collapse; margin-top: 4px; }
        .recap td { padding: 3px 6px; font-size: 10px; }
        .recap td.label { text-align: right; color: #666; }
        .recap td.value { text-align: right; font-weight: 600; width: 40%; }
        .recap tr.total-line td { border-top: 2px solid #1c57a3; padding-top: 5px; font-size: 12px; }

        /* Badges */
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: 600; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-secondary { background: #e5e7eb; color: #374151; }

        h3 { font-size: 11px; color: #1c57a3; margin: 12px 0 4px; }
        h4 { font-size: 10px; color: #444; margin: 8px 0 4px; }

        .footer { text-align: center; margin-top: 12px; padding-top: 6px; border-top: 1px solid #ccc; color: #999; font-size: 8px; }

        .text-success { color: #28a745; }
        .text-danger { color: #dc3545; }
        .text-muted { color: #6b7280; }
    </style>
</head>
<body>
<div class="container">
    {{-- ====== EN-TÊTE ====== --}}
    <div class="header">
        <table class="head-table">
            <tr>
                @if ($logoSrc)
                    <td class="head-logo">
                        <img src="{{ $logoSrc }}" alt="Logo">
                    </td>
                @endif
                <td class="head-info">
                    <h1>{{ strtoupper($entreprise) }}</h1>
                    <p class="subtitle">
                        @if($config?->adresse_siege){{ $config->adresse_siege }}@endif
                        @if($config?->telephone) • Tél : {{ $config->telephone }}@endif
                        @if($config?->email_entreprise) • {{ $config->email_entreprise }}@endif
                    </p>
                    @if($config?->ncc || $config?->rccm)
                        <p class="subtitle">
                            @if($config?->rccm)RCCM : {{ $config->rccm }}@endif
                            @if($config?->ncc) • NCC : {{ $config->ncc }}@endif
                        </p>
                    @endif
                </td>
                <td style="text-align: right; vertical-align: top; width: 200px;">
                    <p class="subtitle" style="margin: 0;">
                        Édité le {{ now()->format('d/m/Y à H:i') }}
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <div class="doc-title">
        BON D'ENLÈVEMENT — COMMANDE N° {{ $commande->numero }}
    </div>

    {{-- ====== INFOS COMMANDE ====== --}}
    <table class="info-blocks">
        <tr>
            <td class="info-cell" style="padding-right: 4px;">
                <h6>Client</h6>
                <p>
                    <strong>{{ $commande->le_client }}</strong><br>
                    {{ $commande->client?->user?->email ?? '-' }}<br>
                    {{ $commande->contact1 ?? '-' }}
                </p>
            </td>
            @if ($commande->est_livrable)
                <td class="info-cell" style="padding-left: 4px;">
                    <h6>Lieu de livraison</h6>
                    <p>
                        Pays : {{ ucfirst($commande->lePays ?? '-') }}<br>
                        Ville : {{ ucfirst($commande->laVille ?? '-') }}<br>
                        {{ ucfirst($commande->adresse ?? '-') }}
                    </p>
                </td>
            @else
                <td class="info-cell" style="padding-left: 4px;">
                    <h6>Type</h6>
                    <p>Retrait sur place</p>
                </td>
            @endif
        </tr>
        <tr>
            <td class="info-cell" style="padding-right: 4px; margin-top: 4px;">
                <h6>Date commande</h6>
                <p>{{ $commande->date_commande ?? '-' }}</p>
            </td>
            <td class="info-cell" style="padding-left: 4px;">
                <h6>Mode de paiement</h6>
                <p>{{ $commande->mode_paiement ?? '-' }}</p>
            </td>
        </tr>
    </table>

    {{-- ====== RÉFÉRENCE BON UPLOADÉ ====== --}}
    @if ($commande->blClient && $commande->blClient->fichier)
        <p class="text-muted" style="margin: 6px 0;">
            <strong>Bon de commande client uploadé</strong>
            @if ($commande->blClient->numero) — N° {{ $commande->blClient->numero }}@endif
        </p>
    @endif

    {{-- ====== PRODUITS COMMANDÉS ====== --}}
    <h3>Produits commandés</h3>
    <table class="table">
        <thead>
            <tr>
                <th width="40%">Produit</th>
                <th class="right" width="20%">Prix unitaire</th>
                <th class="center" width="20%">Quantité (T)</th>
                <th class="right" width="20%">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($commande->produits as $produit)
                <tr>
                    <td>{{ $produit->nom }}</td>
                    <td class="right">{{ number_format($produit->pivot->prix, 0, ',', ' ') }} fcfa</td>
                    <td class="center">{{ $produit->pivot->qte }}</td>
                    <td class="right">{{ number_format($produit->pivot->prix * $produit->pivot->qte, 0, ',', ' ') }} fcfa</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ====== RÉCAPITULATIF MONTANTS ====== --}}
    <table class="recap">
        <tr>
            <td class="label">Sous-total :</td>
            <td class="value">{{ Help::formatNombre($commande->montantHT(), true) }}</td>
        </tr>
        <tr>
            <td class="label">Coût de livraison :</td>
            <td class="value">{{ Help::formatNombre($commande->cout_livraison_client, true) }}</td>
        </tr>
        <tr>
            <td class="label">TVA :</td>
            <td class="value">{{ Help::formatNombre($commande->TvaCommande?->montant ?? 0, true) }}</td>
        </tr>
        @if ($commande->remise)
            <tr>
                <td class="label">Remise :</td>
                <td class="value text-danger">- {{ Help::formatNombre($commande->remise, true) }}</td>
            </tr>
        @endif
        <tr class="total-line">
            <td class="label"><strong>TOTAL :</strong></td>
            <td class="value"><strong>{{ Help::formatNombre($totalCommande, true) }}</strong></td>
        </tr>
        <tr>
            <td class="label">Statut paiement :</td>
            <td class="value">
                {{-- fcfa sans décimales : restant < 1 = soldé (résidu d'arrondi TVA). --}}
                @if ($restant >= $montantAPayer)
                    <span class="badge badge-danger">Aucun paiement</span>
                @elseif ($restant >= 1)
                    <span class="badge badge-warning">Paiement en cours</span>
                @else
                    <span class="badge badge-success">Soldé</span>
                @endif
            </td>
        </tr>
    </table>

    {{-- ====== DÉTAIL DES ENLÈVEMENTS ====== --}}
    @foreach ($details as $detail)
        @php $qteRestante = $detail->qte; @endphp
        <h3 style="page-break-before: auto;">Enlèvements — {{ $detail->nom }}</h3>
        <table class="table table-enleve">
            <thead>
                <tr>
                    <th class="center">Qté restante</th>
                    <th class="right">Prix U.</th>
                    <th class="right">Montant</th>
                    <th class="center">Qté enlevée</th>
                    <th class="center">Reste à enlever</th>
                    <th class="right">Montant enlevé</th>
                    <th class="right">Montant restant</th>
                    <th>Traité par</th>
                    <th class="center">Date</th>
                    <th class="center">Statut</th>
                    <th>N° Facture</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($detail->livs as $livraison)
                    <tr>
                        <td class="center">{{ $qteRestante }}</td>
                        <td class="right">{{ number_format(($detail->prix ?? $detail->prix_moyen), 0, ',', ' ') }} fcfa</td>
                        <td class="right">{{ number_format(($detail->prix ?? $detail->prix_moyen) * $qteRestante, 0, ',', ' ') }} fcfa</td>
                        <td class="center"><strong>{{ $livraison->qte_enleve }}</strong></td>
                        <td class="center">{{ $qteRestante - $livraison->qte_enleve }}</td>
                        <td class="right">{{ number_format($livraison->qte_enleve * ($detail->prix ?? $detail->prix_moyen), 0, ',', ' ') }} fcfa</td>
                        <td class="right">{{ number_format(($detail->prix ?? $detail->prix_moyen) * ($qteRestante - $livraison->qte_enleve), 0, ',', ' ') }} fcfa</td>
                        <td>{{ $livraison->gestionnaire ?? '-' }}</td>
                        <td class="center">{{ $livraison->created_at?->format('d-m-Y') ?? '-' }}</td>
                        <td class="center">
                            @switch($livraison->etat_livraison)
                                @case('LIVREE')
                                    <span class="badge badge-success">{{ $livraison->etat_livraison }}</span>
                                    @break
                                @case('EN ATTENTE')
                                @case('EN TRAITEMENT')
                                @case('EN COURS LIVRAISON')
                                    <span class="badge badge-warning">{{ $livraison->etat_livraison }}</span>
                                    @break
                                @default
                                    <span class="badge badge-secondary">{{ $livraison->etat_livraison }}</span>
                            @endswitch
                        </td>
                        <td>{{ $livraison->numero_facture ?? '-' }}</td>
                    </tr>
                    @php $qteRestante -= $livraison->qte_enleve; @endphp
                @empty
                    <tr>
                        <td colspan="11" class="center text-muted">Aucun enlèvement enregistré pour ce produit.</td>
                    </tr>
                @endforelse
                @if ($qteRestante > 0)
                    <tr class="total-row">
                        <td class="center">{{ $qteRestante }}</td>
                        <td class="right">{{ Help::formatNombre($detail->prix, true) }}</td>
                        <td class="right">{{ Help::formatNombre($qteRestante * $detail->prix, true) }}</td>
                        <td colspan="8" class="text-muted">Reste à enlever pour ce produit</td>
                    </tr>
                @endif
            </tbody>
        </table>
    @endforeach

    {{-- ====== FACTURES GÉNÉRÉES ====== --}}
    @if (!$commande->factures->isEmpty())
        <h3>Factures générées</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>N° Facture</th>
                    <th>N° FNE (DGI)</th>
                    <th class="center">Statut FNE</th>
                    <th class="center">Date</th>
                    <th class="right">Montant</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($commande->factures as $facture)
                    <tr>
                        <td><strong>{{ $facture->numero }}</strong></td>
                        <td>{{ $facture->fne_reference ?? '—' }}</td>
                        <td class="center">
                            @switch($facture->fne_status)
                                @case('certified')
                                    <span class="badge badge-success">Certifiée DGI</span>
                                    @break
                                @case('failed')
                                    <span class="badge badge-danger">Échec FNE</span>
                                    @break
                                @case('disabled')
                                    <span class="badge badge-secondary">Non certifiée</span>
                                    @break
                                @default
                                    <span class="badge badge-warning">En attente</span>
                            @endswitch
                        </td>
                        <td class="center">{{ Carbon::parse($facture->created_at)->format('d-m-Y') }}</td>
                        <td class="right">{{ number_format($facture->montant, 0, ',', ' ') }} fcfa</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Bon d'enlèvement généré le {{ now()->format('d/m/Y à H:i') }} • {{ strtoupper($entreprise) }}
    </div>
</div>
</body>
</html>
