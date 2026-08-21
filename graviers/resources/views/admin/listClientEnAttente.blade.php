@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title', 'Inscriptions en attente')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Inscriptions en attente de confirmation</h2>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <p class="text-muted mb-0">
                Ces personnes ont commencé leur inscription sans jamais saisir le code de
                confirmation reçu par e-mail. Leur compte n'est donc pas actif : elles ne
                peuvent ni se connecter, ni commander, et leur adresse e-mail reste
                réservée.
                <br>
                Pour débloquer l'une d'elles, invitez-la à refaire son inscription avec la
                même adresse : un nouveau code lui sera envoyé.
            </p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            @if ($clients->isEmpty())
                <p class="text-muted text-center mb-0 py-4">
                    Aucune inscription en attente. Toutes les inscriptions commencées ont été
                    menées à leur terme.
                </p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover" id="tableClientEnAttente">
                        <thead>
                            <tr>
                                <th class="text-center">Nom</th>
                                <th class="text-center">Email</th>
                                <th class="text-center">Contact</th>
                                <th class="text-center">Type de compte</th>
                                <th class="text-center">Inscription commencée le</th>
                                <th class="text-center">Depuis</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($clients as $c)
                                @php
                                    $debut = $c->created_at ? Carbon::parse($c->created_at) : null;
                                    $jours = $debut ? $debut->diffInDays(now()) : null;
                                @endphp
                                <tr>
                                    <td class="text-center">{{ $c->display_name }}</td>
                                    <td class="text-center">{{ $c->email ?: $c->user?->email }}</td>
                                    <td class="text-center">{{ $c->contact1 }}</td>
                                    <td class="text-center">{{ $c->type_client }}</td>
                                    {{-- data-order : sans lui, DataTables trierait la date comme
                                         du texte et « 09-08-2026 » passerait avant « 10-07-2026 ». --}}
                                    <td class="text-center" data-order="{{ $debut?->format('Y-m-d H:i:s') }}">
                                        {{ $debut ? $debut->format('d-m-Y à H:i') : '-' }}
                                    </td>
                                    <td class="text-center" data-order="{{ $jours }}">
                                        @if ($jours === null)
                                            -
                                        @elseif ($jours === 0)
                                            <span class="badge bg-info">aujourd'hui</span>
                                        @elseif ($jours <= 2)
                                            <span class="badge bg-warning text-dark">{{ $jours }} j</span>
                                        @else
                                            <span class="badge bg-danger">{{ $jours }} j</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('jspart')
    <script>
        $(function () {
            // Garde-fou : initialiser DataTables sur un tableau absent (liste vide)
            // déclenche « Requested unknown parameter » et casse la page.
            if ($('#tableClientEnAttente tbody tr').length) {
                $('#tableClientEnAttente').DataTable({
                    order: [[4, 'desc']],
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                });
            }
        });
    </script>
@endsection
