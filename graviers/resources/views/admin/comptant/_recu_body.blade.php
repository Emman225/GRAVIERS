{{--
    Partial : corps du reçu d'encaissement en agence.
    Utilisé par recu.blade.php (HTML) et recu-pdf.blade.php (PDF).
    Variables attendues : $paiement, $commande, $config, $trancheNum, $trancheTotal,
    $totalAPayer, $totalPaye, $reste, $mode, $reference
--}}
@php
    use Illuminate\Support\Carbon;
    $logoPath = public_path(config('constantes.logo'));
    $logoSrc = '';
    if (file_exists($logoPath)) {
        $logoData = base64_encode(file_get_contents($logoPath));
        $ext = pathinfo($logoPath, PATHINFO_EXTENSION);
        $logoSrc = "data:image/{$ext};base64,{$logoData}";
    }
    $candidatNom = function ($v) {
        $v = trim((string) ($v ?? ''));
        return ($v !== '' && !ctype_digit($v)) ? $v : null;
    };
    $entreprise = $candidatNom($config?->raison_sociale)
        ?? $candidatNom($config?->nom_etablissement)
        ?? '';
@endphp

<div class="recu-container">
    <div class="recu-header">
        <table class="recu-head-table">
            <tr>
                @if ($logoSrc)
                    <td class="recu-head-logo">
                        <img src="{{ $logoSrc }}" alt="Logo">
                    </td>
                @endif
                <td class="recu-head-info">
                    @if ($entreprise)
                        <h1>{{ strtoupper($entreprise) }}</h1>
                    @endif
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
            </tr>
        </table>
    </div>

    <div class="recu-numero">
        REÇU DE PAIEMENT N° {{ $paiement->numero_recu ?? $paiement->code }}
    </div>

    @if ($trancheTotal > 1)
        <div class="recu-tranche">
            <strong>Tranche {{ $trancheNum }} / {{ $trancheTotal }}</strong> — Paiement échelonné
        </div>
    @endif

    <div class="recu-meta">
        <div class="recu-meta-row">
            <div class="recu-meta-cell">
                <div class="recu-meta-label">Date</div>
                <div class="recu-meta-value">{{ $paiement->created_at?->format('d/m/Y H:i') }}</div>
            </div>
            <div class="recu-meta-cell" style="text-align: right;">
                <div class="recu-meta-label">Agence</div>
                <div class="recu-meta-value">
                    {{ $paiement->agence?->code ?? '-' }}
                    @if ($paiement->agence?->nom)
                        <br><span style="font-weight: normal; font-size: 11px; color: #666;">{{ $paiement->agence->nom }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <table class="recu-table">
        <tr>
            <td>Reçu de</td>
            <td>{{ $paiement->client ? trim($paiement->client->nom . ' ' . $paiement->client->prenom) : '-' }}</td>
        </tr>
        <tr>
            <td>Téléphone</td>
            <td>{{ $paiement->client?->contact1 ?? '-' }}</td>
        </tr>
        <tr>
            <td>N° Commande</td>
            <td>{{ $commande?->numero ?? '-' }}</td>
        </tr>
        <tr>
            <td>Mode de paiement</td>
            <td>{{ $mode }}</td>
        </tr>
        @if ($reference)
        <tr>
            <td>Référence transaction</td>
            <td>{{ $reference }}</td>
        </tr>
        @endif
        <tr>
            <td>Caissier</td>
            <td>{{ $paiement->caissier?->nom_prenoms ?? '-' }}</td>
        </tr>
    </table>

    <div class="recu-montant">
        <div class="recu-montant-label">Montant encaissé</div>
        <div class="recu-montant-value">{{ Help::formatNombre($paiement->montant_total, true) }}</div>
    </div>

    @if ($commande && $totalAPayer > 0)
        <div class="recu-resume">
            <div class="recu-resume-row">
                <div class="recu-resume-label">Total commande :</div>
                <div class="recu-resume-value">{{ Help::formatNombre($totalAPayer, true) }}</div>
            </div>
            <div class="recu-resume-row">
                <div class="recu-resume-label">Total payé :</div>
                <div class="recu-resume-value text-success">{{ Help::formatNombre($totalPaye, true) }}</div>
            </div>
            <div class="recu-resume-row">
                <div class="recu-resume-label">Reste à payer :</div>
                <div class="recu-resume-value {{ $reste > 0 ? 'text-danger' : 'text-success' }}">
                    {{ Help::formatNombre($reste, true) }}
                </div>
            </div>
        </div>
    @endif

    @if ($paiement->libelle)
        <p style="font-size: 9px; color: #666; margin: 6px 0;"><strong>Notes :</strong> {{ $paiement->libelle }}</p>
    @endif

    @php
        $codeBarre = $paiement->numero_recu ?? $paiement->code ?? '';
        $codeBarreClean = strtoupper(preg_replace('/[^0-9A-Z\-\. \$\/\+\%]/i', '', $codeBarre));
    @endphp
    @if ($codeBarreClean !== '')
        <div class="recu-barcode">
            {!! Help::barcode39Html($codeBarreClean, 40) !!}
            <div class="recu-barcode-num">{{ $codeBarre }}</div>
        </div>
    @endif

    <div class="recu-signatures">
        <div class="recu-sign">Signature Caissier</div>
        <div class="recu-sign">Signature Client</div>
    </div>

    <div class="recu-footer">
        Reçu généré le {{ now()->format('d/m/Y à H:i') }}@if ($entreprise) • {{ strtoupper($entreprise) }}@endif
    </div>
</div>
