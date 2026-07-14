{{--
    Partial : corps du reçu de paiement (template partagé).
    Utilisé pour clients à terme, fournisseurs, livreurs, apporteurs.
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
        {{ strtoupper($titre) }} N° {{ $numeroRecu }}
        @if (!empty($sousTitre))
            <br><small>{{ $sousTitre }}</small>
        @endif
    </div>

    @if (($trancheTotal ?? 1) > 1)
        <div class="recu-tranche">
            <strong>Tranche {{ $trancheNum }} / {{ $trancheTotal }}</strong> — Paiement échelonné
        </div>
    @endif

    <div class="recu-meta">
        <div class="recu-meta-row">
            <div class="recu-meta-cell">
                <div class="recu-meta-label">Date paiement</div>
                <div class="recu-meta-value">{{ $datePaiement ? Carbon::parse($datePaiement)->format('d/m/Y') : '-' }}</div>
            </div>
            @if (!empty($agenceLabel))
                <div class="recu-meta-cell" style="text-align: right;">
                    <div class="recu-meta-label">Agence</div>
                    <div class="recu-meta-value">{{ $agenceLabel }}</div>
                </div>
            @endif
        </div>
    </div>

    <table class="recu-table">
        <tr>
            <td>{{ $beneficiaireRole ?? 'Bénéficiaire' }}</td>
            <td>{{ $beneficiaireNom ?? '-' }}</td>
        </tr>
        @if (!empty($beneficiaireContact))
        <tr>
            <td>Contact</td>
            <td>{{ $beneficiaireContact }}</td>
        </tr>
        @endif
        @foreach ($contexteInfos ?? [] as $label => $valeur)
            @if (!empty($valeur))
            <tr>
                <td>{{ $label }}</td>
                <td>{{ $valeur }}</td>
            </tr>
            @endif
        @endforeach
        <tr>
            <td>Mode de paiement</td>
            <td>{{ $modePaiement ?? '-' }}</td>
        </tr>
        @if (!empty($reference))
        <tr>
            <td>Référence transaction</td>
            <td>{{ $reference }}</td>
        </tr>
        @endif
        @if (!empty($caissier))
        <tr>
            <td>Émis par</td>
            <td>{{ $caissier }}</td>
        </tr>
        @endif
    </table>

    <div class="recu-montant">
        <div class="recu-montant-label">{{ $montantLabel ?? 'Montant payé' }}</div>
        <div class="recu-montant-value">{{ Help::formatNombre($montant, true) }}</div>
    </div>

    @if (!empty($resumeFinancier) && ($resumeFinancier['total'] ?? 0) > 0)
        <div class="recu-resume">
            <div class="recu-resume-row">
                <div class="recu-resume-label">{{ $resumeFinancier['totalLabel'] ?? 'Total' }} :</div>
                <div class="recu-resume-value">{{ Help::formatNombre($resumeFinancier['total'], true) }}</div>
            </div>
            <div class="recu-resume-row">
                <div class="recu-resume-label">Total payé :</div>
                <div class="recu-resume-value text-success">{{ Help::formatNombre($resumeFinancier['paye'], true) }}</div>
            </div>
            <div class="recu-resume-row">
                <div class="recu-resume-label">Reste à payer :</div>
                <div class="recu-resume-value {{ ($resumeFinancier['reste'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">
                    {{ Help::formatNombre($resumeFinancier['reste'], true) }}
                </div>
            </div>
        </div>
    @endif

    @if (!empty($libelle))
        <p style="font-size: 9px; color: #666; margin: 6px 0;"><strong>Notes :</strong> {{ $libelle }}</p>
    @endif

    @php
        $codeBarreClean = strtoupper(preg_replace('/[^0-9A-Z\-\. \$\/\+\%]/i', '', (string) $numeroRecu));
    @endphp
    @if ($codeBarreClean !== '')
        <div class="recu-barcode">
            {!! Help::barcode39Html($codeBarreClean, 40) !!}
            <div class="recu-barcode-num">{{ $numeroRecu }}</div>
        </div>
    @endif

    <div class="recu-signatures">
        <div class="recu-sign">{{ $signatureGauche ?? "Signature Émetteur" }}</div>
        <div class="recu-sign">{{ $signatureDroite ?? "Signature Bénéficiaire" }}</div>
    </div>

    <div class="recu-footer">
        Document généré le {{ now()->format('d/m/Y à H:i') }}@if ($entreprise) • {{ strtoupper($entreprise) }}@endif
    </div>
</div>
