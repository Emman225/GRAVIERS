{{--
    LES DÉCISIONS DE CRÉDIT QUI ATTENDENT LEUR SECOND CONTRÔLE.

    Le même bloc sur les deux écrans de saisie : celui qui vient d'enregistrer
    une décision doit voir qu'elle n'est PAS encore appliquée, et le second
    administrateur doit la trouver sans avoir à la chercher.

    Attend $decisions (collection) — voir DecisionClientTerme::enAttente().
--}}
@if (!empty($decisions) && $decisions->isNotEmpty())
    <div class="card mb-4 border-warning">
        <header class="card-header bg-warning bg-opacity-10">
            <strong>{{ $decisions->count() }}</strong>
            décision{{ $decisions->count() > 1 ? 's' : '' }} de crédit
            {{ $decisions->count() > 1 ? 'attendent' : 'attend' }} une seconde validation
        </header>

        <div class="card-body">
            {{-- Clés propres : Flasher capte success/error et les rejoue en
                 bulle flottante, qui s efface d elle-meme. --}}
            @if (session('decision_succes'))
                <div class="alert alert-success">{{ session('decision_succes') }}</div>
            @endif
            @if (session('decision_erreur'))
                <div class="alert alert-danger">{{ session('decision_erreur') }}</div>
            @endif

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Décision</th>
                            <th>Ce qu'elle change</th>
                            <th>Saisie par</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($decisions as $decision)
                            <tr>
                                <td>{{ $decision->client?->display_name ?? 'Client n° ' . $decision->client_id }}</td>
                                <td><span class="badge bg-secondary">{{ $decision->libelleType() }}</span></td>
                                <td class="small">{{ $decision->resume() }}</td>
                                <td class="small">
                                    {{ $decision->initie_par }}
                                    <br><span class="text-muted">
                                        {{ $decision->date_validation_1 ? \Carbon\Carbon::parse($decision->date_validation_1)->format('d/m/Y H:i:s') : '—' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if ($decision->peutEtreValidePar(auth()->user()))
                                        {{-- Les messages ne portent aucune apostrophe : delete-confirm.js
                                             construit le texte du SweetAlert par une expression
                                             régulière qui s'arrête à la première, même échappée. --}}
                                        <form method="post" action="{{ route('show.decisionCredit.valider', $decision) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success"
                                                onclick="return confirm('Confirmez-vous cette decision ? Elle sera appliquee immediatement et le client en sera informe.')">
                                                Valider
                                            </button>
                                        </form>
                                        <form method="post" action="{{ route('show.decisionCredit.refuser', $decision) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Confirmez-vous le refus ? Rien ne sera modifie sur le client.')">
                                                Refuser
                                            </button>
                                        </form>
                                    @else
                                        <span class="small text-muted">En attente d'un autre administrateur</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="text-muted small mb-0 mt-3">
                Tant qu'une décision n'est pas validée, <strong>rien n'est modifié</strong> sur le client
                et aucun e-mail ne lui est envoyé. Celui qui l'a saisie ne peut pas la valider lui-même.
            </p>
        </div>
    </div>
@endif
