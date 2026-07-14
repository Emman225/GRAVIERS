@php
    $couleur = $couleurPrincipale ?? '#1c57a3';
    $isPdf = true;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $titre }} - {{ $numeroRecu }}</title>
    @include('admin.shared._recu_paiement_styles')
</head>
<body>
    @include('admin.shared._recu_paiement_body')
</body>
</html>
