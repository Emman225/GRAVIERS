@extends('layout.main')
@section('title', 'Écriture ' . $ecriture->identifiant)

@php
    use App\Models\EcritureComptable;
    $francs = fn ($montant) => number_format((float) $montant, 0, ',', ' ');
@endphp

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Écriture {{ $ecriture->identifiant }}</h2>
        <div>
            <a href="{{ route('show.comptabilite.ecritures.index') }}" class="btn btn-light">
                <i class="material-icons md-arrow_back"></i> Retour au journal
            </a>
        </div>
    </div>

    <div class="card dash-card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><small class="text-muted d-block">Date de l'écriture</small><strong>{{ $ecriture->date_ecriture?->format('d/m/Y') }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Journal</small><strong>{{ $ecriture->journal?->designation ?: ($ecriture->journal_code ?: '-') }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Pièce</small><strong>{{ $ecriture->piece }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Origine</small><strong>{{ EcritureComptable::ORIGINES[$ecriture->origine] ?? $ecriture->origine }}</strong></div>

                <div class="col-md-3"><small class="text-muted d-block">Référence FNE (DGI)</small><strong>{{ $ecriture->reference_fne ?: '-' }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Affaire</small><strong>{{ $ecriture->numero_affaire ?: '-' }}</strong> <small class="text-muted">{{ $ecriture->service }}</small></div>
                <div class="col-md-3">
                    <small class="text-muted d-block">Tiers</small>
                    {{-- Un tiers sans identifiant n'affiche pas « Client n° » suivi de rien. --}}
                    <strong>{{ $ecriture->client?->display_name
                        ?: ($ecriture->tiers_type && $ecriture->tiers_id ? \Help::phrase($ecriture->tiers_type) . ' n° ' . $ecriture->tiers_id : '-') }}</strong>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block">État</small>
                    @if ($ecriture->etat === EcritureComptable::ETAT_EXPORTEE)
                        <span class="badge bg-success">Transmise</span>
                        @if ($deversement)
                            <small class="d-block text-muted">{{ $deversement->numero }}, le {{ \Help::dateHeure($ecriture->exportee_le) }}</small>
                        @endif
                    @elseif ($ecriture->etat === EcritureComptable::ETAT_ANOMALIE)
                        <span class="badge bg-warning text-dark">En anomalie</span>
                    @else
                        <span class="badge bg-info">À transmettre</span>
                    @endif
                </div>

                <div class="col-12"><small class="text-muted d-block">Libellé</small><strong>{{ $ecriture->libelle }}</strong></div>
            </div>

            @if ($ecriture->annulee_par_id || $ecriture->annulation_de_id)
                <div class="alert alert-info mt-3 mb-0">
                    @if ($ecriture->annulee_par_id)
                        Cette écriture a été annulée par
                        <a href="{{ route('show.comptabilite.ecritures.detail', $ecriture->annulee_par_id) }}">{{ $ecriture->annuleePar?->identifiant }}</a> :
                        une écriture transmise ne se modifie jamais, elle se contre-passe.
                    @else
                        Cette écriture annule
                        <a href="{{ route('show.comptabilite.ecritures.detail', $ecriture->annulation_de_id) }}">{{ $ecriture->annulationDe?->identifiant }}</a>.
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title"><i class="material-icons md-list text-primary"></i> Lignes de l'écriture</h5>
        </div>
        <div class="card-body">
            <x-export-buttons table-id="tableLignes" filename="ecriture-{{ $ecriture->identifiant }}" title="Écriture {{ $ecriture->identifiant }}" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0" id="tableLignes">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Compte</th>
                            <th>Compte tiers</th>
                            <th>Analytique</th>
                            <th>Libellé</th>
                            <th>Lettrage</th>
                            <th class="text-end">Débit</th>
                            <th class="text-end">Crédit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ecriture->lignes as $ligne)
                            <tr>
                                <td>{{ $ligne->rang }}</td>
                                <td class="text-nowrap">
                                    <strong>{{ $ligne->numero_compte ?: '—' }}</strong>
                                    @if ($ligne->compte) <small class="d-block text-muted">{{ $ligne->compte->libelle }}</small> @endif
                                </td>
                                <td class="text-nowrap">{{ $ligne->compte_tiers ?: '-' }}</td>
                                <td class="text-nowrap">
                                    {{ $ligne->numero_analytique ?: '-' }}
                                    @if ($ligne->famille) <small class="d-block text-muted">{{ $ligne->famille->nom }}</small> @endif
                                </td>
                                <td class="td-texte-long">{{ $ligne->libelle }}</td>
                                <td class="text-nowrap">{{ $ligne->lettre ?: '-' }}</td>
                                <td class="text-end text-nowrap">{{ $ligne->debit > 0 ? $francs($ligne->debit) : '' }}</td>
                                <td class="text-end text-nowrap">{{ $ligne->credit > 0 ? $francs($ligne->credit) : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="6" class="text-end">Totaux</th>
                            <th class="text-end">{{ $francs($ecriture->total_debit) }}</th>
                            <th class="text-end">{{ $francs($ecriture->total_credit) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if ($ecriture->estEquilibree())
                <div class="alert alert-success mt-3 mb-0">L'écriture est équilibrée : total débit = total crédit.</div>
            @else
                <div class="alert alert-warning mt-3 mb-0">
                    L'écriture ne s'équilibre pas : elle ne sera pas transmise tant que l'écart subsistera.
                </div>
            @endif
        </div>
    </div>

    @if ($ecriture->anomalies->isNotEmpty())
        <div class="card dash-card mb-4">
            <div class="card-header dash-card-header">
                <h5 class="dash-card-title"><i class="material-icons md-error text-primary"></i> Anomalies</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table dash-table align-middle mb-0">
                        <thead>
                            <tr><th>Concerné</th><th>Cause</th><th>Colonne à corriger</th><th>Ligne</th><th class="text-center">État</th><th class="text-end">Corriger</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($ecriture->anomalies as $anomalie)
                                <tr>
                                    <td><strong>{{ $anomalie->objet }}</strong></td>
                                    <td class="td-texte-long">{{ $anomalie->cause }}</td>
                                    <td>{{ $anomalie->colonne }}</td>
                                    <td class="text-center">{{ $anomalie->rang_ligne ?: '-' }}</td>
                                    <td class="text-center">
                                        @if ($anomalie->resolue_le)
                                            <span class="badge bg-success">Corrigée</span>
                                            <small class="d-block text-muted">{{ \Help::dateHeure($anomalie->resolue_le) }}</small>
                                        @else
                                            <span class="badge bg-warning text-dark">Ouverte</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if (!$anomalie->resolue_le && $anomalie->onglet)
                                            <a href="{{ route('show.comptabilite.parametrage', ['onglet' => $anomalie->onglet]) }}" class="btn btn-sm btn-primary">Ouvrir le paramétrage</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if ($soeurs->isNotEmpty())
        <div class="card dash-card mb-4">
            <div class="card-header dash-card-header">
                <h5 class="dash-card-title"><i class="material-icons md-link text-primary"></i> Les autres écritures de la même pièce</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table dash-table align-middle mb-0">
                        <thead><tr><th>Identifiant</th><th>Date</th><th>Origine</th><th class="text-end">Débit</th><th class="text-center">État</th><th class="text-end">Détail</th></tr></thead>
                        <tbody>
                            @foreach ($soeurs as $soeur)
                                <tr>
                                    <td><strong>{{ $soeur->identifiant }}</strong></td>
                                    <td class="text-nowrap">{{ $soeur->date_ecriture?->format('d/m/Y') }}</td>
                                    <td>{{ EcritureComptable::ORIGINES[$soeur->origine] ?? $soeur->origine }}</td>
                                    <td class="text-end text-nowrap">{{ $francs($soeur->total_debit) }}</td>
                                    <td class="text-center">{{ $soeur->libelle_etat }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('show.comptabilite.ecritures.detail', $soeur) }}" class="btn btn-sm btn-primary rounded"><i class="material-icons md-visibility"></i></a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection
