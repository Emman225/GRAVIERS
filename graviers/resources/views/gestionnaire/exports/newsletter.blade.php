{{-- Sert à la fois l'export PDF (dompdf) et l'export Word : Word ouvre
     nativement un document HTML servi sous l'extension .doc. Les styles sont
     posés en ligne, les deux moteurs ne suivant pas les feuilles externes. --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Abonnés à la lettre d'information</title>
</head>
<body style="font-family: DejaVu Sans, Arial, sans-serif; font-size:12px; color:#222;">

    <h2 style="margin:0 0 4px; color:#1c57a3;">Abonnés à la lettre d'information</h2>
    <p style="margin:0 0 4px; color:#666;">DALAKOUN SARL — édité le {{ $edite }}</p>
    <p style="margin:0 0 16px; color:#666;">
        {{ $abonnes->count() }} adresse(s), dont {{ $abonnes->where('statut', 1)->count() }} abonnée(s)
    </p>

    <table style="width:100%; border-collapse:collapse;">
        <thead>
            <tr>
                <th style="border:1px solid #999; padding:6px; background:#1c57a3; color:#fff; text-align:center;">N°</th>
                <th style="border:1px solid #999; padding:6px; background:#1c57a3; color:#fff; text-align:left;">Adresse e-mail</th>
                <th style="border:1px solid #999; padding:6px; background:#1c57a3; color:#fff; text-align:center;">Statut</th>
                <th style="border:1px solid #999; padding:6px; background:#1c57a3; color:#fff; text-align:left;">Origine</th>
                <th style="border:1px solid #999; padding:6px; background:#1c57a3; color:#fff; text-align:center;">Inscrit le</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($abonnes as $i => $abonne)
                <tr>
                    <td style="border:1px solid #999; padding:5px; text-align:center;">{{ $i + 1 }}</td>
                    <td style="border:1px solid #999; padding:5px;">{{ $abonne->email }}</td>
                    <td style="border:1px solid #999; padding:5px; text-align:center;">
                        {{ $abonne->statut == 1 ? 'Abonné' : 'Désabonné' }}
                    </td>
                    <td style="border:1px solid #999; padding:5px;">{{ $abonne->origine ?: '—' }}</td>
                    <td style="border:1px solid #999; padding:5px; text-align:center;">
                        {{ $abonne->created_at ? $abonne->created_at->format('d/m/Y H:i') : '—' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="border:1px solid #999; padding:12px; text-align:center; color:#666;">
                        Aucune adresse inscrite pour le moment.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
