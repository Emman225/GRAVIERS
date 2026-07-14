<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reçu - {{ $paiement->numero_recu ?? $paiement->code }}</title>
    @php $isPdf = true; @endphp
    @include('admin.comptant._recu_styles')
</head>
<body>
    @include('admin.comptant._recu_body')
</body>
</html>
