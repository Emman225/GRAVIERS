@extends('layout.main')
@section('title', "Facture d'avoir")

@section('contenu')
    <section class="content-main">
        <div class="dash-welcome mb-4">
            <div class="dash-welcome-content">
                <div>
                    <h2 class="dash-welcome-title">
                        Facture <span class="dash-welcome-name">d'avoir</span>
                    </h2>
                    <p class="dash-welcome-subtitle">
                        Sur la facture {{ Help::formatNumeroFacture($facture->numero) }} — réf. DGI {{ $facture->fne_reference }}
                        — client {{ $facture->client?->display_name ?? $facture->client?->raison_sociale ?? '' }}
                    </p>
                </div>
            </div>
            <div class="dash-welcome-decoration"></div>
        </div>

        <div class="card dash-card">
            <div class="card-body">
                @if(session('warning'))
                    <div class="alert alert-warning">{{ session('warning') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif

                <p class="text-muted">
                    Indiquez, article par article, la quantité retournée ou à créditer. L'avoir est
                    certifié par la DGI (référence A…) avant d'être enregistré ; sans certification,
                    rien n'est créé. Le montant crédité au client est la part de la facture
                    correspondant aux quantités créditées.
                </p>

                @if($avoirs->isNotEmpty())
                    <div class="alert alert-info">
                        Avoirs déjà établis sur cette facture :
                        @foreach($avoirs as $a)
                            <span class="badge bg-danger">{{ $a->fne_reference }} ({{ number_format(abs($a->montant), 0, ',', ' ') }} fcfa)</span>
                        @endforeach
                    </div>
                @endif

                <form method="post" action="{{ route('orders.emettreAvoir', $facture) }}"
                      class="js-delete-form" data-confirm-mode="delete" data-confirm-title="Certifier l'avoir auprès de la DGI ?"
                      data-confirm-button="Oui, certifier l'avoir"
                      data-confirm-html="L'avoir sera certifié par la DGI (un sticker consommé) et enregistré avec sa référence. Cette opération ne s'annule pas.">
                    @csrf
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>Réf</th>
                                    <th>Désignation</th>
                                    <th class="text-end">Prix unitaire HT</th>
                                    <th class="text-end">Qté facturée</th>
                                    <th class="text-end">Reste à créditer</th>
                                    <th style="width: 160px;">Qté à créditer</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($articles as $article)
                                    <tr>
                                        <td>{{ $article['reference'] }}</td>
                                        <td>{{ $article['description'] }}</td>
                                        <td class="text-end">{{ number_format($article['amount'], 0, ',', ' ') }}</td>
                                        <td class="text-end">{{ rtrim(rtrim(number_format($article['quantity'], 2, ',', ' '), '0'), ',') }} {{ $article['measurementUnit'] }}</td>
                                        <td class="text-end">{{ rtrim(rtrim(number_format($article['reste'], 2, ',', ' '), '0'), ',') }}</td>
                                        <td>
                                            <input type="number" class="form-control" name="quantites[{{ $article['id'] }}]"
                                                   min="0" max="{{ $article['reste'] }}" step="any"
                                                   value="{{ old('quantites.' . $article['id'], 0) }}"
                                                   {{ $article['reste'] <= 0 ? 'disabled' : '' }}>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted">La réponse de la DGI ne porte aucun article : recertifiez la facture avant d'établir un avoir.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Motif de l'avoir <span class="text-danger">*</span></label>
                        <input type="text" name="motif" class="form-control" maxlength="255" required
                               value="{{ old('motif') }}" placeholder="Ex. : marchandise retournée, erreur de facturation…">
                    </div>

                    <a href="{{ route('orders.facturesValidees') }}" class="btn btn-light">Retour</a>
                    <button type="submit" class="btn btn-danger" {{ empty($articles) ? 'disabled' : '' }}>
                        <i class="fas fa-undo"></i> Certifier l'avoir auprès de la DGI
                    </button>
                </form>
            </div>
        </div>
    </section>
@endsection
