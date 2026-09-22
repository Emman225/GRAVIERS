{{-- Le choix de la période : par dates, ou par mois. Le dernier mode choisi est
     mémorisé (réponse du responsable, question 8). Partagé par le journal et
     le rapport d'anomalies.

     Le mois est un seul champ « AAAA-MM » : deux champs séparés (mois, année)
     se désaccordent dès qu'on change de mois sans changer d'année. --}}
@php
    use App\Services\Comptabilite\JournalDesEcritures;
    $parMois = $periode['mode'] === JournalDesEcritures::PAR_MOIS;
@endphp

<form method="get" action="{{ $action }}" class="row g-2 align-items-end">
    @foreach (($champsConserves ?? []) as $nom => $valeur)
        @if ($valeur) <input type="hidden" name="{{ $nom }}" value="{{ $valeur }}"> @endif
    @endforeach

    <div class="col-auto">
        <label class="form-label d-block">Période</label>
        <div class="btn-group" role="group">
            <input type="radio" class="btn-check" name="mode_periode" id="mode-mois" value="{{ JournalDesEcritures::PAR_MOIS }}"
                   {{ $parMois ? 'checked' : '' }} onchange="this.form.submit()">
            <label class="btn btn-sm btn-outline-primary" for="mode-mois">Par mois</label>
            <input type="radio" class="btn-check" name="mode_periode" id="mode-dates" value="{{ JournalDesEcritures::PAR_DATES }}"
                   {{ $parMois ? '' : 'checked' }} onchange="this.form.submit()">
            <label class="btn btn-sm btn-outline-primary" for="mode-dates">Par dates</label>
        </div>
    </div>

    @if ($parMois)
        <div class="col-auto">
            <label class="form-label" for="periode-mois">Mois</label>
            <select name="periode" id="periode-mois" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach ($moisProposes as $option)
                    @php $valeur = sprintf('%04d-%02d', $option['annee'], $option['mois']); @endphp
                    <option value="{{ $valeur }}"
                            {{ sprintf('%04d-%02d', $periode['annee'], $periode['mois']) === $valeur ? 'selected' : '' }}>
                        {{ $option['libelle'] }}
                    </option>
                @endforeach
            </select>
        </div>
    @else
        <div class="col-auto">
            <label class="form-label" for="periode-du">Du</label>
            <input type="date" name="du" id="periode-du" class="form-control form-control-sm" value="{{ $periode['du']->toDateString() }}">
        </div>
        <div class="col-auto">
            <label class="form-label" for="periode-au">Au</label>
            <input type="date" name="au" id="periode-au" class="form-control form-control-sm" value="{{ $periode['au']->toDateString() }}">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-primary">Afficher</button>
        </div>
    @endif

    {!! $complement ?? '' !!}
</form>
