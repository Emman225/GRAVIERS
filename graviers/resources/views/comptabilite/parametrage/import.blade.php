@extends('layout.main')
@section('title', $applique ? 'Import appliqué' : 'Analyse du classeur de paramétrage')

@php
    use App\Services\Comptabilite\ImportParametrage;
    $couleurs = [
        ImportParametrage::CREATION   => 'bg-success',
        ImportParametrage::CORRECTION => 'bg-primary',
        ImportParametrage::INCHANGE   => 'bg-secondary',
        ImportParametrage::REFUS      => 'bg-danger',
    ];
    $refus = $resume[ImportParametrage::REFUS] ?? 0;
    $aPoser = ($resume[ImportParametrage::CREATION] ?? 0) + ($resume[ImportParametrage::CORRECTION] ?? 0);
@endphp

@section('contenu')
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">{{ $applique ? 'Le paramétrage est posé' : 'Ce que le classeur ferait' }}</h2>
                <p class="dash-welcome-subtitle">
                    {{ $applique
                        ? 'Le classeur a été appliqué. Le compte rendu ci-dessous dit ce qui a changé, ligne à ligne.'
                        : 'Rien n’est encore écrit. Lire le compte rendu, puis appliquer si tout est juste.' }}
                </p>
            </div>
            <div class="dash-welcome-actions">
                <a href="{{ route('show.comptabilite.parametrage', ['onglet' => $applique ? 'controle' : 'import']) }}" class="btn btn-primary">
                    <i class="material-icons md-arrow_back"></i> Paramétrage comptable
                </a>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([
            ImportParametrage::CREATION   => ['md-add_circle', 'kpi-card-success', 'À créer'],
            ImportParametrage::CORRECTION => ['md-edit', 'kpi-card-primary', 'À corriger'],
            ImportParametrage::INCHANGE   => ['md-check_circle', 'kpi-card-info', 'Déjà en place'],
            ImportParametrage::REFUS      => ['md-error', 'kpi-card-warning', 'Refusées'],
        ] as $action => [$icone, $classe, $titre])
            <div class="col-md-3">
                <div class="kpi-card {{ $classe }}">
                    <div class="kpi-card-icon"><i class="material-icons {{ $icone }}"></i></div>
                    <div class="kpi-card-body">
                        <div class="kpi-card-label">{{ $applique && $action !== ImportParametrage::REFUS ? \Help::phrase(mb_strtolower($action)) . 'es' : $titre }}</div>
                        <div class="kpi-card-value">{{ $resume[$action] ?? 0 }}</div>
                    </div>
                    <div class="kpi-card-shape"></div>
                </div>
            </div>
        @endforeach
    </div>

    @if (!$applique)
        <div class="card dash-card mb-4">
            <div class="card-body">
                @if ($aPoser === 0)
                    <div class="alert alert-info mb-3">
                        Le classeur ne change rien : tout ce qu'il porte est déjà en place. C'est le cas normal
                        quand on réimporte le modèle sans l'avoir modifié.
                    </div>
                @endif
                @if ($refus > 0)
                    <div class="alert alert-warning mb-3">
                        {{ $refus }} ligne(s) seront laissées de côté, avec leur cause ci-dessous. Le reste du
                        classeur passera quand même : rien n'oblige à tout corriger avant d'appliquer.
                    </div>
                @endif
                <form method="POST" action="{{ route('show.comptabilite.import.appliquer') }}" class="js-confirmer"
                      data-confirm-title="Appliquer le classeur ?"
                      data-confirm-text="{{ $aPoser }} réglage(s) vont être posés. L'import ne supprime jamais rien : ce que le classeur ne nomme pas reste tel quel.">
                    @csrf
                    <input type="hidden" name="fichier" value="{{ $fichier }}">
                    <button type="submit" class="btn btn-primary" {{ $aPoser === 0 ? 'disabled' : '' }}>
                        <i class="material-icons md-check_circle"></i> Appliquer le paramétrage
                    </button>
                    <a href="{{ route('show.comptabilite.parametrage', ['onglet' => 'import']) }}" class="btn btn-light">Annuler</a>
                </form>
            </div>
        </div>
    @else
        <div class="alert alert-success mb-4">
            C'est posé. Ouvrir l'onglet « Contrôle » du paramétrage : il dit ce qui manque encore, s'il manque
            quelque chose.
        </div>
    @endif

    @foreach ($rapport as $nom => $feuille)
        <div class="card dash-card mb-4">
            <div class="card-header dash-card-header">
                <h5 class="dash-card-title">
                    <i class="material-icons md-list text-primary"></i> {{ $nom }}
                    @if ($feuille['absente'])
                        <span class="badge bg-secondary ms-2">Feuille absente</span>
                    @else
                        <span class="badge bg-light text-dark ms-2">{{ count($feuille['lignes']) }} ligne(s)</span>
                    @endif
                </h5>
            </div>
            <div class="card-body">
                @if ($feuille['absente'])
                    <p class="text-muted mb-0">Cette feuille n'était pas dans le classeur : rien n'a été touché de ce côté.</p>
                @elseif (!count($feuille['lignes']))
                    <p class="text-muted mb-0">Feuille vide : rien n'a été touché de ce côté.</p>
                @else
                    <div class="table-responsive">
                        <table class="table dash-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="text-end">Ligne</th><th>Résultat</th><th>Objet</th><th>Détail</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($feuille['lignes'] as $ligne)
                                    <tr>
                                        <td class="text-end text-nowrap">{{ $ligne['rang'] }}</td>
                                        <td class="text-nowrap">
                                            <span class="badge {{ $couleurs[$ligne['action']] ?? 'bg-secondary' }}">{{ $ligne['action'] }}</span>
                                        </td>
                                        <td class="text-nowrap"><strong>{{ $ligne['objet'] }}</strong></td>
                                        <td class="td-texte-long">{{ $ligne['detail'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endforeach
@endsection
