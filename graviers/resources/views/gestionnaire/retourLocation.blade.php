@php use Illuminate\Support\Carbon; $caution = (float) ($location->caution ?? 0); @endphp
@extends('layout.main')
@section('title','Retour du matériel loué')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Retour matériel — location {{ $location->numero }}</h2>
        <a href="{{ route('show.listeLocationEnAttente') }}" class="btn btn-outline-secondary btn-sm">Retour à la liste</a>
    </div>

    @if (isset($errors) && $errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <p class="mb-1"><strong>Client :</strong> {{ $location->client?->nom }} {{ $location->client?->prenom }}</p>
                    <p class="mb-1"><strong>Livreur affecté :</strong> {{ $location->livreur?->user?->nom_prenoms ?? '-' }}</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-1"><strong>Caution déposée :</strong> {{ number_format($caution, 0, ',', ' ') }} fcfa</p>
                    <p class="mb-1"><strong>État :</strong> <span class="badge bg-info text-dark">{{ $location->etatLibelle() }}</span></p>
                </div>
            </div>

            <form id="formRetour" method="POST" action="{{ route('show.retourLocation', $location) }}" class="row gx-3">
                @csrf
                <div class="col-md-4 mb-3">
                    <label class="form-label">Retenue sur caution (fcfa)</label>
                    <input type="number" id="caution_retenue" name="caution_retenue" min="0" max="{{ $caution }}" step="1"
                           class="form-control" value="{{ old('caution_retenue', 0) }}">
                    <small class="text-muted">0 = caution entièrement restituée. Max : {{ number_format($caution, 0, ',', ' ') }} fcfa.</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Montant restitué au client</label>
                    <input type="text" id="restitue" class="form-control" value="{{ number_format($caution, 0, ',', ' ') }}" readonly>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Motif de la retenue (si retenue)</label>
                    <input type="text" name="motif_retenue" class="form-control" maxlength="255"
                           value="{{ old('motif_retenue') }}" placeholder="Ex : casse, pièce manquante…">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-success">
                        <i class="material-icons md-check align-middle"></i> Valider le retour
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var caution = {{ $caution }};
            var ret = document.getElementById('caution_retenue');
            var out = document.getElementById('restitue');
            function maj() {
                var r = parseFloat(ret.value) || 0;
                if (r < 0) r = 0; if (r > caution) r = caution;
                out.value = (caution - r).toLocaleString('fr-FR');
            }
            if (ret) { ret.addEventListener('input', maj); maj(); }

            // Confirmation SweetAlert2 (remplace le confirm() natif). Le submit est
            // bloqué jusqu'à confirmation, puis relancé avec un drapeau anti-reboucle.
            var form = document.getElementById('formRetour');
            if (form) {
                form.addEventListener('submit', function (e) {
                    if (form.dataset.confirmed === '1') return;
                    e.preventDefault();
                    if (typeof Swal === 'undefined') { form.dataset.confirmed = '1'; form.submit(); return; }
                    var retenue = parseFloat((ret && ret.value) || 0) || 0;
                    var restitue = Math.max(0, caution - Math.min(Math.max(retenue, 0), caution));
                    Swal.fire({
                        title: 'Confirmer le retour ?',
                        html: 'La location passera à l\'état <b>TERMINÉE</b>.<br>'
                            + 'Retenue sur caution : <b>' + retenue.toLocaleString('fr-FR') + ' fcfa</b><br>'
                            + 'Restitué au client : <b>' + restitue.toLocaleString('fr-FR') + ' fcfa</b>',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Oui, valider le retour',
                        cancelButtonText: 'Annuler',
                        confirmButtonColor: '#198754',
                        cancelButtonColor: '#6c757d',
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            form.dataset.confirmed = '1';
                            form.submit();
                        }
                    });
                });
            }
        })();
    </script>
@endsection
