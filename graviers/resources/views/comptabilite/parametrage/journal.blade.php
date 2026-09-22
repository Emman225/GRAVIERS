@extends('layout.main')
@php
    use App\Models\JournalComptable;
    $titre = $mode === 'create' ? 'Nouveau journal' : 'Modifier le journal ' . $journal->code;
    $typeCourant = old('type', $journal->type);
@endphp
@section('title', $titre)

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">{{ $titre }}</h2>
        <div>
            <a href="{{ route('show.comptabilite.parametrage', ['onglet' => 'journaux']) }}" class="btn btn-light">
                <i class="material-icons md-arrow_back"></i> Retour aux journaux
            </a>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">@foreach ($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach</ul>
                        </div>
                    @endif

                    <form method="post"
                          action="{{ $mode === 'create' ? route('show.comptabilite.journaux.store') : route('show.comptabilite.journaux.update', $journal) }}">
                        @csrf
                        @if ($mode === 'edit') @method('PUT') @endif

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="code">Code <span class="text-danger">*</span></label>
                                <input type="text" name="code" id="code" class="form-control" required maxlength="6"
                                       value="{{ old('code', $journal->code) }}" placeholder="BQ">
                                <small class="text-muted">Celui du logiciel comptable.</small>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="libelle">Libellé <span class="text-danger">*</span></label>
                                <input type="text" name="libelle" id="libelle" class="form-control" required maxlength="100"
                                       value="{{ old('libelle', $journal->libelle) }}" placeholder="Journal de banque">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="type">Type <span class="text-danger">*</span></label>
                                <select name="type" id="type" class="form-select" required>
                                    @foreach (JournalComptable::TYPES as $cle => $libelle)
                                        <option value="{{ $cle }}" {{ $typeCourant === $cle ? 'selected' : '' }}>{{ $libelle }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-7" id="blocCompte">
                                <label class="form-label" for="compte_comptable_id">Compte de trésorerie</label>
                                <select name="compte_comptable_id" id="compte_comptable_id" class="form-select">
                                    <option value="">— À renseigner —</option>
                                    @foreach ($comptes as $compte)
                                        <option value="{{ $compte->id }}" {{ (int) old('compte_comptable_id', $journal->compte_comptable_id) === (int) $compte->id ? 'selected' : '' }}>{{ $compte->designation }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Le compte débité à chaque encaissement de ce journal, crédité à chaque décaissement.</small>
                            </div>
                        </div>

                        @if ($mode === 'edit' && !empty($emplois))
                            <div class="alert alert-info mt-3">
                                Ce journal est employé : {{ collect($emplois)->map(fn ($nombre, $lieu) => $nombre . ' ' . $lieu)->implode(', ') }}.
                            </div>
                        @endif

                        <div class="d-flex justify-content-between mt-4">
                            <a href="{{ route('show.comptabilite.parametrage', ['onglet' => 'journaux']) }}" class="btn btn-secondary">Annuler</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="material-icons md-save"></i> {{ $mode === 'create' ? 'Enregistrer' : 'Mettre à jour' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('jsParts')
<script>
    // Le compte de trésorerie n'a de sens que pour la banque, la caisse et le Mobile Money.
    document.addEventListener('DOMContentLoaded', function () {
        var type = document.getElementById('type');
        var bloc = document.getElementById('blocCompte');
        var tresorerie = {!! json_encode(JournalComptable::TYPES_DE_TRESORERIE) !!};
        function ajuster() { bloc.style.display = tresorerie.indexOf(type.value) === -1 ? 'none' : ''; }
        type.addEventListener('change', ajuster);
        ajuster();
    });
</script>
@endsection
